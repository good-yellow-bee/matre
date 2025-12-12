/**
 * Test Run Grid - Vue Island Entry Point
 *
 * Mounts the TestRunGrid component as a Vue island.
 */
import { createApp } from 'vue';
import TestRunGrid from './components/TestRunGrid.vue';

const initTestRunGrid = () => {
  const container = document.querySelector('[data-vue-island="test-run-grid"]');

  if (!container) {
    return;
  }

  const apiUrl = container.dataset.apiUrl;
  const csrfToken = container.dataset.csrfToken;

  if (!apiUrl) {
    console.error('TestRunGrid: Missing required data-api-url attribute');
    return;
  }

  const app = createApp(TestRunGrid, {
    apiUrl,
    csrfToken: csrfToken || '',
  });

  app.mount(container);
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTestRunGrid);
} else {
  initTestRunGrid();
}
