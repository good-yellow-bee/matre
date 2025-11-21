import { createApp } from 'vue';
import CategoryGrid from './components/CategoryGrid.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="category-grid"]');
  if (!target) {
    return;
  }

  const apiUrl = target.dataset.apiUrl || '/api/categories/list';
  const csrfToken = target.dataset.csrfToken || '';

  createApp(CategoryGrid, { apiUrl, csrfToken }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
