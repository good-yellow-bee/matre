/**
 * Test ID Selector - Vue Island Entry Point
 *
 * Mounts the TestIdSelector component as a Vue island.
 */
import { createApp } from 'vue';
import TestIdSelector from './components/TestIdSelector.vue';

const initTestIdSelector = () => {
  const container = document.querySelector('[data-vue-island="test-id-selector"]');

  if (!container) {
    return;
  }

  const apiUrl = container.dataset.apiUrl;
  const fieldName = container.dataset.fieldName || 'testId';
  const initialValue = container.dataset.initialValue || '';
  const required = container.dataset.required === 'true';

  if (!apiUrl) {
    console.error('TestIdSelector: Missing required apiUrl attribute');
    return;
  }

  const app = createApp(TestIdSelector, {
    apiUrl,
    fieldName,
    initialValue,
    required,
  });

  app.mount(container);
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initTestIdSelector);
} else {
  initTestIdSelector();
}
