import Vue from 'vue';
import { Store } from 'vuex';
import BridgeConfig from '@/presentation/plugins/BridgeConfigPlugin';
import Application, { InitializedApplicationState, SavingState } from '@/store/Application';
import ApplicationStatus from '@/definitions/ApplicationStatus';
import AppInformation from '@/definitions/AppInformation';
import EditDecision from '@/definitions/EditDecision';
import { NS_ENTITY, NS_STATEMENTS } from '@/store/namespaces';
import DataValue from '@/datamodel/DataValue';
import { MainSnakPath } from '@/store/statements/MainSnakPath';
import ApplicationError, { ErrorTypes } from '@/definitions/ApplicationError';
import { Actions, Context } from 'vuex-smart-module';
import { RootGetters } from '@/store/getters';
import { RootMutations } from '@/store/mutations';
import Term from '@/datamodel/Term';
import { entityModule } from './entity';
import { statementModule } from '@/store/statements';
import { MissingPermissionsError } from '@/definitions/data-access/BridgePermissionsRepository';
import { WikibaseRepoConfiguration } from '@/definitions/data-access/WikibaseRepoConfigRepository';
import ServiceContainer from '@/services/ServiceContainer';
import statementMutationFactory from '@/change-op/statement-mutation/statementMutationFactory';
import clone from '@/store/clone';
import StatementMutationStrategy from '@/change-op/statement-mutation/strategies/StatementMutationStrategy';
import errorTypeFormatter from '@/utils/errorTypeFormatter';

export class RootActions extends Actions<
Application,
RootGetters,
RootMutations,
RootActions
> {
	private store!: { $services: ServiceContainer };
	private entityModule!: Context<typeof entityModule>;
	private statementModule!: Context<typeof statementModule>;
	private statementMutationFactory!: ( editDecision: EditDecision ) => StatementMutationStrategy;

	public $init( store: Store<Application> ): void {
		this.store = store;
		this.entityModule = entityModule.context( store );
		this.statementModule = statementModule.context( store );
		this.statementMutationFactory = statementMutationFactory;
	}

	public relaunchBridge( information: AppInformation ): Promise<void> {
		this.commit( 'reset' );
		this.entityModule.commit( 'reset' );
		this.statementModule.commit( 'reset' );

		return this.dispatch( 'initBridge', information );
	}

	public initBridge(
		information: AppInformation,
	): Promise<void> {
		this.commit( 'setEditFlow', information.editFlow );
		this.commit( 'setPropertyPointer', information.propertyId );
		this.commit( 'setEntityTitle', information.entityTitle );
		this.commit( 'setOriginalHref', information.originalHref );
		this.commit( 'setPageTitle', information.pageTitle );
		this.commit( 'setPageUrl', information.pageUrl );
		this.commit( 'setShowWarningAnonymousEdit', information.userName === null );

		this.dispatch( 'requestAndSetTargetLabel', information.propertyId );

		return Promise.all( [
			this.store.$services.get( 'wikibaseRepoConfigRepository' ).getRepoConfiguration(),
			this.store.$services.get( 'editAuthorizationChecker' ).canUseBridgeForItemAndPage(
				information.entityTitle,
				information.pageTitle,
			),
			this.store.$services.get( 'propertyDatatypeRepository' ).getDataType( information.propertyId ),
			this.entityModule.dispatch(
				'entityInit',
				{ entity: information.entityId },
			),
		] ).then(
			( results ) => this.dispatch( 'initBridgeWithRemoteData', { information, results } ),
			( error ) => {
				this.commit( 'addApplicationErrors', [ { type: ErrorTypes.INITIALIZATION_ERROR, info: error } ] );
				throw error;
			},
		).then( () => {
			this.commit(
				'setApplicationStatus',
				ApplicationStatus.READY,
			);
		} );
	}

	public async initBridgeWithRemoteData( {
		information,
		results: [
			wikibaseRepoConfiguration,
			permissionErrors,
			dataType,
			_entityInit,
		],
	}: {
		information: AppInformation;
		results: [ WikibaseRepoConfiguration, MissingPermissionsError[], string, unknown ];
	} ): Promise<void> {
		if ( permissionErrors.length ) {
			this.commit( 'addApplicationErrors', permissionErrors );
			return;
		}

		this.store.$services.get( 'tracker' ).trackPropertyDatatype( dataType );

		await this.dispatch( 'renderReferences' );

		BridgeConfig( Vue, { ...wikibaseRepoConfiguration, ...information.client } );

		return this.dispatch( 'postEntityLoad' );
	}

	public async renderReferences(): Promise<void> {
		const renderedReferences = await this.store.$services.get( 'referencesRenderingRepository' )
			.getRenderedReferences( this.getters.targetReferences );
		this.commit( 'setRenderedTargetReferences', renderedReferences );
	}

	public async postEntityLoad(): Promise<void> {
		const state = this.state as InitializedApplicationState;

		const path = new MainSnakPath(
			state[ NS_ENTITY ].id,
			state.targetProperty,
			0,
		);

		await this.dispatch( 'validateEntityState', path );
		if ( this.getters.applicationStatus !== ApplicationStatus.ERROR ) {
			this.commit(
				'setTargetValue',
				// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
				path.resolveSnakInStatement( state[ NS_STATEMENTS ] )!.datavalue!,
			);
		}
	}

	public requestAndSetTargetLabel( propertyId: string ): Promise<void> {
		return this.store.$services.get( 'entityLabelRepository' ).getLabel( propertyId )
			.then( ( label: Term ) => {
				this.commit( 'setTargetLabel', label );
			}, ( _error: Error ) => {
				// TODO: handling on failed label loading, which is not a bocking error for now
			} );
	}

	public validateEntityState(
		path: MainSnakPath,
	): Promise<void> {
		if ( !this.statementModule.getters.propertyExists( path ) ) {
			this.commit( 'addApplicationErrors', [ { type: ErrorTypes.INVALID_ENTITY_STATE_ERROR } ] );
			return Promise.resolve();
		}

		return this.dispatch( 'validateBridgeApplicability', path );
	}

	public validateBridgeApplicability(
		path: MainSnakPath,
	): Promise<void> {
		if ( this.state.applicationStatus === ApplicationStatus.SAVED ) {
			// saving edits can transition us from applicable to inapplicable states, but that should not be an error
			return Promise.resolve();
		}

		if ( this.statementModule.getters.isStatementGroupAmbiguous( path ) ) {
			return this.dispatch( 'addError', [ { type: ErrorTypes.UNSUPPORTED_AMBIGUOUS_STATEMENT } ] );
		}

		if ( this.statementModule.getters.rank( path ) === 'deprecated' ) {
			return this.dispatch( 'addError', [ { type: ErrorTypes.UNSUPPORTED_DEPRECATED_STATEMENT } ] );
		}

		const snakType = this.statementModule.getters.snakType( path );
		if ( snakType === null ) {
			throw new Error( 'If snak type is missing, there should have been an error earlier' );
		}
		if ( snakType !== 'value' ) {
			const error: ApplicationError = {
				type: ErrorTypes.UNSUPPORTED_SNAK_TYPE,
				info: { snakType },
			};
			return this.dispatch( 'addError', [ error ] );
		}

		const datatype = this.statementModule.getters.dataType( path );
		if ( datatype === null ) {
			throw new Error( 'If snak is missing, there should have been an error earlier' );
		}
		if ( datatype !== 'string' ) {
			const error: ApplicationError = {
				type: ErrorTypes.UNSUPPORTED_DATATYPE,
				info: {
					unsupportedDatatype: datatype,
				},
			};
			return this.dispatch( 'addError', [ error ] );
		}

		if ( this.statementModule.getters.dataValueType( path ) !== 'string' ) {
			return this.dispatch( 'addError', [ { type: ErrorTypes.UNSUPPORTED_DATAVALUE_TYPE } ] );
		}

		return Promise.resolve();
	}

	public setTargetValue(
		dataValue: DataValue,
	): Promise<void> {
		if ( this.state.applicationStatus !== ApplicationStatus.READY ) {
			this.commit( 'addApplicationErrors', [ {
				type: ErrorTypes.APPLICATION_LOGIC_ERROR,
				info: { stack: ( new Error() ).stack },
			} ] );
			return Promise.reject( null );
		}

		this.commit( 'setTargetValue', dataValue );

		return Promise.resolve();
	}

	public async saveBridge(): Promise<void> {
		if ( this.state.applicationStatus !== ApplicationStatus.READY ) {
			this.commit( 'addApplicationErrors', [ {
				type: ErrorTypes.APPLICATION_LOGIC_ERROR,
				info: { stack: ( new Error() ).stack },
			} ] );
			return Promise.reject( null );
		}
		this.commit(
			'setApplicationStatus',
			ApplicationStatus.SAVING,
		);
		const state = this.state as SavingState;
		const entityId = state[ NS_ENTITY ].id;
		const path = new MainSnakPath(
			entityId,
			state.targetProperty,
			0,
		);

		let statements;
		try {
			statements = this.statementMutationFactory( state.editDecision )
				.apply(
					state.targetValue,
					path,
					clone( state[ NS_STATEMENTS ] ),
				);
		} catch ( error ) {
			this.commit( 'addApplicationErrors', [ {
				type: ErrorTypes.APPLICATION_LOGIC_ERROR,
				info: error,
			} ] );
			throw error;
		}

		return this.entityModule.dispatch( 'entitySave', { statements: statements[ entityId ] } )
			.catch( ( error: Error ) => {
				this.commit( 'addApplicationErrors', [ { type: ErrorTypes.SAVING_FAILED, info: error } ] );
				throw error;
			} )
			.then( () => {
				return this.store.$services.get( 'purgeTitles' )
					.purge( [ this.state.pageTitle ] )
					.catch( () => {
						// we don't want to stop normal operation in that case
						this.store.$services.get( 'tracker' ).trackTitlePurgeError();
					} );
			} )
			.then( () => {
				this.commit(
					'setApplicationStatus',
					ApplicationStatus.SAVED,
				);
				return this.dispatch( 'postEntityLoad' );
			} );
	}

	public async retrySave(): Promise<void> {
		await this.dispatch( 'goBackFromErrorToReady' );
		return this.dispatch( 'saveBridge' );
	}

	public goBackFromErrorToReady(): Promise<void> {
		this.commit( 'clearApplicationErrors' );
		this.commit( 'setApplicationStatus', ApplicationStatus.READY );
		return Promise.resolve();
	}

	public addError(
		errors: ApplicationError[],
	): Promise<void> {
		this.commit( 'addApplicationErrors', errors );
		return Promise.resolve();
	}

	public setEditDecision(
		editDecision: EditDecision,
	): Promise<void> {
		this.commit( 'setEditDecision', editDecision );
		return Promise.resolve();
	}

	public async trackApplicationErrorsAsUnknown(): Promise<void> {
		for ( const error of this.state.applicationErrors ) {
			const type = errorTypeFormatter( error.type );
			this.store.$services.get( 'tracker' ).trackUnknownError( type );
		}
	}

	public async trackApplicationErrorsOnSaveAsUnknown(): Promise<void> {
		for ( const error of this.state.applicationErrors ) {
			const type = errorTypeFormatter( error.type );
			this.store.$services.get( 'tracker' ).trackSavingUnknownError( type );
		}
	}

}
