/**
 * Test History - Vue Island Entry Point
 *
 * Mounts the TestHistory component as a Vue island.
 */
import { createApp } from 'vue';
import TestHistory from './components/TestHistory.vue';

const initTestHistory = () => {
  const container = document.querySelector('[data-vue-island="test-history"]');

  if (!container) {
    return;
  }

  const apiUrl = container.dataset.apiUrl;
  const testId = container.dataset.testId;
  const environmentId = container.dataset.environmentId;
  const testRunBaseUrl = container.dataset.testRunBaseUrl;

  if (!apiUrl || !testId || !environmentId) {
    console.error('TestHistory: Missing required data attributes');
    return;
  }

  const app = createApp(TestHistory, {
    apiUrl,
    testId,
    environmentId: parseInt(environmentId, 10),
    testRunBaseUrl,
  });

  app.mount(container);
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTestHistory);
} else {
  initTestHistory();
}
