import { createApp } from 'vue';
import TestEnvironmentForm from './components/TestEnvironmentForm.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="test-environment-form"]');
  if (!target) {
    return;
  }

  const environmentId = target.dataset.environmentId ? parseInt(target.dataset.environmentId, 10) : null;
  const apiUrl = target.dataset.apiUrl;
  const cancelUrl = target.dataset.cancelUrl || '/admin/test-environments';

  createApp(TestEnvironmentForm, { environmentId, apiUrl, cancelUrl }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
