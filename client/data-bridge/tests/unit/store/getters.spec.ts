import { ErrorTypes } from '@/definitions/ApplicationError';
import EditDecision from '@/definitions/EditDecision';
import {
	NS_ENTITY,
	NS_STATEMENTS,
} from '@/store/namespaces';
import newApplicationState from './newApplicationState';
import ApplicationStatus, { ValidApplicationStatus } from '@/definitions/ApplicationStatus';
import clone from '@/store/clone';
import { inject } from 'vuex-smart-module';
import { RootGetters } from '@/store/getters';
import Statement from '@/datamodel/Statement';
import Reference from '@/datamodel/Reference';

describe( 'root/getters', () => {
	const entityId = 'Q42';
	const targetProperty = 'P23';

	describe( 'targetLabel', () => {
		it( 'returns the targetProperty and no linguistic content' +
			', if no targetLabel is set.', () => {
			const applicationState = newApplicationState( { targetProperty } );
			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.targetLabel ).toStrictEqual( { value: targetProperty, language: 'zxx' } );
		} );

		it( 'returns the targetLabel term', () => {
			const targetLabel = { language: 'zh', value: '土豆' };
			const applicationState = newApplicationState( { targetLabel } );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.targetLabel ).toBe( targetLabel );
		} );
	} );

	describe( 'isTargetValueModified', () => {

		it( 'returns false if the application is still initializing', () => {
			const actualTargetStatement = {
				type: 'statement',
				id: 'opaque statement ID',
				rank: 'normal',
				mainsnak: {
					snaktype: 'value',
					property: 'P60',
					datatype: 'string',
					datavalue: {
						type: 'string',
						value: 'a string value',
					},
				},
			} as Statement;

			const targetValue = clone( actualTargetStatement.mainsnak.datavalue );
			actualTargetStatement.mainsnak.datavalue!.value = 'modified teststring';
			const applicationState = newApplicationState( {
				targetProperty,
				applicationStatus: ApplicationStatus.INITIALIZING,
				targetValue,
				[ NS_ENTITY ]: {
					id: entityId,
				},
				[ NS_STATEMENTS ]: {
					[ entityId ]: {
						[ targetProperty ]: [ actualTargetStatement ],
					},
				},
			} );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isTargetValueModified ).toBe( false );
		} );

		it( 'returns false if there is no diff', () => {
			const actualTargetStatement = {
				type: 'statement',
				id: 'opaque statement ID',
				rank: 'normal',
				mainsnak: {
					snaktype: 'value',
					property: 'P60',
					datatype: 'string',
					datavalue: {
						type: 'string',
						value: 'a string value',
					},
				},
			} as Statement;

			const targetValue = clone( actualTargetStatement.mainsnak.datavalue );
			const applicationState = newApplicationState( {
				targetProperty,
				applicationStatus: ApplicationStatus.READY,
				targetValue,
				[ NS_ENTITY ]: {
					id: entityId,
				},
				[ NS_STATEMENTS ]: {
					[ entityId ]: {
						[ targetProperty ]: [ actualTargetStatement ],
					},
				},
			} );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isTargetValueModified ).toBe( false );
		} );

		it( 'returns true if there is a diff', () => {
			const actualTargetStatement = {
				type: 'statement',
				id: 'opaque statement ID',
				rank: 'normal',
				mainsnak: {
					snaktype: 'value',
					property: 'P60',
					datatype: 'string',
					datavalue: {
						type: 'string',
						value: 'a string value',
					},
				},
			} as Statement;

			const targetValue = clone( actualTargetStatement.mainsnak.datavalue );
			actualTargetStatement.mainsnak.datavalue!.value = 'modified teststring';

			const applicationState = newApplicationState( {
				targetProperty,
				applicationStatus: ApplicationStatus.READY,
				targetValue,
				[ NS_ENTITY ]: {
					id: entityId,
				},
				[ NS_STATEMENTS ]: {
					[ entityId ]: {
						[ targetProperty ]: [ actualTargetStatement ],
					},
				},
			} );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isTargetValueModified ).toBe( true );
		} );
	} );

	describe( 'canStartSaving', () => {

		it.each( [
			[ false, false, null, ApplicationStatus.READY ],
			[ false, true, null, ApplicationStatus.READY ],
			[ false, false, EditDecision.REPLACE, ApplicationStatus.READY ],
			[ true, true, EditDecision.REPLACE, ApplicationStatus.READY ],
			[ false, true, EditDecision.UPDATE, ApplicationStatus.SAVING ],
		] )(
			'returns %p with isTargetValueModified=%p and editDecision=%p',
			( expected: boolean,
				isTargetValueModified: boolean,
				editDecision: EditDecision|null,
				applicationStatus: ApplicationStatus ) => {
				const applicationState = newApplicationState( { editDecision } );

				// @ts-ignore
				const getters = inject( RootGetters, {
					state: applicationState,
					getters: {
						isTargetValueModified,
						applicationStatus,
					},
				} );

				expect( getters.canStartSaving ).toBe( expected );
			},
		);

	} );

	describe( 'targetReferences', () => {
		const expectedTargetReferences = [
			{
				snaks: {
					P268: [
						{
							snaktype: 'value',
							property: 'P268',
							hash: '8721e8944f95e9ce185c270dd1e12b81d13f7e9b',
							datavalue: {
								value: '11888092r',
								type: 'string',
							},
							'datatype': 'external-id',
						},
					],
				},
				'snaks-order': [ 'P268' ],
			} as Partial<Reference>,
		];

		it( 'returns the references datablob', () => {
			const applicationState = newApplicationState( {
				targetProperty,
				applicationStatus: ApplicationStatus.READY,
				[ NS_ENTITY ]: {
					id: entityId,
				},
				[ NS_STATEMENTS ]: {
					[ entityId ]: {
						[ targetProperty ]: [ {
							references: expectedTargetReferences,
						} ],
					},
				},
			} );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.targetReferences ).toBe( expectedTargetReferences );
		} );

		it( 'returns an empty array, if there are no references', () => {
			const applicationState = newApplicationState( {
				targetProperty,
				applicationStatus: ApplicationStatus.READY,
				[ NS_ENTITY ]: {
					id: entityId,
				},
				[ NS_STATEMENTS ]: {
					[ entityId ]: {
						[ targetProperty ]: [ {} ],
					},
				},
			} );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.targetReferences ).toStrictEqual( [] );
		} );

		it( 'returns the references blob without considering the ApplicationStatus', () => {
			const applicationState = newApplicationState( {
				targetProperty,
				applicationStatus: ApplicationStatus.INITIALIZING,
				[ NS_ENTITY ]: {
					id: entityId,
				},
				[ NS_STATEMENTS ]: {
					[ entityId ]: {
						[ targetProperty ]: [ {
							references: expectedTargetReferences,
						} ],
					},
				},
			} );

			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.targetReferences ).toStrictEqual( expectedTargetReferences );
		} );

		it( 'returns an empty array, if the entity is not yet available', () => {
			const getters = inject( RootGetters, {
				state: newApplicationState(),
			} );

			expect( getters.targetReferences ).toStrictEqual( [] );
		} );
	} );

	describe( 'applicationStatus', () => {
		it.each( [
			ValidApplicationStatus.INITIALIZING,
			ValidApplicationStatus.READY,
			ValidApplicationStatus.SAVING,
		] )(
			'returns underlying valid application status (%s) if there are no errors',
			( status: ValidApplicationStatus ) => {
				const applicationState = newApplicationState( {
					applicationStatus: status,
				} );

				const getters = inject( RootGetters, {
					state: applicationState,
				} );

				expect( getters.applicationStatus ).toBe( status );
			},
		);

		it.each( [
			ValidApplicationStatus.INITIALIZING,
			ValidApplicationStatus.READY,
			ValidApplicationStatus.SAVING,
		] )(
			'returns error application status instead of "%s" if there are errors',
			( status: ValidApplicationStatus ) => {
				const applicationState = newApplicationState( {
					applicationStatus: status,
					applicationErrors: [ { type: ErrorTypes.APPLICATION_LOGIC_ERROR, info: {} } ],
				} );

				const getters = inject( RootGetters, {
					state: applicationState,
				} );

				expect( getters.applicationStatus ).toBe( ApplicationStatus.ERROR );
			},
		);
	} );

	describe( 'reportIssueTemplateBody', () => {
		it( 'builds a string matching the snapshot', () => {
			const pageUrl = 'https://bg.client.example.com/wiki/Дъглас_Адамс';
			const entityTitle = `Item:${entityId}`;
			const applicationState = newApplicationState( {
				entityTitle,
				targetProperty,
				pageUrl,
				applicationErrors: [ { type: ErrorTypes.APPLICATION_LOGIC_ERROR, info: { stack: 'test' } } ],
			} );
			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.reportIssueTemplateBody ).toMatchSnapshot();
		} );
	} );

	describe( 'isGenericSavingError', () => {
		it( 'is false if there is no error', () => {
			const applicationState = newApplicationState();
			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isGenericSavingError ).toBe( false );
		} );

		it( 'is false if there are only non-saving errors', () => {
			const applicationState = newApplicationState( {
				applicationErrors: [
					{ type: ErrorTypes.APPLICATION_LOGIC_ERROR, info: { stack: 'test' } },
					{ type: ErrorTypes.UNSUPPORTED_DATATYPE },
				],
			} );
			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isGenericSavingError ).toBe( false );
		} );

		it( 'is false if there is at least one error other than a saving error', () => {
			const applicationState = newApplicationState( {
				applicationErrors: [
					{ type: ErrorTypes.APPLICATION_LOGIC_ERROR, info: { stack: 'test' } },
					{ type: ErrorTypes.SAVING_FAILED, info: { stack: 'test' } },
				],
			} );
			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isGenericSavingError ).toBe( false );
		} );

		it( 'is true if there are only saving errors', () => {
			const applicationState = newApplicationState( {
				applicationErrors: [
					{ type: ErrorTypes.SAVING_FAILED, info: { stack: 'test' } },
					{ type: ErrorTypes.SAVING_FAILED, info: { stack: 'test2' } },
				],
			} );
			const getters = inject( RootGetters, {
				state: applicationState,
			} );

			expect( getters.isGenericSavingError ).toBe( true );
		} );
	} );

	describe( 'canGoToPreviousState', () => {

		it( 'is true if isGenericSavingError is true', () => {
			const applicationState = newApplicationState();
			const isSavingError = true;

			// @ts-ignore
			const getters = inject( RootGetters, {
				state: applicationState,
				getters: {
					isGenericSavingError: isSavingError,
				},
			} );

			expect( getters.canGoToPreviousState ).toBe( true );
		} );

		it( 'is false if isGenericSavingError is false', () => {
			const applicationState = newApplicationState();
			const isSavingError = false;

			// @ts-ignore
			const getters = inject( RootGetters, {
				state: applicationState,
				getters: {
					isGenericSavingError: isSavingError,
				},
			} );

			expect( getters.canGoToPreviousState ).toBe( false );
		} );
	} );

} );
