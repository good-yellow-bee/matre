import { createApp } from 'vue';
import CronJobForm from './components/CronJobForm.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="cron-job-form"]');
  if (!target) {
    return;
  }

  const cronJobId = target.dataset.cronJobId ? parseInt(target.dataset.cronJobId, 10) : null;
  const apiUrl = target.dataset.apiUrl;
  const cancelUrl = target.dataset.cancelUrl || '/admin/cron-jobs';

  createApp(CronJobForm, { cronJobId, apiUrl, cancelUrl }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
