module.exports = {
	content: ['./includes/**/*.{html,js,jsx,ts,tsx,vue,php}'],
	important: true,
	theme: {
		extend: {},
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
