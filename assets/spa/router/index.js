import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { setUnauthorizedHandler } from '../api/client';
import AdminLayout from '../layouts/AdminLayout.vue';

const routes = [
  { path: '/', name: 'landing', component: () => import('../views/LandingView.vue'), meta: { public: true } },
  { path: '/login', name: 'login', component: () => import('../views/auth/LoginView.vue'), meta: { guest: true } },
  { path: '/2fa', name: 'two-factor', component: () => import('../views/auth/TwoFactorView.vue'), meta: { requires2fa: true } },
  { path: '/2fa-setup', name: 'two-factor-setup', component: () => import('../views/auth/TwoFactorSetupView.vue'), meta: { requiresAuth: true } },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('../views/dashboard/DashboardView.vue'), meta: { title: 'Dashboard', section: 'Platform' } },
      { path: 'test-runs', name: 'test-runs', component: () => import('../views/test-runs/TestRunsView.vue'), meta: { title: 'Test Runs', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-runs/new', name: 'test-run-new', component: () => import('../views/test-runs/TestRunNewView.vue'), meta: { title: 'Start Test Run', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-runs/:id(\\d+)', name: 'test-run-detail', component: () => import('../views/test-runs/TestRunDetailView.vue'), meta: { title: 'Test Run', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-history', name: 'test-history', component: () => import('../views/test-history/TestHistoryView.vue'), meta: { title: 'Test History', section: 'Test Automation' } },
      { path: 'test-environments', name: 'environments', component: () => import('../views/environments/EnvironmentsView.vue'), meta: { title: 'Environments', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-environments/new', name: 'environment-new', component: () => import('../views/environments/EnvironmentFormView.vue'), meta: { title: 'Add Environment', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-environments/:id(\\d+)', name: 'environment-detail', component: () => import('../views/environments/EnvironmentDetailView.vue'), meta: { title: 'Environment', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-environments/:id(\\d+)/edit', name: 'environment-edit', component: () => import('../views/environments/EnvironmentFormView.vue'), meta: { title: 'Edit Environment', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-suites', name: 'suites', component: () => import('../views/suites/SuitesView.vue'), meta: { title: 'Test Suites', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-suites/new', name: 'suite-new', component: () => import('../views/suites/SuiteFormView.vue'), meta: { title: 'Add Test Suite', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-suites/:id(\\d+)', name: 'suite-detail', component: () => import('../views/suites/SuiteDetailView.vue'), meta: { title: 'Test Suite', section: 'Test Automation', requiresAdmin: true } },
      { path: 'test-suites/:id(\\d+)/edit', name: 'suite-edit', component: () => import('../views/suites/SuiteFormView.vue'), meta: { title: 'Edit Test Suite', section: 'Test Automation', requiresAdmin: true } },
      { path: 'env-variables', name: 'env-variables', component: () => import('../views/env-variables/EnvVariablesView.vue'), meta: { title: 'Env Variables', section: 'Test Automation', requiresAdmin: true } },
      { path: 'users', name: 'users', component: () => import('../views/users/UsersView.vue'), meta: { title: 'Users', section: 'Administration', requiresAdmin: true } },
      { path: 'users/new', name: 'user-new', component: () => import('../views/users/UserFormView.vue'), meta: { title: 'Create User', section: 'Administration', requiresAdmin: true } },
      { path: 'users/:id(\\d+)', name: 'user-detail', component: () => import('../views/users/UserDetailView.vue'), meta: { title: 'User', section: 'Administration', requiresAdmin: true } },
      { path: 'users/:id(\\d+)/edit', name: 'user-edit', component: () => import('../views/users/UserFormView.vue'), meta: { title: 'Edit User', section: 'Administration', requiresAdmin: true } },
      { path: 'notification-templates', name: 'notification-templates', component: () => import('../views/templates/NotificationTemplatesView.vue'), meta: { title: 'Notification Templates', section: 'Administration', requiresAdmin: true } },
      { path: 'notification-templates/:id(\\d+)/edit', name: 'notification-template-edit', component: () => import('../views/templates/NotificationTemplateEditView.vue'), meta: { title: 'Edit Template', section: 'Administration', requiresAdmin: true } },
      { path: 'settings', name: 'settings', component: () => import('../views/settings/SettingsView.vue'), meta: { title: 'Settings', section: 'System', requiresAdmin: true } },
      { path: 'cron-jobs', name: 'cron-jobs', component: () => import('../views/cron-jobs/CronJobsView.vue'), meta: { title: 'Cron Jobs', section: 'System', requiresAdmin: true } },
      { path: 'cron-jobs/new', name: 'cron-job-new', component: () => import('../views/cron-jobs/CronJobFormView.vue'), meta: { title: 'Create Cron Job', section: 'System', requiresAdmin: true } },
      { path: 'cron-jobs/:id(\\d+)', name: 'cron-job-detail', component: () => import('../views/cron-jobs/CronJobDetailView.vue'), meta: { title: 'Cron Job', section: 'System', requiresAdmin: true } },
      { path: 'cron-jobs/:id(\\d+)/edit', name: 'cron-job-edit', component: () => import('../views/cron-jobs/CronJobFormView.vue'), meta: { title: 'Edit Cron Job', section: 'System', requiresAdmin: true } },
      { path: 'audit-logs', name: 'audit-logs', component: () => import('../views/audit-logs/AuditLogsView.vue'), meta: { title: 'Audit Logs', section: 'System', requiresAdmin: true } },
      { path: 'profile/notifications', name: 'profile-notifications', component: () => import('../views/profile/ProfileNotificationsView.vue'), meta: { title: 'Notification Settings', section: 'Profile' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/NotFoundView.vue'), meta: { public: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  try {
    await auth.bootstrap();
  } catch {
    if (!to.meta.public && !to.meta.guest) return { name: 'login' };
    return true;
  }

  if (to.meta.guest && auth.isAuthenticated) return { name: 'dashboard' };
  if (to.meta.requires2fa && !auth.twoFactorPending) {
    return auth.isAuthenticated ? { name: 'dashboard' } : { name: 'login' };
  }

  if (to.meta.requiresAuth) {
    if (auth.twoFactorPending) return { name: 'two-factor' };
    if (!auth.isAuthenticated) return { name: 'login', query: { redirect: to.fullPath } };
    if (auth.requires2faSetup && to.name !== 'two-factor-setup') return { name: 'two-factor-setup' };
    if (to.meta.requiresAdmin && !auth.isAdmin) return { name: 'dashboard' };
  }

  return true;
});

router.afterEach((to) => {
  const base = to.meta.title ? `${to.meta.title} · ` : '';
  document.title = `${base}MATRE`;
});

setUnauthorizedHandler(() => {
  const auth = useAuthStore();
  auth.reset();
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
  }
});

export default router;
