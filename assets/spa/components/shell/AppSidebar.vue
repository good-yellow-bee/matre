<template>
  <aside
    class="flex h-full shrink-0 flex-col border-r border-edge bg-panel transition-[width] duration-200"
    :class="collapsed ? 'w-[68px]' : 'w-64'"
  >
    <router-link :to="{ name: 'dashboard' }" class="flex h-14 items-center gap-3 border-b border-edge px-4">
      <BrandMark class="h-8 w-8 shrink-0" />
      <span v-if="!collapsed" class="truncate font-mono text-sm font-bold tracking-tight text-ink">
        {{ auth.settings.adminPanelTitle || 'MATRE' }}
      </span>
    </router-link>

    <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
      <div v-for="section in visibleSections" :key="section.label">
        <div v-if="!collapsed" class="mb-1.5 px-2 text-[10px] font-bold uppercase tracking-[0.18em] text-ink-faint">
          {{ section.label }}
        </div>
        <div class="space-y-0.5">
          <router-link
            v-for="item in section.items"
            :key="item.name"
            :to="{ name: item.name }"
            class="group flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium text-ink-mute transition-colors hover:bg-panel-2 hover:text-ink"
            :class="{ 'bg-accent-soft text-accent hover:bg-accent-soft hover:text-accent': isActive(item) }"
            :title="collapsed ? item.label : undefined"
          >
            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" :stroke-width="2" />
            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
          </router-link>
        </div>
      </div>
    </nav>

    <div class="border-t border-edge p-3">
      <div class="flex items-center gap-2.5" :class="collapsed ? 'justify-center' : ''">
        <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-soft font-mono text-xs font-bold text-accent">
          {{ initials }}
        </div>
        <template v-if="!collapsed">
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-semibold text-ink">{{ auth.user?.username }}</div>
            <div class="truncate text-[11px] text-ink-faint">{{ auth.user?.email }}</div>
          </div>
          <router-link
            :to="{ name: 'profile-notifications' }"
            class="rounded-md p-1.5 text-ink-faint transition-colors hover:bg-panel-2 hover:text-ink"
            title="Notification settings"
          >
            <Bell class="h-4 w-4" />
          </router-link>
          <button
            class="cursor-pointer rounded-md p-1.5 text-ink-faint transition-colors hover:bg-panel-2 hover:text-fail"
            title="Sign out"
            @click="logout"
          >
            <LogOut class="h-4 w-4" />
          </button>
        </template>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Bell, Braces, ClockArrowUp, Gauge, History, Layers, LogOut,
  MailCheck, PlayCircle, ScrollText, Server, Settings, Users,
} from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';
import BrandMark from './BrandMark.vue';

defineProps({ collapsed: { type: Boolean, default: false } });

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const sections = [
  {
    label: 'Platform',
    items: [{ name: 'dashboard', label: 'Dashboard', icon: Gauge, exact: true }],
  },
  {
    label: 'Test Automation',
    items: [
      { name: 'test-runs', label: 'Test Runs', icon: PlayCircle, admin: true },
      { name: 'test-history', label: 'Test History', icon: History },
      { name: 'environments', label: 'Environments', icon: Server, admin: true },
      { name: 'suites', label: 'Test Suites', icon: Layers, admin: true },
      { name: 'env-variables', label: 'Env Variables', icon: Braces, admin: true },
    ],
  },
  {
    label: 'Administration',
    items: [
      { name: 'users', label: 'Users', icon: Users, admin: true },
      { name: 'notification-templates', label: 'Notif. Templates', icon: MailCheck, admin: true },
    ],
  },
  {
    label: 'System',
    items: [
      { name: 'settings', label: 'Settings', icon: Settings, admin: true },
      { name: 'cron-jobs', label: 'Cron Jobs', icon: ClockArrowUp, admin: true },
      { name: 'audit-logs', label: 'Audit Logs', icon: ScrollText, admin: true },
    ],
  },
];

const visibleSections = computed(() =>
  sections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => !item.admin || auth.isAdmin),
    }))
    .filter((section) => section.items.length > 0),
);

function isActive(item) {
  if (item.exact) return route.name === item.name;
  const prefix = router.resolve({ name: item.name }).path;
  return route.path === prefix || route.path.startsWith(`${prefix}/`);
}

const initials = computed(() => (auth.user?.username || '?').slice(0, 2).toUpperCase());

async function logout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>
