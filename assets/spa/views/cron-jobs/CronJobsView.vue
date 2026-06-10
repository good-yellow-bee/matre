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
          aria-label="Search cron jobs by name or command"
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
            @click="askToggle(row)"
          >
            {{ row.isActive ? 'Yes' : 'No' }}
          </button>
        </template>

        <template #cell-lastStatus="{ value }">
          <StatusBadge v-if="value" :status="value" />
          <span v-else class="badge border border-edge bg-panel-2 text-ink-mute">—</span>
        </template>

        <template #cell-lastRunAt="{ value }">
          <span class="text-xs text-ink-mute" :title="value ? new Date(value).toLocaleString() : ''">
            {{ relativeTime(value) }}
          </span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button class="btn-ghost btn-sm" title="Run now" @click="askRun(row)">
              <Play class="h-3.5 w-3.5" />
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
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Pencil, Play, Plus, Search, Trash2, X } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import Pagination from '../../components/ui/Pagination.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { confirm } from '../../composables/useConfirm';
import { debounce } from '../../utils/debounce';
import { relativeTime, truncate } from '../../utils/format';

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

const onSearchInput = debounce(() => {
  page.value = 1;
  fetchJobs();
}, 300);

function clearSearch() {
  onSearchInput.cancel();
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

function askToggle(job) {
  confirm({
    title: job.isActive ? 'Deactivate Cron Job' : 'Activate Cron Job',
    message: job.isActive
      ? `Deactivate “${job.name}”? It will no longer run on schedule.`
      : `Activate “${job.name}”? It will run on its schedule.`,
    confirmLabel: job.isActive ? 'Deactivate' : 'Activate',
    action: async () => {
      const data = await api.post(`/api/cron-jobs/${job.id}/toggle-active`);
      job.isActive = data.isActive;
      toasts.success(data.message);
    },
  });
}

function askRun(job) {
  confirm({
    title: 'Run Cron Job',
    message: `Run “${job.name}” now? The command will be dispatched to the queue immediately.`,
    confirmLabel: 'Run Now',
    action: async () => {
      const data = await api.post(`/api/cron-jobs/${job.id}/run`);
      toasts.success(data.message);
      refreshTimeout = setTimeout(fetchJobs, 1000);
    },
  });
}

function askDelete(job) {
  confirm({
    title: 'Delete Cron Job',
    message: `Are you sure you want to delete “${job.name}”? This action cannot be undone.`,
    confirmLabel: 'Delete Job',
    danger: true,
    action: async () => {
      const data = await api.delete(`/api/cron-jobs/${job.id}`);
      toasts.success(data.message);
      await fetchJobs();
    },
  });
}

onMounted(fetchJobs);
onUnmounted(() => {
  onSearchInput.cancel();
  clearTimeout(refreshTimeout);
});
</script>
