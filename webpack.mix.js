let mix = require('laravel-mix');

mix.js('resources/js/admin.js', 'js')
	.js('resources/js/conversation.js', 'js')
	.postCss('resources/css/admin.css', 'css')
	.postCss('resources/css/thrivedesk.css', 'css')
	.version()
	.setPublicPath('assets').options({
		processCssUrls: false
	});
