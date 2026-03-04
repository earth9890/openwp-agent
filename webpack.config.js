const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/admin/index.js',
		'ai-content-generator-block': './src/editor/index.js',
		'sitewide-chatbot': './src/sitewide-chatbot/index.js',
	},
};
