let mix = require('laravel-mix');

mix.disableNotifications();

mix.webpackConfig({
	stats: {
		children: false
	},
	// WordPress serves @wordpress/i18n as the global wp.i18n (loaded via the
	// wp-i18n script dependency), so reference it instead of bundling a copy.
	externals: {
		'@wordpress/i18n': 'wp.i18n'
	}
});

mix.override((webpackConfig) => {
	webpackConfig.plugins = webpackConfig.plugins.filter(
		(plugin) => plugin.constructor.name !== 'WebpackBarPlugin'
	);
});

mix.options({
	processCssUrls: false
});

mix.js('resources/js/admin.js', 'js')
	.js('resources/js/conversation.js', 'js')
	.postCss('resources/css/admin.css', 'css')
	.postCss('resources/css/thrivedesk.css', 'css')
	.version()
	.setPublicPath('assets');

mix.disableSuccessNotifications();
