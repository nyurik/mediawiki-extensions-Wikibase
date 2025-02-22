const path = require( 'path' );
const filePrefix = 'tainted-ref.';
const DEV_MODE = process.env.WEBPACK_TARGET === 'dev';

/**
 * In production vue is provided by ResourceLoader,
 * in dev it is still webpack's job to make it available
 */
function vueExternal() {
	return DEV_MODE ? {} : {
		vue: {
			commonjs: 'vue2',
			commonjs2: 'vue2',
			amd: 'vue2',
			root: 'vue2',
		},
	};
}

module.exports = {
	configureWebpack: () => ( {
		output: {
			filename: `${filePrefix}[name].js`,
		},
		entry: {
			app: './src/main.ts',
			init: './src/tainted-ref.init.ts',
		},
		externals: [ vueExternal() ],
		resolve: {
			symlinks: false,
			alias: {
				'@': path.join( __dirname, 'src' ),
			},
		},
	} ),
	chainWebpack: ( config ) => {
		config.optimization.delete( 'splitChunks' );

		if ( process.env.NODE_ENV === 'production' ) {
			config.plugin( 'extract-css' )
				.tap( ( [ options, ...args ] ) => [
					Object.assign( {}, options, { filename: `${filePrefix}[name].css` } ),
					...args,
				] );
			config.module
				.rule( 'images' )
				.test( /\.(png|jpe?g|gif|svg)(\?.*)?$/ )
				.use( 'url-loader' )
				.loader( 'url-loader' )
				.options( {
					limit: -1,
					name: '[path]/[name].[ext]',
				} );
		}
	},
	css: {
		loaderOptions: {
			sass: {
				data: '@import "@/presentation/styles/_main.scss";',
			},
		},
	},
};
