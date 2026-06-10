<template>
  <div>
    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-9 w-64"></div>
      <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
        <div class="space-y-4">
          <div class="skeleton h-64"></div>
          <div class="skeleton h-40"></div>
        </div>
        <div class="skeleton h-72"></div>
      </div>
    </div>

    <div v-else-if="notFound" class="card p-14">
      <EmptyState title="Test run not found" :message="`Test run #${runId} does not exist.`">
        <RouterLink :to="{ name: 'test-runs' }" class="btn-ghost btn-sm mt-2">
          <ArrowLeft class="h-3.5 w-3.5" />
          Back to Test Runs
        </RouterLink>
      </EmptyState>
    </div>

    <template v-else-if="run">
      <PageHeader :title="`Test Run #${run.id}`">
        <template #subtitle>
          {{ run.environment.name }} <span class="text-ink-faint">•</span> {{ run.type.toUpperCase() }}
          <span class="text-ink-faint">•</span> {{ capitalize(run.triggeredBy) }} trigger
        </template>
        <template #actions>
          <StatusBadge :status="run.status" />
        </template>
      </PageHeader>

      <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div class="min-w-0 space-y-4">
          <!-- Status card -->
          <section class="rise card" style="--i: 1">
            <header class="flex items-center justify-between border-b border-edge px-5 py-3.5">
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Run Status</h2>
              <StatusBadge :status="run.status" />
            </header>
            <dl class="grid gap-x-8 gap-y-2.5 p-5 text-sm sm:grid-cols-2">
              <div class="detail-row">
                <dt>Environment</dt>
                <dd>
                  <RouterLink :to="{ name: 'environment-detail', params: { id: run.environment.id } }" class="link">
                    {{ run.environment.name }}
                  </RouterLink>
                </dd>
              </div>
              <div class="detail-row">
                <dt>Type</dt>
                <dd><span class="badge border" :class="typeBadgeClass(run.type)">{{ run.type }}</span></dd>
              </div>
              <div v-if="run.suite" class="detail-row">
                <dt>Suite</dt>
                <dd>
                  <RouterLink :to="{ name: 'suite-detail', params: { id: run.suite.id } }" class="link">
                    {{ run.suite.name }}
                  </RouterLink>
                </dd>
              </div>
              <div v-if="run.testFilter" class="detail-row">
                <dt>Filter</dt>
                <dd><code class="rounded bg-panel-2 px-1.5 py-0.5 font-mono text-xs">{{ run.testFilter }}</code></dd>
              </div>
              <div class="detail-row">
                <dt>Triggered By</dt>
                <dd>{{ capitalize(run.triggeredBy) }}</dd>
              </div>
              <div class="detail-row">
                <dt>Executed By</dt>
                <dd>
                  <RouterLink v-if="run.executedBy" :to="{ name: 'user-detail', params: { id: run.executedBy.id } }" class="link">
                    {{ run.executedBy.username }}
                  </RouterLink>
                  <span v-else class="text-ink-faint">—</span>
                </dd>
              </div>
              <div class="detail-row">
                <dt>Created</dt>
                <dd class="font-mono text-xs">{{ formatDateTime(run.createdAt) }}</dd>
              </div>
              <div v-if="run.startedAt" class="detail-row">
                <dt>Started</dt>
                <dd class="font-mono text-xs">{{ formatDateTime(run.startedAt) }}</dd>
              </div>
              <div v-if="run.completedAt" class="detail-row">
                <dt>Completed</dt>
                <dd class="font-mono text-xs">{{ formatDateTime(run.completedAt) }}</dd>
              </div>
              <div v-if="run.completedAt" class="detail-row">
                <dt>Duration</dt>
                <dd class="font-mono text-xs">{{ run.duration || '—' }}</dd>
              </div>
              <div v-if="run.originalRun" class="detail-row sm:col-span-2">
                <dt>Retry Of</dt>
                <dd class="flex flex-wrap items-center gap-2">
                  <RouterLink :to="{ name: 'test-run-detail', params: { id: run.originalRun.id } }" class="link">
                    Run #{{ run.originalRun.id }}
                  </RouterLink>
                  <span class="badge border border-accent/25 bg-accent-soft text-accent">Attempt {{ run.retryAttempt }}</span>
                  <span v-if="run.retryOfFailed" class="badge border border-run/25 bg-run/10 text-run">Failed Only</span>
                </dd>
              </div>
            </dl>
          </section>

          <!-- Live progress -->
          <section v-if="isActive" class="rise card overflow-hidden" style="--i: 2">
            <header class="flex items-center justify-between border-b border-edge px-5 py-3.5">
              <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-ink">
                <span class="led led-pulse text-run"></span>
                Live Progress
              </h2>
              <span v-if="progressInfo" class="font-mono text-sm font-bold text-ink">
                {{ progressInfo.current }}<span class="text-ink-faint">/</span>{{ progressInfo.total }}
              </span>
            </header>
            <div class="space-y-3 p-5">
              <div v-if="progressInfo" class="h-2 overflow-hidden rounded-full bg-panel-2">
                <div
                  class="progress-stripes h-full rounded-full bg-run transition-all duration-700"
                  :style="{ width: `${progressInfo.total ? Math.min(100, (progressInfo.current / progressInfo.total) * 100) : 0}%` }"
                ></div>
              </div>
              <div v-if="currentTestName" class="flex items-center gap-2 text-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Current</span>
                <code class="truncate font-mono text-xs text-ink">{{ currentTestName }}</code>
              </div>
              <p v-else class="text-xs text-ink-faint">Waiting for the next test to start…</p>
            </div>
          </section>

          <!-- Results summary -->
          <section v-if="counts.total > 0" class="rise card" style="--i: 2">
            <header class="border-b border-edge px-5 py-3.5">
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Results Summary</h2>
            </header>
            <div class="p-5">
              <div class="grid grid-cols-4 gap-3 text-center">
                <div>
                  <div class="text-3xl font-bold text-pass">{{ counts.passed }}</div>
                  <div class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Passed</div>
                </div>
                <div>
                  <div class="text-3xl font-bold text-fail">{{ counts.failed }}</div>
                  <div class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Failed</div>
                </div>
                <div>
                  <div class="text-3xl font-bold text-broken">{{ counts.broken }}</div>
                  <div class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Broken</div>
                </div>
                <div>
                  <div class="text-3xl font-bold text-skip">{{ counts.skipped }}</div>
                  <div class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Skipped</div>
                </div>
              </div>
              <div class="mt-4 flex h-2 overflow-hidden rounded-full bg-panel-2">
                <div class="bg-pass" :style="{ width: `${(counts.passed / counts.total) * 100}%` }"></div>
                <div class="bg-fail" :style="{ width: `${(counts.failed / counts.total) * 100}%` }"></div>
                <div class="bg-broken" :style="{ width: `${(counts.broken / counts.total) * 100}%` }"></div>
                <div class="bg-skip" :style="{ width: `${(counts.skipped / counts.total) * 100}%` }"></div>
              </div>
            </div>
          </section>

          <!-- Results table -->
          <section v-if="results.length || isActive" class="rise card overflow-hidden" style="--i: 3">
            <header class="flex items-center justify-between border-b border-edge px-5 py-3.5">
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Test Results</h2>
              <span class="badge border border-edge bg-panel-2 text-ink-mute">{{ results.length }}</span>
            </header>
            <div v-if="results.length" class="overflow-x-auto">
              <table class="w-full">
                <thead class="border-b border-edge bg-panel-2/60">
                  <tr>
                    <th class="th-base">Test</th>
                    <th class="th-base">Status</th>
                    <th class="th-base">Duration</th>
                    <th class="th-base text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-edge">
                  <template v-for="result in results" :key="result.id">
                    <tr class="transition-colors hover:bg-panel-2/40">
                      <td class="td-base">
                        <div class="font-medium break-words">{{ result.testName }}</div>
                        <div v-if="result.testId" class="mt-0.5 flex items-center gap-2">
                          <span class="font-mono text-xs text-ink-faint">{{ result.testId }}</span>
                          <RouterLink
                            :to="{ name: 'test-history', query: { testId: result.testId, environmentId: run.environment.id } }"
                            class="link inline-flex items-center gap-1 text-xs"
                            title="View execution history for this test"
                          >
                            <History class="h-3 w-3" />
                            History
                          </RouterLink>
                        </div>
                      </td>
                      <td class="td-base"><StatusBadge :status="result.status" /></td>
                      <td class="td-base">
                        <span class="font-mono text-xs text-ink-mute">{{ result.durationFormatted || '—' }}</span>
                      </td>
                      <td class="td-base">
                        <div class="flex flex-wrap justify-end gap-1.5">
                          <button class="btn-ghost btn-sm" title="View execution steps" @click="openSteps(result)">
                            <ListTree class="h-3.5 w-3.5" />
                            Steps
                          </button>
                          <button v-if="result.hasOutput" class="btn-ghost btn-sm" title="View output" @click="openOutput(result)">
                            <Terminal class="h-3.5 w-3.5" />
                            Output
                          </button>
                          <button
                            v-if="result.screenshotPath"
                            class="btn-ghost btn-sm"
                            title="View screenshot"
                            @click="openLightbox(result.screenshotPath)"
                          >
                            <Image class="h-3.5 w-3.5" />
                          </button>
                          <button
                            v-if="result.errorMessage"
                            class="btn-ghost btn-sm text-fail"
                            :title="expandedErrors.has(result.id) ? 'Hide error' : 'Show error'"
                            @click="toggleError(result.id)"
                          >
                            <Bug class="h-3.5 w-3.5" />
                            <ChevronUp v-if="expandedErrors.has(result.id)" class="h-3 w-3" />
                            <ChevronDown v-else class="h-3 w-3" />
                          </button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="result.errorMessage && expandedErrors.has(result.id)">
                      <td colspan="4" class="bg-fail/5 px-5 py-3">
                        <pre class="max-h-56 overflow-y-auto font-mono text-xs whitespace-pre-wrap break-words text-fail">{{ result.errorMessage }}</pre>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            <div v-else class="px-5 py-10">
              <EmptyState title="No results yet" message="Results appear here as each test finishes." />
            </div>
          </section>

          <!-- Artifacts -->
          <section v-if="artifacts.screenshots.length || artifacts.html.length" class="rise card" style="--i: 4">
            <header class="border-b border-edge px-5 py-3.5">
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Artifacts</h2>
            </header>
            <div class="space-y-5 p-5">
              <div v-if="artifacts.screenshots.length">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-ink-mute">
                  Screenshots ({{ artifacts.screenshots.length }})
                </h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                  <figure v-for="artifact in artifacts.screenshots" :key="artifact.screenshot" class="min-w-0">
                    <button
                      type="button"
                      class="block w-full cursor-zoom-in overflow-hidden rounded-lg border border-edge transition-colors hover:border-accent"
                      :title="artifact.screenshot"
                      @click="openLightbox(artifact.screenshot)"
                    >
                      <img
                        :src="artifactUrl(artifact.screenshot)"
                        :alt="artifact.screenshot"
                        loading="lazy"
                        class="h-28 w-full object-cover"
                      >
                    </button>
                    <figcaption class="mt-1 truncate font-mono text-[11px] text-ink-faint" :title="artifact.screenshot">
                      {{ artifact.screenshot }}
                    </figcaption>
                    <a
                      v-if="artifact.html"
                      :href="artifactUrl(artifact.html)"
                      target="_blank"
                      rel="noopener"
                      class="link mt-0.5 inline-flex items-center gap-1 text-xs"
                    >
                      <FileCode class="h-3 w-3" />
                      HTML report
                    </a>
                  </figure>
                </div>
              </div>

              <div v-if="artifacts.html.length">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-mute">
                  HTML Reports ({{ artifacts.html.length }})
                </h3>
                <ul class="divide-y divide-edge rounded-lg border border-edge">
                  <li v-for="html in artifacts.html" :key="html">
                    <a
                      :href="artifactUrl(html)"
                      target="_blank"
                      rel="noopener"
                      class="flex items-center gap-2 px-3 py-2 font-mono text-xs text-ink transition-colors hover:bg-panel-2 hover:text-accent"
                    >
                      <FileCode class="h-3.5 w-3.5 shrink-0 text-accent" />
                      <span class="truncate">{{ html }}</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </section>

          <!-- Output log -->
          <section class="rise card" style="--i: 5">
            <header class="flex items-center justify-between gap-3 border-b border-edge px-5 py-3.5">
              <h2 class="flex min-w-0 items-center gap-2 text-sm font-bold uppercase tracking-wider text-ink">
                Output Log
                <code v-if="currentTestName" class="truncate font-mono text-xs font-normal normal-case text-ink-faint">
                  ({{ currentTestName }})
                </code>
              </h2>
              <span
                v-if="isLiveOutput"
                class="inline-flex shrink-0 items-center gap-1.5 font-mono text-[11px] font-bold uppercase tracking-widest text-run"
              >
                <span class="led led-pulse"></span>
                Live
              </span>
            </header>
            <div class="p-5">
              <AnsiLog :text="outputText || 'Waiting for output...'" max-height="420px" />
            </div>
          </section>

          <!-- Error message -->
          <section v-if="run.errorMessage" class="rise card border-fail/40" style="--i: 6">
            <header class="flex items-center gap-2 border-b border-fail/30 bg-fail/10 px-5 py-3.5">
              <AlertCircle class="h-4 w-4 text-fail" />
              <h2 class="text-sm font-bold uppercase tracking-wider text-fail">Error</h2>
            </header>
            <pre class="max-h-72 overflow-y-auto p-5 font-mono text-xs whitespace-pre-wrap break-words text-fail">{{ run.errorMessage }}</pre>
          </section>
        </div>

        <!-- Actions rail -->
        <aside class="rise card sticky top-20 p-4" style="--i: 2">
          <h2 class="mb-3 px-1 text-xs font-bold uppercase tracking-widest text-ink-faint">Actions</h2>
          <div class="flex flex-col gap-2">
            <a
              v-if="watchLiveVisible"
              :href="auth.urls.novnc"
              target="_blank"
              rel="noopener"
              class="btn-primary w-full"
            >
              <MonitorPlay class="h-4 w-4" />
              Watch Live
            </a>

            <a v-if="allureUrl" :href="allureUrl" target="_blank" rel="noopener" class="btn-ghost w-full">
              <BarChart3 class="h-4 w-4 text-pass" />
              View Allure Project
            </a>

            <button v-if="run.canBeCancelled" class="btn-danger w-full" @click="requestAction('cancel')">
              <XCircle class="h-4 w-4" />
              Cancel Run
            </button>

            <template v-if="isTerminal">
              <button v-if="counts.failed > 0 || counts.broken > 0" class="btn-ghost w-full" @click="requestAction('retryFailed')">
                <RotateCcw class="h-4 w-4 text-run" />
                Retry Failed
              </button>
              <button class="btn-ghost w-full" @click="requestAction('retry')">
                <RotateCcw class="h-4 w-4" />
                Retry All
              </button>
              <button class="btn-ghost w-full" @click="requestAction('resend')">
                <Bell class="h-4 w-4" />
                Resend Notification
              </button>
            </template>

            <RouterLink :to="{ name: 'test-runs' }" class="btn-ghost w-full">
              <ArrowLeft class="h-4 w-4" />
              Back to List
            </RouterLink>
          </div>
        </aside>
      </div>

      <!-- Steps modal -->
      <Modal :open="stepsModal.open" size="lg" @close="stepsModal = { open: false, result: null }">
        <template #title>
          <span class="inline-flex items-center gap-2">
            <ListTree class="h-4 w-4 text-accent" />
            {{ stepsModal.result?.testName || 'Test Steps' }}
          </span>
        </template>
        <StepTree
          v-if="stepsModal.open && stepsModal.result"
          :api-url="`/api/test-runs/${runId}/results/${stepsModal.result.id}/steps`"
          @loaded="onStepsLoaded"
        />
      </Modal>

      <!-- Output modal -->
      <Modal :open="outputModal.open" size="xl" @close="outputModal.open = false">
        <template #title>
          <span class="inline-flex items-center gap-2">
            <Terminal class="h-4 w-4 text-accent" />
            {{ outputModal.testName || 'Test Output' }}
            <StatusBadge v-if="outputModal.status" :status="outputModal.status" />
          </span>
        </template>
        <div v-if="outputModal.loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="skeleton h-4" :style="{ width: `${55 + ((i * 23) % 40)}%` }"></div>
        </div>
        <AnsiLog v-else :text="outputModal.text" max-height="65vh" />
      </Modal>

      <Lightbox :src="lightboxSrc" :alt="lightboxName" @close="lightboxSrc = ''" />

      <ConfirmDialog
        :open="!!confirmAction"
        :title="confirmAction?.title || ''"
        :message="confirmAction?.message || ''"
        :confirm-label="confirmAction?.confirmLabel || 'Confirm'"
        :danger="confirmAction?.danger"
        :busy="confirmBusy"
        @confirm="executeAction"
        @cancel="confirmAction = null"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  AlertCircle, ArrowLeft, BarChart3, Bell, Bug, ChevronDown, ChevronUp, FileCode, History, Image,
  ListTree, MonitorPlay, RotateCcw, Terminal, XCircle,
} from 'lucide-vue-next';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { useAuthStore } from '../../stores/auth';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import Modal from '../../components/ui/Modal.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import AnsiLog from './components/AnsiLog.vue';
import StepTree from './components/StepTree.vue';
import Lightbox from './components/Lightbox.vue';
import { capitalize, formatDateTime } from './utils/format';

const ACTIVE_STATUSES = ['pending', 'preparing', 'cloning', 'waiting', 'running', 'reporting'];
const WATCHABLE_STATUSES = ['preparing', 'cloning', 'running'];
const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];
const POLL_INTERVAL = 2000;

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();
const auth = useAuthStore();

const runId = computed(() => Number(route.params.id));
const run = ref(null);
const results = ref([]);
const loading = ref(true);
const notFound = ref(false);

const liveOutput = ref('');
const liveCurrentTest = ref(null);
const liveProgress = ref(null);
let pollTimer = null;

const expandedErrors = ref(new Set());
const stepsModal = ref({ open: false, result: null });
const outputModal = ref({ open: false, loading: false, testName: '', status: '', text: '' });
const lightboxSrc = ref('');
const lightboxName = ref('');
const confirmAction = ref(null);
const confirmBusy = ref(false);

const isActive = computed(() => !!run.value && ACTIVE_STATUSES.includes(run.value.status));
const isTerminal = computed(() => !!run.value && TERMINAL_STATUSES.includes(run.value.status));
const isLiveOutput = computed(() => !!run.value && ['preparing', 'cloning', 'running'].includes(run.value.status));
const watchLiveVisible = computed(
  () => !!run.value && WATCHABLE_STATUSES.includes(run.value.status) && !!auth.urls.novnc,
);

const counts = computed(() => run.value?.resultCounts || { passed: 0, failed: 0, broken: 0, skipped: 0, total: 0 });

const artifacts = computed(() => run.value?.artifacts || { screenshots: [], html: [] });

const outputText = computed(() => liveOutput.value || run.value?.output || '');

const currentTestName = computed(() => liveCurrentTest.value ?? run.value?.currentTestName ?? null);

const progressInfo = computed(() => {
  if (liveProgress.value) {
    const [current, total] = liveProgress.value.split('/').map(Number);
    return { current, total };
  }
  if (run.value && run.value.totalTests !== null && run.value.totalTests !== undefined) {
    const completed = run.value.completedTests ?? 0;
    return { current: run.value.currentTestName ? completed + 1 : completed, total: run.value.totalTests };
  }
  return null;
});

const allureUrl = computed(() => {
  if (!auth.urls.allure || !run.value) return null;
  return `${auth.urls.allure}/allure-docker-service/projects/${run.value.environment.name}/reports/latest/index.html`;
});

function typeBadgeClass(type) {
  if (type === 'mftf') return 'border-accent/25 bg-accent-soft text-accent';
  if (type === 'playwright') return 'border-run/25 bg-run/10 text-run';
  return 'border-broken/25 bg-broken/10 text-broken';
}

function artifactUrl(filename) {
  return `/admin/test-runs/${runId.value}/artifacts/${encodeURIComponent(filename)}`;
}

function setResultsFromRun(data) {
  results.value = (data.results || []).map((result) => ({ ...result }));
}

function mergeLiveResults(list) {
  for (const item of list) {
    const existing = results.value.find((result) => result.id === item.id);
    if (existing) {
      existing.status = item.status;
      existing.duration = item.duration;
      existing.durationFormatted = item.durationFormatted;
      existing.errorMessage = item.errorMessage;
      existing.hasOutput = existing.hasOutput || !!item.hasOutputFile;
    } else {
      results.value.push({
        id: item.id,
        testName: item.testName,
        testId: null,
        status: item.status,
        duration: item.duration,
        durationFormatted: item.durationFormatted,
        errorMessage: item.errorMessage,
        screenshotPath: null,
        hasOutput: !!item.hasOutputFile,
      });
    }
  }
}

async function loadRun({ silent = false } = {}) {
  if (!silent) loading.value = true;
  try {
    const data = await api.get(`/api/test-runs/${runId.value}`);
    run.value = data;
    setResultsFromRun(data);
  } catch (e) {
    if (e.status === 404) notFound.value = true;
    else toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

async function poll() {
  if (document.hidden || !run.value) return;
  try {
    const data = await api.get(`/api/test-runs/${runId.value}/live-output`);
    if (data.output) liveOutput.value = data.output;
    liveCurrentTest.value = data.currentTest;
    liveProgress.value = data.progress;
    run.value.status = data.status;
    if (data.resultCounts) run.value.resultCounts = data.resultCounts;
    if (data.results?.length) mergeLiveResults(data.results);

    if (TERMINAL_STATUSES.includes(data.status)) {
      stopPolling();
      await loadRun({ silent: true });
    }
  } catch {
    // transient polling errors are retried on the next tick
  }
}

function startPolling() {
  if (pollTimer) return;
  pollTimer = setInterval(poll, POLL_INTERVAL);
  poll();
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function onVisibilityChange() {
  if (!document.hidden && pollTimer) poll();
}

function toggleError(resultId) {
  const next = new Set(expandedErrors.value);
  if (next.has(resultId)) next.delete(resultId);
  else next.add(resultId);
  expandedErrors.value = next;
}

function openSteps(result) {
  stepsModal.value = { open: true, result };
}

function onStepsLoaded({ testName }) {
  // Backfill test name from Allure data (mirrors legacy test-name-updated event)
  if (!testName || testName === 'Unknown') return;
  const row = results.value.find((result) => result.id === stepsModal.value.result?.id);
  if (row && row.testName !== testName) row.testName = testName;
}

async function openOutput(result) {
  outputModal.value = { open: true, loading: true, testName: result.testName, status: '', text: '' };
  try {
    const data = await api.get(`/api/test-runs/${runId.value}/results/${result.id}/output`);
    outputModal.value.testName = data.testName || result.testName;
    outputModal.value.status = data.status || '';
    outputModal.value.text = data.output || 'No output available';
  } catch (e) {
    outputModal.value.text = `Error loading output: ${e.message}`;
  } finally {
    outputModal.value.loading = false;
  }
}

function openLightbox(filename) {
  lightboxName.value = filename;
  lightboxSrc.value = artifactUrl(filename);
}

function requestAction(type) {
  const actions = {
    cancel: { title: `Cancel run #${runId.value}`, message: 'Cancel this test run?', confirmLabel: 'Cancel Run', danger: true },
    retry: { title: 'Retry all tests', message: 'Create a new run with the same configuration?', confirmLabel: 'Retry All' },
    retryFailed: {
      title: 'Retry failed tests',
      message: 'Retry failed test(s) with infrastructure errors? Only WebDriver/infrastructure failures are retryable.',
      confirmLabel: 'Retry Failed',
    },
    resend: { title: 'Resend notification', message: 'Resend notification via Slack/Email?', confirmLabel: 'Resend' },
  };
  confirmAction.value = { type, ...actions[type] };
}

async function executeAction() {
  const { type } = confirmAction.value;
  confirmBusy.value = true;
  try {
    if (type === 'cancel') {
      const data = await api.post(`/api/test-runs/${runId.value}/cancel`);
      toasts.success(data.message || 'Run cancelled');
      stopPolling();
      await loadRun({ silent: true });
    } else if (type === 'retry') {
      const data = await api.post(`/api/test-runs/${runId.value}/retry`);
      toasts.success(`New run #${data.run.id} created`);
      router.push({ name: 'test-run-detail', params: { id: data.run.id } });
    } else if (type === 'retryFailed') {
      const data = await api.post(`/api/test-runs/${runId.value}/retry-failed`);
      toasts.success(data.message || `New run #${data.run.id} created`);
      router.push({ name: 'test-run-detail', params: { id: data.run.id } });
    } else if (type === 'resend') {
      const data = await api.post(`/api/test-runs/${runId.value}/resend-notification`);
      toasts.success(data.message || 'Notification sent');
    }
    confirmAction.value = null;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    confirmBusy.value = false;
  }
}

async function init() {
  stopPolling();
  run.value = null;
  results.value = [];
  liveOutput.value = '';
  liveCurrentTest.value = null;
  liveProgress.value = null;
  notFound.value = false;
  expandedErrors.value = new Set();
  await loadRun();
}

watch(isActive, (active) => {
  if (active) startPolling();
  else stopPolling();
});

watch(runId, () => {
  if (route.name === 'test-run-detail') init();
});

onMounted(() => {
  init();
  document.addEventListener('visibilitychange', onVisibilityChange);
});

onUnmounted(() => {
  stopPolling();
  document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<style scoped>
.detail-row {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  min-width: 0;
}

.detail-row dt {
  flex-shrink: 0;
  width: 6.5rem;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--m-ink-faint);
}

.detail-row dd {
  min-width: 0;
  overflow-wrap: anywhere;
}

@keyframes stripe-slide {
  0% { background-position: 0 0; }
  100% { background-position: 24px 0; }
}

.progress-stripes {
  background-image: linear-gradient(
    45deg,
    rgba(255, 255, 255, 0.25) 25%,
    transparent 25%,
    transparent 50%,
    rgba(255, 255, 255, 0.25) 50%,
    rgba(255, 255, 255, 0.25) 75%,
    transparent 75%
  );
  background-size: 24px 24px;
  animation: stripe-slide 0.8s linear infinite;
}
</style>
