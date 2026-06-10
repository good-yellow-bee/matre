<template>
  <div>
    <PageHeader title="Users" subtitle="Manage user accounts and permissions">
      <template #actions>
        <RouterLink :to="{ name: 'user-new' }" class="btn-primary">
          <UserPlus class="h-4 w-4" />
          Create User
        </RouterLink>
      </template>
    </PageHeader>

    <div class="rise mb-4 max-w-sm" style="--i: 1">
      <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" />
        <input
          v-model="search"
          class="input pl-9 pr-9"
          type="text"
          placeholder="Search by username or email…"
          aria-label="Search users by username or email"
          @input="onSearchInput"
        >
        <button
          v-if="search"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer rounded p-0.5 text-ink-faint hover:text-ink"
          title="Clear search"
          @click="clearSearch"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </div>

    <div class="rise" style="--i: 2">
      <DataTable
        :columns="columns"
        :rows="users"
        :loading="loading"
        :sort="sort"
        :order="order.toLowerCase()"
        clickable
        @sort="onSort"
        @row-click="(row) => router.push({ name: 'user-detail', params: { id: row.id } })"
      >
        <template #cell-username="{ row }">
          <RouterLink :to="{ name: 'user-detail', params: { id: row.id } }" class="mono-id link font-semibold" @click.stop>
            {{ row.username }}
          </RouterLink>
        </template>

        <template #cell-email="{ value }">
          <span class="text-ink-mute">{{ value }}</span>
        </template>

        <template #cell-roles="{ row }">
          <span v-for="role in row.roles" :key="role" class="badge mr-1 border" :class="roleBadgeClass(role)">
            {{ formatRole(role) }}
          </span>
        </template>

        <template #cell-isActive="{ value }">
          <span v-if="value" class="badge border border-pass/25 bg-pass/10 text-pass">Active</span>
          <span v-else class="badge border border-fail/25 bg-fail/10 text-fail">Inactive</span>
        </template>

        <template #cell-totpEnabled="{ value }">
          <ShieldCheck v-if="value" class="h-4 w-4 text-pass" title="Two-factor authentication enabled" />
          <span v-else class="text-ink-faint" title="Two-factor authentication not configured">—</span>
        </template>

        <template #cell-createdAt="{ value }">
          <span class="text-xs text-ink-mute">{{ formatDate(value) }}</span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1" @click.stop>
            <RouterLink :to="{ name: 'user-edit', params: { id: row.id } }" class="btn-ghost btn-sm" title="Edit user">
              <Pencil class="h-3.5 w-3.5" />
            </RouterLink>
            <template v-if="row.id !== auth.user?.id">
              <button
                class="btn-ghost btn-sm"
                :title="row.isActive ? 'Deactivate user' : 'Activate user'"
                @click="toggleTarget = row"
              >
                <Power class="h-3.5 w-3.5" :class="row.isActive ? 'text-run' : 'text-pass'" />
              </button>
              <button class="btn-ghost btn-sm" title="Delete user" @click="deleteTarget = row">
                <Trash2 class="h-3.5 w-3.5 text-fail" />
              </button>
            </template>
          </div>
        </template>

        <template #empty>
          <EmptyState
            :title="search ? 'No users match your search' : 'No users yet'"
            :message="search ? 'Try a different username or email.' : 'Create the first user account to get started.'"
          >
            <RouterLink v-if="!search" :to="{ name: 'user-new' }" class="btn-primary btn-sm mt-2">
              <UserPlus class="h-3.5 w-3.5" />
              Create User
            </RouterLink>
          </EmptyState>
        </template>

        <template #footer>
          <Pagination :page="meta.page" :pages="meta.pages" :total="meta.total" @update:page="goToPage" />
        </template>
      </DataTable>
    </div>

    <ConfirmDialog
      :open="!!deleteTarget"
      title="Delete user"
      :message="`Are you sure you want to delete user “${deleteTarget?.username}”? This action cannot be undone.`"
      confirm-label="Delete User"
      danger
      :busy="mutating"
      @confirm="deleteUser"
      @cancel="deleteTarget = null"
    />

    <ConfirmDialog
      :open="!!toggleTarget"
      :title="toggleTarget?.isActive ? 'Deactivate user' : 'Activate user'"
      :message="toggleTarget?.isActive
        ? `Deactivate “${toggleTarget?.username}”? They will no longer be able to log in.`
        : `Activate “${toggleTarget?.username}”? They will be able to log in again.`"
      :confirm-label="toggleTarget?.isActive ? 'Deactivate' : 'Activate'"
      :danger="!!toggleTarget?.isActive"
      :busy="mutating"
      @confirm="toggleActive"
      @cancel="toggleTarget = null"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Pencil, Power, Search, ShieldCheck, Trash2, UserPlus, X } from 'lucide-vue-next';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import DataTable from '../../components/ui/DataTable.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import Pagination from '../../components/ui/Pagination.vue';
import { api } from '../../api/client';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toasts';

const router = useRouter();
const auth = useAuthStore();
const toasts = useToastStore();

const columns = [
  { key: 'username', label: 'Username', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'roles', label: 'Roles' },
  { key: 'isActive', label: 'Status', sortable: true },
  { key: 'totpEnabled', label: '2FA' },
  { key: 'createdAt', label: 'Created', sortable: true },
  { key: 'actions', label: '', cellClass: 'text-right' },
];

const users = ref([]);
const meta = ref({ page: 1, pages: 1, total: 0 });
const loading = ref(false);
const search = ref('');
const sort = ref('createdAt');
const order = ref('DESC');
const page = ref(1);

const deleteTarget = ref(null);
const toggleTarget = ref(null);
const mutating = ref(false);

let searchTimeout = null;

async function fetchUsers() {
  loading.value = true;
  try {
    const data = await api.get('/api/users', {
      params: { page: page.value, limit: 10, sort: sort.value, order: order.value, q: search.value },
    });
    users.value = data.items;
    meta.value = data.meta;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    page.value = 1;
    fetchUsers();
  }, 300);
}

function clearSearch() {
  search.value = '';
  page.value = 1;
  fetchUsers();
}

function onSort({ sort: field, order: direction }) {
  sort.value = field;
  order.value = direction.toUpperCase();
  fetchUsers();
}

function goToPage(target) {
  page.value = target;
  fetchUsers();
}

async function deleteUser() {
  mutating.value = true;
  try {
    const result = await api.delete(`/api/users/${deleteTarget.value.id}`);
    toasts.success(result.message);
    deleteTarget.value = null;
    await fetchUsers();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    mutating.value = false;
  }
}

async function toggleActive() {
  mutating.value = true;
  try {
    const result = await api.post(`/api/users/${toggleTarget.value.id}/toggle-active`);
    toasts.success(result.message);
    toggleTarget.value = null;
    await fetchUsers();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    mutating.value = false;
  }
}

function formatRole(role) {
  return role.replace('ROLE_', '').replace('_', ' ');
}

function roleBadgeClass(role) {
  return role === 'ROLE_ADMIN'
    ? 'border-accent/25 bg-accent-soft text-accent'
    : 'border-edge bg-panel-2 text-ink-mute';
}

function formatDate(value) {
  return new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

onMounted(fetchUsers);
</script>
