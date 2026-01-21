import { createApp } from 'vue';
import ProfileNotificationsForm from './components/ProfileNotificationsForm.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="profile-notifications"]');
  if (!target) {
    return;
  }

  const apiUrl = target.dataset.apiUrl;
  const environmentsUrl = target.dataset.environmentsUrl;
  const cancelUrl = target.dataset.cancelUrl || '/admin/dashboard';
  const userEmail = target.dataset.userEmail;

  createApp(ProfileNotificationsForm, {
    apiUrl,
    environmentsUrl,
    cancelUrl,
    userEmail,
  }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
