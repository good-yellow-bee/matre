import { createApp } from 'vue';
import TestRunForm from './components/TestRunForm.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="test-run-form"]');
  if (!target) {
    return;
  }

  const suitesUrl = target.dataset.suitesUrl;
  const formAction = target.dataset.formAction;
  const cancelUrl = target.dataset.cancelUrl || '/admin/test-runs';
  const csrfToken = target.dataset.csrfToken;

  createApp(TestRunForm, { suitesUrl, formAction, cancelUrl, csrfToken }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
