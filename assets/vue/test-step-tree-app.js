/**
 * Test Step Tree - Vue Island Entry Point
 *
 * Mounts the TestStepTree component as a Vue island for displaying Allure-style execution steps.
 */
import { createApp } from 'vue';
import TestStepTree from './components/TestStepTree.vue';

const initTestStepTree = () => {
  const container = document.querySelector('[data-vue-island="test-step-tree"]');

  if (!container) {
    return;
  }

  const apiUrl = container.dataset.apiUrl;

  if (!apiUrl) {
    console.error('TestStepTree: Missing required data-api-url attribute');
    return;
  }

  const app = createApp(TestStepTree, {
    apiUrl,
  });

  app.mount(container);
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTestStepTree);
} else {
  initTestStepTree();
}

// Export for modal dynamic mounting
export { TestStepTree };

// Global function for modal-based mounting
window.mountTestStepTree = (element, apiUrl) => {
  const app = createApp(TestStepTree, { apiUrl });
  app.mount(element);
  return app;
};
