let mix = require('laravel-mix');


mix.js('resources/js/admin.js', 'js')
	.postCss('resources/css/admin.css', 'css')
	.version()
	.setPublicPath('assets');
