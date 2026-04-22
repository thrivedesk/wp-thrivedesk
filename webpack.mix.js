let mix = require('laravel-mix');

mix.disableNotifications();

mix.webpackConfig({
	stats: {
		children: false
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
