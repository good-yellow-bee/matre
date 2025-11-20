import { createApp } from 'vue';
import DashboardHello from './components/DashboardHello.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="dashboard-hello"]');
  if (!target) {
    return;
  }

  const username = target.dataset.username || 'Admin';
  createApp(DashboardHello, { username }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
