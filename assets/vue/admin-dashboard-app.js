import { createApp } from 'vue';
import EnvironmentStats from './components/EnvironmentStats.vue';

const mount = () => {
  // Mount Environment Stats widget
  const envStatsTarget = document.querySelector('[data-vue-island="environment-stats"]');
  if (envStatsTarget) {
    const apiUrl = envStatsTarget.dataset.apiUrl;
    const testRunBaseUrl = envStatsTarget.dataset.testRunBaseUrl;
    createApp(EnvironmentStats, { apiUrl, testRunBaseUrl }).mount(envStatsTarget);
  }
};

document.addEventListener('DOMContentLoaded', mount);
