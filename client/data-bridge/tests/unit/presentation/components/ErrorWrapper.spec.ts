import ErrorWrapper from '@/presentation/components/ErrorWrapper.vue';
import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import {
	MissingPermissionsError,
	PageNotEditable,
	ProtectedReason,
} from '@/definitions/data-access/BridgePermissionsRepository';
import ErrorPermission from '@/presentation/components/ErrorPermission.vue';
import ErrorUnknown from '@/presentation/components/ErrorUnknown.vue';
import ErrorUnsupportedDatatype from '@/presentation/components/ErrorUnsupportedDatatype.vue';
import ErrorDeprecatedStatement from '@/presentation/components/ErrorDeprecatedStatement.vue';
import ErrorAmbiguousStatement from '@/presentation/components/ErrorAmbiguousStatement.vue';
import ErrorUnsupportedSnakType from '@/presentation/components/ErrorUnsupportedSnakType.vue';
import ErrorSaving from '@/presentation/components/ErrorSaving.vue';
import ApplicationError, { ErrorTypes, UnsupportedDatatypeError } from '@/definitions/ApplicationError';
import { createTestStore } from '../../../util/store';

const localVue = createLocalVue();
localVue.use( Vuex );

describe( 'ErrorWrapper', () => {
	it( 'mounts ErrorUnknown on empty applicationErrors', () => {
		const store = createTestStore( {
			state: {
				applicationErrors: [],
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );
		expect( wrapper.find( ErrorUnknown ).exists() ).toBe( true );
		expect( wrapper.find( ErrorPermission ).exists() ).toBe( false );
	} );

	it( 'mounts ErrorUnknown for unknown errors', () => {
		const store = createTestStore( {
			state: {
				applicationErrors: [
					{
						type: ErrorTypes.INVALID_ENTITY_STATE_ERROR,
					},
				],
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );
		expect( wrapper.find( ErrorUnknown ).exists() ).toBe( true );
		expect( wrapper.find( ErrorPermission ).exists() ).toBe( false );
	} );

	it( 'shows ErrorPermission if a permission error is contained in the application errors', () => {
		const applicationErrors: ApplicationError[] = [
			{
				type: PageNotEditable.ITEM_SEMI_PROTECTED,
			} as ProtectedReason,
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, {
			localVue,
			store,
		} );

		const permissionErrorComponent = wrapper.find( ErrorPermission );
		expect( permissionErrorComponent.exists() ).toBe( true );
		expect( permissionErrorComponent.props( 'permissionErrors' ) ).toEqual( applicationErrors );
		expect( wrapper.find( ErrorUnknown ).exists() ).toBe( false );
	} );

	it( 'shows only ErrorPermission even if permission errors are mixed with other application errors', () => {
		const permissionErrors: MissingPermissionsError[] = [
			{
				type: PageNotEditable.ITEM_SEMI_PROTECTED,
				info: {
					right: 'editsemiprotected',
				},
			},
			{
				type: PageNotEditable.PAGE_CASCADE_PROTECTED,
				info: {
					pages: [ 'Page' ],
				},
			},
		];
		const applicationErrors: ApplicationError[] = [
			{
				type: ErrorTypes.APPLICATION_LOGIC_ERROR,
				info: {},
			},
			...permissionErrors,
			{
				type: ErrorTypes.INVALID_ENTITY_STATE_ERROR,
			},
			{
				type: ErrorTypes.UNSUPPORTED_DATATYPE,
				info: {
					unsupportedDatatype: 'time',
				},
			} as UnsupportedDatatypeError,
			{
				type: ErrorTypes.UNSUPPORTED_DEPRECATED_STATEMENT,
			},
			{
				type: ErrorTypes.UNSUPPORTED_AMBIGUOUS_STATEMENT,
			},
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, {
			localVue,
			store,
		} );

		const permissionErrorComponent = wrapper.find( ErrorPermission );
		expect( permissionErrorComponent.exists() ).toBe( true );
		expect( permissionErrorComponent.props( 'permissionErrors' ) ).toEqual( permissionErrors );
		expect( wrapper.find( ErrorUnknown ).exists() ).toBe( false );
	} );

	// eslint-disable-next-line max-len
	it( 'mounts ErrorUnsupportedDatatype when an unsupported data type error is present in the application errors', () => {
		const applicationErrors: ApplicationError[] = [
			{
				type: ErrorTypes.UNSUPPORTED_DATATYPE,
				info: {
					unsupportedDatatype: 'time',
				},
			} as UnsupportedDatatypeError,
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );
		expect( wrapper.find( ErrorUnsupportedDatatype ).exists() ).toBe( true );
	} );

	// eslint-disable-next-line max-len
	it( 'mounts ErrorDeprecatedStatement when a deprecated statement error is present in the application errors', () => {
		const applicationErrors: ApplicationError[] = [
			{
				type: ErrorTypes.UNSUPPORTED_DEPRECATED_STATEMENT,
			},
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );

		expect( wrapper.find( ErrorDeprecatedStatement ).exists() ).toBe( true );
	} );

	it( 'mounts ErrorAmbiguousStatement when an ambiguous statement error is present in the application errors', () => {
		const applicationErrors: ApplicationError[] = [
			{
				type: ErrorTypes.UNSUPPORTED_AMBIGUOUS_STATEMENT,
			},
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );

		expect( wrapper.find( ErrorAmbiguousStatement ).exists() ).toBe( true );
	} );

	it( 'mounts ErrorUnsupportedSnakType on unsupported snak type application error', () => {
		const applicationErrors: ApplicationError[] = [
			{
				type: ErrorTypes.UNSUPPORTED_SNAK_TYPE,
				info: {
					snakType: 'somevalue',
				},
			},
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );
		expect( wrapper.find( ErrorUnsupportedSnakType ).exists() ).toBe( true );
	} );

	it( 'mounts ErrorSaving on saving error', () => {
		const applicationErrors: ApplicationError[] = [
			{
				type: ErrorTypes.SAVING_FAILED,
			},
		];
		const store = createTestStore( {
			state: {
				applicationErrors,
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { localVue, store } );
		expect( wrapper.find( ErrorSaving ).exists() ).toBe( true );
	} );

	it( 'repeats ErrorUnknown\'s "relaunch" event', () => {
		const store = createTestStore( {
			state: {
				applicationErrors: [
					{
						type: ErrorTypes.INVALID_ENTITY_STATE_ERROR,
					},
				],
			},
		} );
		const wrapper = shallowMount( ErrorWrapper, { store, localVue } );
		wrapper.find( ErrorUnknown ).vm.$emit( 'relaunch' );
		expect( wrapper.emitted( 'relaunch' ) ).toHaveLength( 1 );
	} );
} );
