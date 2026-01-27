import { createApp } from 'vue';
import AuditLogGrid from './components/AuditLogGrid.vue';

const mount = () => {
  const target = document.querySelector('[data-vue-island="audit-log-grid"]');
  if (!target) {
    return;
  }

  const apiUrl = target.dataset.apiUrl || '/api/audit-logs/list';
  const filtersUrl = target.dataset.filtersUrl || '/api/audit-logs/filters';

  createApp(AuditLogGrid, { apiUrl, filtersUrl }).mount(target);
};

document.addEventListener('DOMContentLoaded', mount);
