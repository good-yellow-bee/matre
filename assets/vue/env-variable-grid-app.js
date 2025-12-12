import { createApp } from 'vue';
import EnvVariableGrid from './components/EnvVariableGrid.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="env-variable-grid"]');
  if (!target) {
    return;
  }

  const apiUrl = target.dataset.apiUrl || '/api/env-variables';
  const csrfToken = target.dataset.csrfToken || '';

  createApp(EnvVariableGrid, { apiUrl, csrfToken }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
