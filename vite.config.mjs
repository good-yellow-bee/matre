import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
  plugins: [
    vue(),
  ],
  publicDir: false,
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets'),
    },
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    rollupOptions: {
      input: {
        spa: './assets/spa/main.js',
      },
    },
  },
  server: {
    strictPort: true,
    port: 5173,
    host: 'localhost',
  },
});
