<template>
  <div>
    <PageHeader title="Test Runs" subtitle="Execution queue and history across all environments">
      <template #actions>
        <RouterLink :to="{ name: 'test-run-new' }" class="btn-primary">
          <Play class="h-4 w-4" />
          Start New Run
        </RouterLink>
      </template>
    </PageHeader>

    <div class="rise card mb-4 flex flex-wrap items-end gap-3 p-4" style="--i: 1">
      <div class="w-40">
        <label class="label" for="filter-status">Status</label>
        <select id="filter-status" v-model="filters.status" class="input" @change="applyFilters">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="running">Running</option>
          <option value="completed">Completed</option>
          <option value="failed">Failed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="w-40">
        <label class="label" for="filter-type">Type</label>
        <select id="filter-type" v-model="filters.type" class="input" @change="applyFilters">
          <option value="">All Types</option>
          <option value="mftf">MFTF</option>
          <option value="playwright">Playwright</option>
          <option value="both">Both</option>
        </select>
      </div>
      <div class="w-52">
        <label class="label" for="filter-suite">Suite</label>
        <select id="filter-suite" v-model="filters.suite" class="input" @change="applyFilters">
          <option value="">All Suites</option>
          <option v-for="suite in suites" :key="suite.id" :value="suite.id">{{ suite.name }}</option>
        </select>
      </div>
      <button v-if="hasFilters" class="btn-ghost btn-sm mb-0.5" @click="clearFilters">
        <X class="h-3.5 w-3.5" />
        Clear
      </button>
      <div class="ms-auto flex items-center gap-3 self-end pb-1">
        <span
          v-if="polling"
          class="inline-flex items-center gap-1.5 font-mono text-[11px] font-bold uppercase tracking-widest text-run"
          title="Auto-refreshing every 5 seconds while runs are active"
        >
          <span class="led led-pulse"></span>
          Live
        </span>
        <button class="btn-ghost btn-sm" :disabled="loading" @click="load()">
          <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
      </div>
    </div>

    <div class="rise" style="--i: 2">
      <DataTable
        :columns="columns"
        :rows="rows"
        :loading="loading"
        clickable
        @row-click="(row) => router.push({ name: 'test-run-detail', params: { id: row.id } })"
      >
        <template #cell-id="{ row }">
          <RouterLink :to="{ name: 'test-run-detail', params: { id: row.id } }" class="mono-id link" @click.stop>
            #{{ row.id }}
          </RouterLink>
        </template>

        <template #cell-environment="{ row }">
          <span class="font-medium">{{ row.environment.name }}</span>
        </template>

        <template #cell-type="{ row }">
          <span class="badge border" :class="typeBadgeClass(row.type)">{{ row.type }}</span>
        </template>

        <template #cell-suite="{ row }">
          <RouterLink
            v-if="row.suite"
            :to="{ name: 'suite-detail', params: { id: row.suite.id } }"
            class="link"
            @click.stop
          >
            {{ row.suite.name }}
          </RouterLink>
          <span v-else class="text-ink-faint">—</span>
        </template>

        <template #cell-testFilter="{ row }">
          <code v-if="row.testFilter" class="font-mono text-xs text-ink-mute" :title="row.testFilter">
            {{ truncate(row.testFilter, 30) }}
          </code>
          <span v-else class="text-ink-faint">—</span>
        </template>

        <template #cell-status="{ row }">
          <StatusBadge :status="row.status" />
        </template>

        <template #cell-results="{ row }">
          <div v-if="row.resultCounts.total > 0" class="leading-tight">
            <span class="font-mono text-xs">
              <span class="font-semibold text-pass">{{ row.resultCounts.passed }}</span>
              <span class="text-ink-faint"> / </span>
              <span class="text-fail" :class="{ 'font-semibold': row.resultCounts.failed > 0 }">{{ row.resultCounts.failed }}</span>
              <span class="text-ink-faint"> / </span>
              <span class="text-broken">{{ row.resultCounts.broken }}</span>
              <span class="text-ink-faint"> / </span>
              <span class="text-skip">{{ row.resultCounts.skipped }}</span>
            </span>
            <div class="text-[11px] text-ink-faint">
              {{ Math.round((row.resultCounts.passed / row.resultCounts.total) * 100) }}% pass
            </div>
          </div>
          <span v-else class="text-ink-faint">—</span>
        </template>

        <template #cell-createdAt="{ row }">
          <span class="text-xs text-ink-mute" :title="formatDateTime(row.startedAt || row.createdAt)">
            {{ relativeTime(row.createdAt) }}
          </span>
        </template>

        <template #cell-duration="{ row }">
          <span class="font-mono text-xs text-ink-mute">{{ row.duration || '—' }}</span>
        </template>

        <template #cell-triggeredBy="{ row }">
          <span class="text-xs text-ink-mute">{{ capitalize(row.triggeredBy) }}</span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex justify-end gap-1.5">
            <button
              v-if="row.canBeCancelled"
              class="btn-danger btn-sm"
              title="Cancel run"
              :disabled="actingOn === row.id"
              @click.stop="askCancel(row)"
            >
              <XCircle class="h-3.5 w-3.5" />
            </button>
            <button
              v-if="isTerminal(row.status)"
              class="btn-ghost btn-sm"
              title="Retry — new run with same configuration"
              :disabled="actingOn === row.id"
              @click.stop="askRetry(row)"
            >
              <RotateCcw class="h-3.5 w-3.5" />
            </button>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No test runs yet" message="Start your first run to see execution results here.">
            <RouterLink :to="{ name: 'test-run-new' }" class="btn-primary btn-sm mt-2">
              <Play class="h-3.5 w-3.5" />
              Start First Run
            </RouterLink>
          </EmptyState>
        </template>

        <template #footer>
          <Pagination :page="meta.page" :pages="meta.pages" :total="meta.total" @update:page="goToPage" />
        </template>
      </DataTable>
    </div>

    <ConfirmDialog
      :open="!!confirming"
      :title="confirming?.title || ''"
      :message="confirming?.message || ''"
      :confirm-label="confirming?.confirmLabel || 'Confirm'"
      :danger="confirming?.danger"
      :busy="!!actingOn"
      @confirm="executeConfirm"
      @cancel="confirming = null"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Play, RefreshCw, RotateCcw, X, XCircle } from 'lucide-vue-next';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import Pagination from '../../components/ui/Pagination.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import { capitalize, formatDateTime, relativeTime } from './utils/format';

const ACTIVE_STATUSES = ['pending', 'preparing', 'cloning', 'waiting', 'running', 'reporting'];
const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];
const POLL_INTERVAL = 5000;

const router = useRouter();
const toasts = useToastStore();

const columns = [
  { key: 'id', label: 'Run' },
  { key: 'environment', label: 'Environment' },
  { key: 'type', label: 'Type' },
  { key: 'suite', label: 'Suite' },
  { key: 'testFilter', label: 'Filter' },
  { key: 'status', label: 'Status' },
  { key: 'results', label: 'Results' },
  { key: 'createdAt', label: 'Created' },
  { key: 'duration', label: 'Duration' },
  { key: 'triggeredBy', label: 'Trigger' },
  { key: 'actions', label: '', headerClass: 'text-right', cellClass: 'text-right' },
];

const rows = ref([]);
const meta = ref({ page: 1, limit: 20, total: 0, pages: 0 });
const suites = ref([]);
const loading = ref(true);
const filters = reactive({ status: '', type: '', suite: '' });
const page = ref(1);
const confirming = ref(null);
const actingOn = ref(null);
let pollTimer = null;
let loadToken = 0;

const hasFilters = computed(() => filters.status || filters.type || filters.suite);

const polling = computed(
  () => rows.value.some((row) => ACTIVE_STATUSES.includes(row.status)) || ['pending', 'running'].includes(filters.status),
);

function isTerminal(status) {
  return TERMINAL_STATUSES.includes(status);
}

function typeBadgeClass(type) {
  if (type === 'mftf') return 'border-accent/25 bg-accent-soft text-accent';
  if (type === 'playwright') return 'border-run/25 bg-run/10 text-run';
  return 'border-broken/25 bg-broken/10 text-broken';
}

function truncate(value, length) {
  if (!value) return '';
  return value.length > length ? `${value.slice(0, length)}…` : value;
}

async function load({ background = false } = {}) {
  const token = ++loadToken;
  if (!background) loading.value = true;
  try {
    const data = await api.get('/api/test-runs', {
      params: { page: page.value, limit: meta.value.limit, status: filters.status, type: filters.type, suite: filters.suite },
    });
    if (token !== loadToken) return;
    rows.value = data.data;
    meta.value = data.meta;
  } catch (e) {
    if (token === loadToken && !background) toasts.error(e.message);
  } finally {
    if (token === loadToken) loading.value = false;
  }
}

function applyFilters() {
  page.value = 1;
  load();
}

function clearFilters() {
  filters.status = '';
  filters.type = '';
  filters.suite = '';
  applyFilters();
}

function goToPage(next) {
  page.value = next;
  load();
}

function askCancel(run) {
  confirming.value = {
    action: 'cancel',
    run,
    title: `Cancel run #${run.id}`,
    message: 'Cancel this test run?',
    confirmLabel: 'Cancel Run',
    danger: true,
  };
}

function askRetry(run) {
  confirming.value = {
    action: 'retry',
    run,
    title: `Retry run #${run.id}`,
    message: 'Create a new run with the same configuration?',
    confirmLabel: 'Retry',
  };
}

async function executeConfirm() {
  const { action, run } = confirming.value;
  actingOn.value = run.id;
  try {
    if (action === 'cancel') {
      await api.post(`/api/test-runs/${run.id}/cancel`);
      toasts.success(`Run #${run.id} cancelled`);
      confirming.value = null;
      await load({ background: true });
    } else {
      const data = await api.post(`/api/test-runs/${run.id}/retry`);
      toasts.success(`New run #${data.run.id} created`);
      confirming.value = null;
      router.push({ name: 'test-run-detail', params: { id: data.run.id } });
    }
  } catch (e) {
    toasts.error(e.message);
  } finally {
    actingOn.value = null;
  }
}

async function loadSuites() {
  try {
    suites.value = await api.get('/api/test-suites/list');
  } catch (e) {
    toasts.error(e.message);
  }
}

onMounted(() => {
  load();
  loadSuites();
  pollTimer = setInterval(() => {
    if (!document.hidden && polling.value) load({ background: true });
  }, POLL_INTERVAL);
});

onUnmounted(() => {
  clearInterval(pollTimer);
});
</script>
