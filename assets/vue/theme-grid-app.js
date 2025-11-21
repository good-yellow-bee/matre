import { createApp } from 'vue';
import ThemeGrid from './components/ThemeGrid.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="theme-grid"]');
  if (!target) {
    return;
  }

  const apiUrl = target.dataset.apiUrl || '/api/themes/list';
  const csrfToken = target.dataset.csrfToken || '';

  createApp(ThemeGrid, { apiUrl, csrfToken }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
