import Vue from 'vue';
import Vuex, { Store, StoreOptions } from 'vuex';
import ValidApplicationStatus from '@/definitions/ApplicationStatus';
import Application from '@/store/Application';
import actions from '@/store/actions';
import { getters } from '@/store/getters';
import { mutations } from '@/store/mutations';
import createEntity from './entity';
import {
	NS_ENTITY,
} from '@/store/namespaces';
import ServiceContainer from '@/services/ServiceContainer';

Vue.use( Vuex );

export function createStore( services: ServiceContainer ): Store<Application> {
	const state: Application = {
		targetLabel: null,
		targetProperty: '',
		originalStatement: null,
		editFlow: '',
		applicationStatus: ValidApplicationStatus.INITIALIZING,
		applicationErrors: [],
		wikibaseRepoConfiguration: null,
		editDecision: null,
	};

	const storeBundle: StoreOptions<Application> = {
		state,
		actions: actions(
			services.get( 'entityLabelRepository' ),
			services.get( 'wikibaseRepoConfigRepository' ),
			services.get( 'propertyDatatypeRepository' ),
			services.get( 'tracker' ),
		),
		getters,
		mutations,
		strict: process.env.NODE_ENV !== 'production',
		modules: {
			[ NS_ENTITY ]: createEntity(
				services.get( 'readingEntityRepository' ),
				services.get( 'writingEntityRepository' ),
			),
		},
	};

	return new Store<Application>( storeBundle );
}
