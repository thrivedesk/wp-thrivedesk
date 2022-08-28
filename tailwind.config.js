module.exports = {
	content: ['./includes/**/*.{html,js,jsx,ts,tsx,vue,php}'],
	important: true,
	theme: {
		extend: {
			zIndex: {
				'99': '99',
				'100': '100',
			}
		},
	},
	corePlugins: {
		float: false,
		objectFit: false,
		objectPosition: false,
	},
	plugins: [
		require('@tailwindcss/typography'),
	],
};
