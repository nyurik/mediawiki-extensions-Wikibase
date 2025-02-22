import { Services } from '@/services/ServiceContainer';

export default function newMockServiceContainer( services: Partial<{
	[ K in keyof Services ]: any;
}> ): any {
	return {
		get( name: keyof Services ) {
			if ( !services[ name ] ) {
				return {};
			}
			return services[ name ];
		},
	};
}
