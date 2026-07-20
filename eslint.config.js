import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
	{
		ignores: [
			'assets/dist/**',
			'dist/**',
			'node_modules/**',
			'vendor/**',
			// This Alpine component is the documented legacy migration boundary.
			'assets/js/admin-scenarios.ts'
		]
	},
	...tseslint.configs.recommended,
	{
		files: ['assets/**/*.ts'],
		languageOptions: {
			globals: {
				...globals.browser,
				ajaxurl: 'readonly',
				jQuery: 'readonly'
			}
		},
		rules: {
			'no-alert': 'off',
			'no-console': ['error', { allow: ['warn', 'error'] }],
			'@typescript-eslint/no-explicit-any': 'error',
			'@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' }]
		}
	}
);
