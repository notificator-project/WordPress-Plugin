import { defineConfig } from 'vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
	root: __dirname,
	build: {
		outDir: path.resolve(__dirname, 'assets/dist'),
		emptyOutDir: true,
		sourcemap: false,
		cssCodeSplit: false,
		rollupOptions: {
			input: path.resolve(__dirname, 'assets/src/admin.ts'),
			output: {
				format: 'iife',
				name: 'NotificatorCompanionAdmin',
				inlineDynamicImports: true,
				entryFileNames: 'admin.js',
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith('.css')) {
						return 'admin.css';
					}
					return '[name].[ext]';
				}
			}
		},
		minify: 'esbuild'
	}
});
