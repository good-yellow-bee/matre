import { createApp } from 'vue';
import TestPatternSelector from './components/TestPatternSelector.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="test-pattern-selector"]');
  if (!target) {
    return;
  }

  const typeFieldId = target.dataset.typeFieldId;
  const patternFieldId = target.dataset.patternFieldId;
  const apiUrl = target.dataset.apiUrl;
  const csrfToken = target.dataset.csrfToken || '';
  const initialValue = target.dataset.initialValue || '';

  createApp(TestPatternSelector, {
    typeFieldId,
    patternFieldId,
    apiUrl,
    csrfToken,
    initialValue,
  }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
