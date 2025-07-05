import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    root: 'public',

    resolve: {
        alias: {
            '/src': path.resolve(__dirname, './src'),
        },
    },

    build: {
        outDir: '../dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                main: path.resolve(__dirname, 'public/index.html')
            },
            output: {
                entryFileNames: `assets/[name]-[hash].js`,
                chunkFileNames: `assets/[name]-[hash].js`,
                assetFileNames: `assets/[name]-[hash].[ext]`
            }
        },
        minify: true
    },

    server: {
        port: 3000,
        open: true,
        proxy: {
            '/api': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
                rewrite: (path) => path,
            }
        }
    }
});