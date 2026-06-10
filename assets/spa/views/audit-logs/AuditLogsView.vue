<template>
  <div>
    <PageHeader title="Audit Logs" subtitle="History of admin changes across the platform" />

    <div class="card rise mb-4 p-4" style="--i: 1">
      <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
        <div>
          <label class="label" for="filter-entity-type">Entity Type</label>
          <select id="filter-entity-type" v-model="filters.entityType" class="input" @change="applyFilters">
            <option value="">All Types</option>
            <option v-for="type in filterOptions.entityTypes" :key="type" :value="type">{{ type }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="filter-action">Action</label>
          <select id="filter-action" v-model="filters.action" class="input" @change="applyFilters">
            <option value="">All Actions</option>
            <option v-for="action in filterOptions.actions" :key="action" :value="action">{{ action }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="filter-user">User</label>
          <select id="filter-user" v-model="filters.userId" class="input" @change="applyFilters">
            <option value="">All Users</option>
            <option v-for="user in filterOptions.users" :key="user.id" :value="user.id">{{ user.username }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="filter-date-from">From Date</label>
          <input
            id="filter-date-from"
            v-model="filters.dateFrom"
            type="date"
            class="input dark:[color-scheme:dark]"
            @change="applyFilters"
          >
        </div>
        <div>
          <label class="label" for="filter-date-to">To Date</label>
          <input
            id="filter-date-to"
            v-model="filters.dateTo"
            type="date"
            class="input dark:[color-scheme:dark]"
            @change="applyFilters"
          >
        </div>
        <div class="flex items-end">
          <button class="btn-ghost w-full" @click="resetFilters">
            <RotateCcw class="h-4 w-4" />
            Reset
          </button>
        </div>
      </div>
      <div class="relative mt-3 max-w-md">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" />
        <input
          v-model="search"
          type="text"
          class="input pl-9 pr-8"
          placeholder="Search by entity label..."
          @input="onSearchInput"
        >
        <button
          v-if="search"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer text-ink-faint hover:text-ink"
          @click="clearSearch"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </div>
    </div>

    <div
      v-if="loadError"
      class="rise card mb-4 flex items-center justify-between gap-3 border-fail/30 bg-fail/10 px-4 py-3 text-sm text-fail"
      style="--i: 2"
    >
      <span>{{ loadError }}</span>
      <button class="btn-ghost btn-sm" @click="fetchLogs">Try again</button>
    </div>

    <div class="rise" style="--i: 2">
      <DataTable
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :sort="sort"
        :order="order"
        row-key="id"
        clickable
        @sort="onSort"
        @row-click="openDetail"
      >
        <template #cell-createdAt="{ row }">
          <span class="whitespace-nowrap font-mono text-xs text-ink-mute">{{ formatTimestamp(row.createdAt) }}</span>
        </template>

        <template #cell-user="{ row }">
          <span v-if="row.user" class="text-sm text-ink">{{ row.user.username }}</span>
          <span v-else class="text-sm italic text-ink-faint">System</span>
        </template>

        <template #cell-action="{ row }">
          <span class="badge border" :class="actionClass(row.action)">{{ row.action }}</span>
        </template>

        <template #cell-entityType="{ row }">
          <span class="badge border border-edge-2 bg-panel-2 text-ink-mute">{{ row.entityType }}</span>
        </template>

        <template #cell-entity="{ row }">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-ink">{{ row.entityLabel || `#${row.entityId}` }}</span>
            <span v-if="row.entityLabel" class="mono-id text-ink-faint">#{{ row.entityId }}</span>
          </div>
        </template>

        <template #cell-changedFields="{ row }">
          <div v-if="row.changedFields?.length" class="flex max-w-60 flex-wrap gap-1">
            <span v-for="field in row.changedFields.slice(0, 3)" :key="field" class="badge border border-accent/25 bg-accent-soft text-accent">
              {{ field }}
            </span>
            <span v-if="row.changedFields.length > 3" class="badge border border-edge bg-panel-2 text-ink-faint">
              +{{ row.changedFields.length - 3 }}
            </span>
          </div>
          <span v-else class="text-ink-faint">—</span>
        </template>

        <template #empty>
          <EmptyState
            :title="hasActiveFilters ? 'No audit logs match your filters' : 'No audit logs yet'"
            :message="hasActiveFilters ? 'Try widening the date range or clearing filters.' : 'Admin changes will appear here as they happen.'"
          >
            <button v-if="hasActiveFilters" class="btn-ghost btn-sm mt-2" @click="resetFilters">
              <RotateCcw class="h-3.5 w-3.5" />
              Reset filters
            </button>
          </EmptyState>
        </template>

        <template #footer>
          <Pagination :page="page" :pages="pages" :total="total" @update:page="goToPage" />
        </template>
      </DataTable>
    </div>

    <AuditLogDetailModal :open="detailOpen" :log="detail" :loading="detailLoading" @close="detailOpen = false" />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { RotateCcw, Search, X } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import Pagination from '../../components/ui/Pagination.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import AuditLogDetailModal from './components/AuditLogDetailModal.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const ACTION_CLASSES = {
  create: 'border-pass/25 bg-pass/10 text-pass',
  update: 'border-run/25 bg-run/10 text-run',
  delete: 'border-fail/25 bg-fail/10 text-fail',
};

const toasts = useToastStore();

const rows = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = 20;
const loading = ref(false);
const loadError = ref('');
const sort = ref('createdAt');
const order = ref('desc');
const search = ref('');
const filters = reactive({ entityType: '', action: '', userId: '', dateFrom: '', dateTo: '' });
const filterOptions = ref({ entityTypes: [], actions: [], users: [] });

const detail = ref(null);
const detailOpen = ref(false);
const detailLoading = ref(false);

let searchTimer = null;

const columns = [
  { key: 'createdAt', label: 'Timestamp', sortable: true },
  { key: 'user', label: 'User' },
  { key: 'action', label: 'Action', sortable: true },
  { key: 'entityType', label: 'Entity Type', sortable: true },
  { key: 'entity', label: 'Entity' },
  { key: 'changedFields', label: 'Changed Fields' },
];

const pages = computed(() => Math.max(1, Math.ceil(total.value / perPage)));
const hasActiveFilters = computed(
  () => Boolean(filters.entityType || filters.action || filters.userId || filters.dateFrom || filters.dateTo || search.value),
);

function actionClass(action) {
  return ACTION_CLASSES[action] || 'border-edge bg-panel-2 text-ink-mute';
}

function formatTimestamp(value) {
  const date = new Date(value);
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

async function fetchLogs() {
  loading.value = true;
  loadError.value = '';
  try {
    const result = await api.get('/api/audit-logs/list', {
      params: {
        search: search.value,
        sort: sort.value,
        order: order.value,
        page: page.value,
        perPage,
        entityType: filters.entityType,
        action: filters.action,
        userId: filters.userId,
        dateFrom: filters.dateFrom,
        dateTo: filters.dateTo,
      },
    });
    rows.value = result.data;
    total.value = result.total;
    page.value = result.page;
  } catch (error) {
    loadError.value = error.message;
  } finally {
    loading.value = false;
  }
}

async function fetchFilterOptions() {
  try {
    filterOptions.value = await api.get('/api/audit-logs/filters');
  } catch (error) {
    toasts.error(`Unable to load filter options: ${error.message}`);
  }
}

function applyFilters() {
  page.value = 1;
  fetchLogs();
}

function resetFilters() {
  clearTimeout(searchTimer);
  Object.assign(filters, { entityType: '', action: '', userId: '', dateFrom: '', dateTo: '' });
  search.value = '';
  page.value = 1;
  fetchLogs();
}

function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    fetchLogs();
  }, 300);
}

function clearSearch() {
  clearTimeout(searchTimer);
  search.value = '';
  page.value = 1;
  fetchLogs();
}

function onSort(payload) {
  if (payload.sort !== sort.value) {
    sort.value = payload.sort;
    order.value = payload.sort === 'createdAt' ? 'desc' : 'asc';
  } else {
    order.value = payload.order;
  }
  page.value = 1;
  fetchLogs();
}

function goToPage(target) {
  if (target < 1 || target > pages.value) return;
  page.value = target;
  fetchLogs();
}

async function openDetail(row) {
  detailOpen.value = true;
  detailLoading.value = true;
  detail.value = null;
  try {
    detail.value = await api.get(`/api/audit-logs/${row.id}`);
  } catch (error) {
    detailOpen.value = false;
    toasts.error(error.message);
  } finally {
    detailLoading.value = false;
  }
}

onMounted(() => {
  fetchFilterOptions();
  fetchLogs();
});

onUnmounted(() => clearTimeout(searchTimer));
</script>
