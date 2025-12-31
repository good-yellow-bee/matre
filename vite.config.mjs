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
        app: './assets/app.js',
        admin: './assets/admin.js',
        admin_vue: './assets/vue/admin-dashboard-app.js',
        admin_users_vue: './assets/vue/admin-users-app.js',
        'user-form-app': './assets/vue/user-form-app.js',
        'cron-job-grid-app': './assets/vue/cron-job-grid-app.js',
        'test-run-grid-app': './assets/vue/test-run-grid-app.js',
        'env-variable-grid-app': './assets/vue/env-variable-grid-app.js',
        'environment-variables-app': './assets/vue/environment-variables-app.js',
        'test-pattern-selector-app': './assets/vue/test-pattern-selector-app.js',
        'test-history-app': './assets/vue/test-history-app.js',
        'test-id-selector-app': './assets/vue/test-id-selector-app.js',
        'test-step-tree-app': './assets/vue/test-step-tree-app.js',
      },
    },
  },
  server: {
    strictPort: true,
    port: 5173,
    host: 'localhost',
  },
});
