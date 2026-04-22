import type { Config } from 'tailwindcss';

const config: Config = {
	content: [
		'./admin/**/*.php',
		'./includes/**/*.php',
		'./assets/js/**/*.ts',
		'./assets/src/**/*.{ts,scss}'
	],
	important: '.notificator-companion-wrap',
	corePlugins: {
		preflight: false
	},
	theme: {
		extend: {}
	},
	plugins: []
};

export default config;
