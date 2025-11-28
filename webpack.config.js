const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		editor: [ './src/editor.js', './src/editor.css' ],
		frontend: [ './src/frontend.js', './src/frontend.css' ],
	},
};
