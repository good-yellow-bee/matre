import { createApp } from 'vue';
import EnvironmentVariables from './components/EnvironmentVariables.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="environment-variables"]');
  if (!target) {
    return;
  }

  const apiUrl = target.dataset.apiUrl;
  const csrfToken = target.dataset.csrfToken || '';

  createApp(EnvironmentVariables, { apiUrl, csrfToken }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
