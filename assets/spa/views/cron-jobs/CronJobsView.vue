<template>
  <div>
    <PageHeader title="Cron Jobs" subtitle="Scheduled console commands with status tracking">
      <template #actions>
        <RouterLink class="btn-primary" :to="{ name: 'cron-job-new' }">
          <Plus class="h-4 w-4" />
          Create Cron Job
        </RouterLink>
      </template>
    </PageHeader>

    <div class="rise mb-4 max-w-md" style="--i: 1">
      <div class="relative">
        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-faint" />
        <input
          v-model="search"
          class="input pl-9"
          type="text"
          placeholder="Search by name or command..."
          @input="onSearchInput"
        >
        <button
          v-if="search"
          class="absolute top-1/2 right-2.5 -translate-y-1/2 cursor-pointer rounded p-0.5 text-ink-faint hover:text-ink"
          @click="clearSearch"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </div>

    <div class="rise" style="--i: 2">
      <DataTable
        :columns="columns"
        :rows="jobs"
        :loading="loading"
        :sort="sort"
        :order="order"
        :empty-title="search ? 'No matches' : 'No cron jobs yet'"
        :empty-message="search ? 'No cron jobs found matching your search.' : 'Schedule a console command to run automatically.'"
        @sort="onSort"
      >
        <template #cell-id="{ value }">
          <span class="mono-id text-ink-faint">{{ value }}</span>
        </template>

        <template #cell-name="{ row }">
          <RouterLink class="link font-semibold" :to="{ name: 'cron-job-detail', params: { id: row.id } }">
            {{ row.name }}
          </RouterLink>
          <div v-if="row.description" class="mt-0.5 max-w-xs truncate text-xs text-ink-faint">{{ row.description }}</div>
        </template>

        <template #cell-command="{ value }">
          <code class="font-mono text-xs text-ink-mute">{{ truncate(value, 40) }}</code>
        </template>

        <template #cell-cronExpression="{ value }">
          <span class="kbd">{{ value }}</span>
        </template>

        <template #cell-isActive="{ row }">
          <button
            class="badge cursor-pointer border transition-colors"
            :class="row.isActive
              ? 'text-pass border-pass/25 bg-pass/10 hover:bg-pass/20'
              : 'text-skip border-skip/25 bg-skip/10 hover:bg-skip/20'"
            :title="row.isActive ? 'Click to deactivate' : 'Click to activate'"
            @click="toggleActive(row)"
          >
            {{ row.isActive ? 'Yes' : 'No' }}
          </button>
        </template>

        <template #cell-lastStatus="{ value }">
          <CronStatusBadge :status="value" />
        </template>

        <template #cell-lastRunAt="{ value }">
          <span class="text-xs text-ink-mute" :title="value ? new Date(value).toLocaleString() : ''">
            {{ relativeTime(value) }}
          </span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button
              class="btn-ghost btn-sm"
              title="Run now"
              :disabled="runningId === row.id"
              @click="askRun(row)"
            >
              <Loader2 v-if="runningId === row.id" class="h-3.5 w-3.5 animate-spin" />
              <Play v-else class="h-3.5 w-3.5" />
            </button>
            <RouterLink class="btn-ghost btn-sm" title="Edit job" :to="{ name: 'cron-job-edit', params: { id: row.id } }">
              <Pencil class="h-3.5 w-3.5" />
            </RouterLink>
            <button class="btn-danger btn-sm" title="Delete job" @click="askDelete(row)">
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </template>

        <template #footer>
          <Pagination :page="page" :pages="pages" :total="total" @update:page="goToPage" />
        </template>
      </DataTable>
    </div>

    <ConfirmDialog
      :open="!!jobToRun"
      title="Run Cron Job"
      :message="`Run “${jobToRun?.name}” now? The command will be dispatched to the queue immediately.`"
      confirm-label="Run Now"
      :busy="runBusy"
      @confirm="confirmRun"
      @cancel="jobToRun = null"
    />

    <ConfirmDialog
      :open="!!jobToDelete"
      title="Delete Cron Job"
      :message="`Are you sure you want to delete “${jobToDelete?.name}”? This action cannot be undone.`"
      confirm-label="Delete Job"
      danger
      :busy="deleteBusy"
      @confirm="confirmDelete"
      @cancel="jobToDelete = null"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Loader2, Pencil, Play, Plus, Search, Trash2, X } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import Pagination from '../../components/ui/Pagination.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import CronStatusBadge from './components/CronStatusBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const toasts = useToastStore();

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'command', label: 'Command', sortable: true },
  { key: 'cronExpression', label: 'Schedule', sortable: true },
  { key: 'isActive', label: 'Active', sortable: true },
  { key: 'lastStatus', label: 'Status', sortable: true },
  { key: 'lastRunAt', label: 'Last Run', sortable: true },
  { key: 'actions', label: '', headerClass: 'text-right', cellClass: 'text-right' },
];

const jobs = ref([]);
const loading = ref(true);
const search = ref('');
const sort = ref('name');
const order = ref('asc');
const page = ref(1);
const perPage = 20;
const total = ref(0);
const pages = computed(() => Math.max(1, Math.ceil(total.value / perPage)));

const runningId = ref(null);
const jobToRun = ref(null);
const runBusy = ref(false);
const jobToDelete = ref(null);
const deleteBusy = ref(false);

let searchTimeout = null;
let refreshTimeout = null;

async function fetchJobs() {
  loading.value = true;
  try {
    const data = await api.get('/api/cron-jobs/list', {
      params: { search: search.value, sort: sort.value, order: order.value, page: page.value, perPage },
    });
    jobs.value = data.data;
    total.value = data.total;
    page.value = data.page;
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
    fetchJobs();
  }, 300);
}

function clearSearch() {
  search.value = '';
  page.value = 1;
  fetchJobs();
}

function onSort({ sort: field, order: direction }) {
  sort.value = field;
  order.value = direction;
  page.value = 1;
  fetchJobs();
}

function goToPage(value) {
  page.value = value;
  fetchJobs();
}

async function toggleActive(job) {
  try {
    const data = await api.post(`/api/cron-jobs/${job.id}/toggle-active`);
    job.isActive = data.isActive;
    toasts.success(data.message);
  } catch (e) {
    toasts.error(e.message);
  }
}

function askRun(job) {
  jobToRun.value = job;
}

async function confirmRun() {
  const job = jobToRun.value;
  runBusy.value = true;
  runningId.value = job.id;
  try {
    const data = await api.post(`/api/cron-jobs/${job.id}/run`);
    toasts.success(data.message);
    jobToRun.value = null;
    refreshTimeout = setTimeout(fetchJobs, 1000);
  } catch (e) {
    toasts.error(e.message);
  } finally {
    runBusy.value = false;
    runningId.value = null;
  }
}

function askDelete(job) {
  jobToDelete.value = job;
}

async function confirmDelete() {
  deleteBusy.value = true;
  try {
    const data = await api.delete(`/api/cron-jobs/${jobToDelete.value.id}`);
    toasts.success(data.message);
    jobToDelete.value = null;
    await fetchJobs();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    deleteBusy.value = false;
  }
}

function truncate(value, length) {
  if (!value) return '';
  return value.length > length ? `${value.slice(0, length)}...` : value;
}

function relativeTime(iso) {
  if (!iso) return '—';
  const diffMinutes = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
  if (diffMinutes < 1) return 'just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  const hours = Math.floor(diffMinutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days}d ago`;
  return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

onMounted(fetchJobs);
onUnmounted(() => {
  clearTimeout(searchTimeout);
  clearTimeout(refreshTimeout);
});
</script>
