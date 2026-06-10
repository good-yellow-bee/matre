<template>
  <div>
    <PageHeader title="Test History">
      <template #subtitle>
        <template v-if="searched && activeTestId">
          Test <code class="rounded bg-panel-2 px-1.5 py-0.5 font-mono text-xs">{{ activeTestId }}</code>
          on {{ activeEnvironmentName }}
        </template>
        <template v-else>View execution history for a specific test</template>
      </template>
    </PageHeader>

    <form class="rise card mb-4 p-4" style="--i: 1" @submit.prevent="submitSearch">
      <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1">
          <label class="label">Test ID</label>
          <TestIdSelector v-model="selectedTestId" :items="testIds" :loading="loadingTestIds" />
        </div>
        <div class="min-w-48 flex-1">
          <label class="label" for="history-environment">Environment</label>
          <select id="history-environment" v-model="selectedEnvironmentId" class="input" :disabled="loadingEnvironments">
            <option :value="null">{{ loadingEnvironments ? 'Loading…' : 'Select environment…' }}</option>
            <option v-for="env in environments" :key="env.id" :value="env.id">{{ env.name }}</option>
          </select>
        </div>
        <button type="submit" class="btn-primary" :disabled="!selectedTestId || !selectedEnvironmentId || loading">
          <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
          <Search v-else class="h-4 w-4" />
          Search
        </button>
      </div>
    </form>

    <div v-if="loading" class="rise card divide-y divide-edge" style="--i: 2">
      <div v-for="i in 5" :key="i" class="flex items-center gap-4 p-4">
        <div class="skeleton h-9 w-9 rounded-full"></div>
        <div class="flex-1 space-y-2">
          <div class="skeleton h-4" :style="{ width: `${35 + ((i * 13) % 30)}%` }"></div>
          <div class="skeleton h-3" :style="{ width: `${20 + ((i * 7) % 25)}%` }"></div>
        </div>
      </div>
    </div>

    <div v-else-if="error" class="rise card flex items-start gap-3 border-fail/30 p-4 text-sm text-fail" style="--i: 2">
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      <div class="flex-1">{{ error }}</div>
      <button class="btn-ghost btn-sm" @click="fetchHistory">Try Again</button>
    </div>

    <template v-else-if="searched">
      <div v-if="history.length" class="rise card overflow-hidden" style="--i: 2">
        <header class="flex items-center justify-between border-b border-edge px-5 py-3.5">
          <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-ink">
            <Clock class="h-4 w-4 text-accent" />
            Last {{ history.length }} Executions
          </h2>
          <span class="font-mono text-xs text-ink-faint">{{ activeEnvironmentName }}</span>
        </header>

        <div class="max-h-[600px] divide-y divide-edge overflow-y-auto">
          <article v-for="result in history" :key="result.id" class="flex items-start gap-3 p-4 transition-colors hover:bg-panel-2/40">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border" :class="statusCircleClass(result.status)">
              <component :is="statusIcon(result.status)" class="h-4 w-4" />
            </span>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <StatusBadge :status="result.status" />
                  <span class="text-xs text-ink-mute">{{ formatDate(result.testRun.startedAt) }}</span>
                </div>
                <div class="flex items-center gap-2.5">
                  <span v-if="result.duration" class="inline-flex items-center gap-1 font-mono text-xs text-ink-mute">
                    <Timer class="h-3.5 w-3.5" />
                    {{ result.durationFormatted }}
                  </span>
                  <RouterLink
                    :to="{ name: 'test-run-detail', params: { id: result.testRun.id } }"
                    class="btn-ghost btn-sm"
                    title="View full test run"
                  >
                    <ExternalLink class="h-3 w-3" />
                    Run #{{ result.testRun.id }}
                  </RouterLink>
                </div>
              </div>

              <div v-if="result.errorMessage" class="mt-2">
                <button class="btn-ghost btn-sm text-fail" @click="toggleError(result.id)">
                  <Bug class="h-3 w-3" />
                  {{ expandedErrors.has(result.id) ? 'Hide' : 'Show' }} Error
                </button>
                <pre
                  v-if="expandedErrors.has(result.id)"
                  class="mt-2 max-h-52 overflow-y-auto rounded-lg border border-fail/25 bg-fail/5 p-3 font-mono text-xs whitespace-pre-wrap break-words text-fail"
                >{{ result.errorMessage }}</pre>
              </div>

              <div class="mt-1.5 flex items-center gap-1 text-xs text-ink-faint">
                <PlayCircle class="h-3 w-3" />
                Triggered by {{ result.testRun.triggeredBy }}
              </div>
            </div>
          </article>
        </div>

        <footer class="grid grid-cols-5 gap-3 border-t border-edge bg-panel-2/40 p-4 text-center">
          <div>
            <div class="text-2xl font-bold text-pass">{{ stats.passed }}</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Passed</div>
          </div>
          <div>
            <div class="text-2xl font-bold text-fail">{{ stats.failed }}</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Failed</div>
          </div>
          <div>
            <div class="text-2xl font-bold text-broken">{{ stats.broken }}</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Broken</div>
          </div>
          <div>
            <div class="text-2xl font-bold text-skip">{{ stats.skipped }}</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Skipped</div>
          </div>
          <div>
            <div class="text-2xl font-bold text-accent">{{ passRate }}%</div>
            <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-faint">Pass Rate</div>
          </div>
        </footer>
      </div>

      <div v-else class="rise card p-14" style="--i: 2">
        <EmptyState title="No execution history" message="No execution history found for this test on the selected environment." />
      </div>
    </template>

    <p v-else class="rise flex items-start gap-2 text-sm text-ink-mute" style="--i: 2">
      <Info class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
      Pick a test ID and environment above — or use the <strong class="font-semibold">History</strong> button from a test
      result row in any test run.
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  AlertCircle, AlertTriangle, Bug, CheckCircle2, Clock, ExternalLink, HelpCircle, Info, Loader2,
  MinusCircle, PlayCircle, Search, Timer, XCircle,
} from 'lucide-vue-next';
import { api } from '../../api/client';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import TestIdSelector from './components/TestIdSelector.vue';
import { formatDate } from '../../utils/format';

const route = useRoute();
const router = useRouter();

const testIds = ref([]);
const environments = ref([]);
const loadingTestIds = ref(false);
const loadingEnvironments = ref(false);

const selectedTestId = ref('');
const selectedEnvironmentId = ref(null);

const history = ref([]);
const meta = ref({});
const loading = ref(false);
const error = ref(null);
const searched = ref(false);
const activeTestId = ref('');
const activeEnvironmentId = ref(null);
const expandedErrors = ref(new Set());

const activeEnvironmentName = computed(
  () => meta.value.environmentName || environments.value.find((env) => env.id === activeEnvironmentId.value)?.name || '',
);

const stats = computed(() => {
  const counts = { passed: 0, failed: 0, broken: 0, skipped: 0 };
  history.value.forEach((result) => {
    if (counts[result.status] !== undefined) counts[result.status]++;
  });
  return counts;
});

const passRate = computed(() => {
  if (!history.value.length) return 0;
  return Math.round((stats.value.passed / history.value.length) * 100);
});

function statusIcon(status) {
  return {
    passed: CheckCircle2,
    failed: XCircle,
    broken: AlertTriangle,
    skipped: MinusCircle,
  }[status] || HelpCircle;
}

function statusCircleClass(status) {
  return {
    passed: 'border-pass/25 bg-pass/10 text-pass',
    failed: 'border-fail/25 bg-fail/10 text-fail',
    broken: 'border-broken/25 bg-broken/10 text-broken',
    skipped: 'border-skip/25 bg-skip/10 text-skip',
  }[status] || 'border-edge bg-panel-2 text-ink-faint';
}

function toggleError(resultId) {
  const next = new Set(expandedErrors.value);
  if (next.has(resultId)) next.delete(resultId);
  else next.add(resultId);
  expandedErrors.value = next;
}

async function fetchHistory() {
  if (!activeTestId.value || !activeEnvironmentId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const data = await api.get('/api/test-history', {
      params: { testId: activeTestId.value, environmentId: activeEnvironmentId.value },
    });
    history.value = data.data;
    meta.value = data.meta;
    expandedErrors.value = new Set();
    searched.value = true;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

function submitSearch() {
  if (!selectedTestId.value || !selectedEnvironmentId.value) return;
  const query = { testId: selectedTestId.value, environmentId: String(selectedEnvironmentId.value) };
  if (route.query.testId === query.testId && route.query.environmentId === query.environmentId) {
    fetchHistory();
  } else {
    router.push({ name: 'test-history', query });
  }
}

function syncFromQuery() {
  const testId = typeof route.query.testId === 'string' ? route.query.testId : '';
  const environmentId = Number(route.query.environmentId) || null;
  if (testId && environmentId) {
    selectedTestId.value = testId;
    selectedEnvironmentId.value = environmentId;
    activeTestId.value = testId;
    activeEnvironmentId.value = environmentId;
    fetchHistory();
  }
}

watch(() => route.query, () => {
  if (route.name === 'test-history') syncFromQuery();
});

onMounted(async () => {
  loadingTestIds.value = true;
  loadingEnvironments.value = true;
  try {
    const [idsResponse, envResponse] = await Promise.all([
      api.get('/api/test-history/test-ids'),
      api.get('/api/test-suites/environments'),
    ]);
    testIds.value = idsResponse.data || [];
    environments.value = envResponse;
  } catch (e) {
    error.value = e.message;
  } finally {
    loadingTestIds.value = false;
    loadingEnvironments.value = false;
  }

  syncFromQuery();
});
</script>
