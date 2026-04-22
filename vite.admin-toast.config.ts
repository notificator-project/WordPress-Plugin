import { defineConfig } from 'vite';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default defineConfig({
	root: __dirname,
	build: {
		outDir: path.resolve(__dirname, 'assets/dist'),
		emptyOutDir: false,
		sourcemap: false,
		cssCodeSplit: false,
		rollupOptions: {
			input: path.resolve(__dirname, 'assets/src/admin-toast.ts'),
			output: {
				format: 'iife',
				name: 'NotificatorCompanionAdminToast',
				inlineDynamicImports: true,
				entryFileNames: 'admin-toast.js',
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && assetInfo.name.endsWith('.css')) {
						return 'admin-toast.css';
					}
					return '[name].[ext]';
				}
			}
		},
		minify: 'esbuild'
	}
});
