import { createApp } from 'vue';
import NotificationTemplateForm from './components/NotificationTemplateForm.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="notification-template-form"]');
  if (!target) {
    return;
  }

  const templateId = parseInt(target.dataset.templateId, 10);
  const apiUrl = target.dataset.apiUrl;
  const channel = target.dataset.channel;
  const cancelUrl = target.dataset.cancelUrl || '/admin/notification-templates';

  createApp(NotificationTemplateForm, { templateId, apiUrl, channel, cancelUrl }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
