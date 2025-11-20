import { createApp } from 'vue';
import CmsFeedback from './components/CmsFeedback.vue';

const mountFeedback = () => {
  const target = document.querySelector('[data-vue-island="cms-feedback"]');
  if (!target) {
    return;
  }

  const siteName = target.dataset.siteName || 'ReSymf CMS';
  const pageSlug = target.dataset.pageSlug || '';

  createApp(CmsFeedback, { siteName, pageSlug }).mount(target);
};

document.addEventListener('DOMContentLoaded', () => {
  mountFeedback();
});
