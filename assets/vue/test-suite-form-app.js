import { createApp } from 'vue';
import TestSuiteForm from './components/TestSuiteForm.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="test-suite-form"]');
  if (!target) {
    return;
  }

  const suiteId = target.dataset.suiteId ? parseInt(target.dataset.suiteId, 10) : null;
  const apiUrl = target.dataset.apiUrl;
  const cancelUrl = target.dataset.cancelUrl || '/admin/test-suites';
  const testDiscoveryUrl = target.dataset.testDiscoveryUrl || '/api/test-discovery';
  const csrfToken = target.dataset.csrfToken || '';

  createApp(TestSuiteForm, {
    suiteId,
    apiUrl,
    cancelUrl,
    testDiscoveryUrl,
    csrfToken,
  }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
