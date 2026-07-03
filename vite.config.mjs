import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    vue(),
  ],
  publicDir: false,
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
