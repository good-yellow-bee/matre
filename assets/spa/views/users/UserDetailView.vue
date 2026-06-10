<template>
  <div>
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-9 w-64"></div>
      <div class="skeleton h-72 w-full"></div>
    </div>

    <EmptyState v-else-if="!user" title="User not found" message="This user does not exist or has been deleted.">
      <RouterLink :to="{ name: 'users' }" class="btn-ghost btn-sm mt-2">
        <ArrowLeft class="h-3.5 w-3.5" />
        Back to Users
      </RouterLink>
    </EmptyState>

    <template v-else>
      <PageHeader :subtitle="user.email">
        <template #title>
          <span class="font-mono">{{ user.username }}</span>
        </template>
        <template #actions>
          <RouterLink :to="{ name: 'users' }" class="btn-ghost">
            <ArrowLeft class="h-4 w-4" />
            Back
          </RouterLink>
          <RouterLink :to="{ name: 'user-edit', params: { id: user.id } }" class="btn-primary">
            <Pencil class="h-4 w-4" />
            Edit
          </RouterLink>
        </template>
      </PageHeader>

      <div class="grid items-start gap-6 lg:grid-cols-3">
        <section class="card rise lg:col-span-2" style="--i: 1">
          <h2 class="border-b border-edge px-5 py-3.5 text-sm font-bold uppercase tracking-wider text-ink">User Details</h2>
          <dl class="divide-y divide-edge">
            <div v-for="row in detailRows" :key="row.label" class="grid grid-cols-3 gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">{{ row.label }}</dt>
              <dd class="col-span-2 text-sm text-ink">
                <template v-if="row.key === 'roles'">
                  <span v-for="role in user.roles" :key="role" class="badge mr-1 border" :class="roleBadgeClass(role)">
                    {{ formatRole(role) }}
                  </span>
                </template>
                <template v-else-if="row.key === 'status'">
                  <span v-if="user.isActive" class="badge border border-pass/25 bg-pass/10 text-pass">Active</span>
                  <span v-else class="badge border border-fail/25 bg-fail/10 text-fail">Inactive</span>
                </template>
                <span v-else :class="row.mono ? 'mono-id' : ''">{{ row.value }}</span>
              </dd>
            </div>
          </dl>
        </section>

        <div class="space-y-6">
          <section class="card rise p-5" style="--i: 2">
            <h2 class="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-ink">
              <ShieldCheck class="h-4 w-4 text-accent" />
              Two-Factor Authentication
            </h2>
            <div class="mb-3 flex items-center gap-2 text-sm text-ink-mute">
              Status:
              <span v-if="user.totpEnabled" class="badge border border-pass/25 bg-pass/10 text-pass">Enabled</span>
              <span v-else class="badge border border-edge bg-panel-2 text-ink-mute">Not configured</span>
            </div>
            <template v-if="user.totpEnabled">
              <p class="mb-3 text-xs text-ink-faint">User has configured an authenticator app for 2FA verification.</p>
              <button class="btn-danger w-full" @click="askReset2fa">
                <RotateCcw class="h-4 w-4" />
                Reset 2FA
              </button>
            </template>
            <p v-else class="text-xs text-ink-faint">User has not set up two-factor authentication yet.</p>
          </section>

          <section class="card rise p-5" style="--i: 3">
            <h2 class="mb-4 text-sm font-bold uppercase tracking-wider text-ink">Actions</h2>
            <template v-if="!isSelf">
              <button class="btn-ghost mb-2 w-full" @click="askToggle">
                <component :is="user.isActive ? PauseCircle : PlayCircle" class="h-4 w-4" :class="user.isActive ? 'text-run' : 'text-pass'" />
                {{ user.isActive ? 'Deactivate User' : 'Activate User' }}
              </button>
              <button class="btn-danger w-full" @click="askDelete">
                <Trash2 class="h-4 w-4" />
                Delete User
              </button>
            </template>
            <p v-else class="flex items-start gap-2 text-xs text-ink-faint">
              <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
              You cannot modify or delete your own account.
            </p>
          </section>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Info, PauseCircle, Pencil, PlayCircle, RotateCcw, ShieldCheck, Trash2 } from 'lucide-vue-next';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { api } from '../../api/client';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toasts';
import { confirm } from '../../composables/useConfirm';
import { formatDateTime } from '../../utils/format';
import { formatRole, roleBadgeClass } from './display.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toasts = useToastStore();

const user = ref(null);
const loading = ref(true);

const isSelf = computed(() => user.value?.id === auth.user?.id);

const detailRows = computed(() => [
  { key: 'id', label: 'ID', value: user.value.id, mono: true },
  { key: 'username', label: 'Username', value: user.value.username, mono: true },
  { key: 'email', label: 'Email', value: user.value.email },
  { key: 'roles', label: 'Roles' },
  { key: 'status', label: 'Status' },
  { key: 'created', label: 'Created', value: formatDateTime(user.value.createdAt) },
  { key: 'updated', label: 'Updated', value: user.value.updatedAt ? formatDateTime(user.value.updatedAt) : 'Never' },
]);

async function fetchUser() {
  loading.value = true;
  try {
    user.value = await api.get(`/api/users/${route.params.id}`);
  } catch (e) {
    user.value = null;
    if (e.status !== 404) toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

function askReset2fa() {
  confirm({
    title: 'Reset 2FA',
    message: 'Reset 2FA for this user? They will need to set up their authenticator app again.',
    confirmLabel: 'Reset 2FA',
    danger: true,
    action: async () => {
      const result = await api.post(`/api/users/${user.value.id}/reset-2fa`);
      toasts.success(result.message);
      await fetchUser();
    },
  });
}

function askToggle() {
  const { isActive, username } = user.value;
  confirm({
    title: isActive ? 'Deactivate user' : 'Activate user',
    message: isActive
      ? `Deactivate “${username}”? They will no longer be able to log in.`
      : `Activate “${username}”? They will be able to log in again.`,
    confirmLabel: isActive ? 'Deactivate' : 'Activate',
    danger: isActive,
    action: async () => {
      const result = await api.post(`/api/users/${user.value.id}/toggle-active`);
      toasts.success(result.message);
      await fetchUser();
    },
  });
}

function askDelete() {
  confirm({
    title: 'Delete user',
    message: `Are you sure you want to delete user “${user.value.username}”? This action cannot be undone.`,
    confirmLabel: 'Delete User',
    danger: true,
    action: async () => {
      const result = await api.delete(`/api/users/${user.value.id}`);
      toasts.success(result.message);
      router.push({ name: 'users' });
    },
  });
}

onMounted(fetchUser);
</script>
