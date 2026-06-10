<template>
  <div>
    <PageHeader :title="suite?.name || 'Test Suite'" :subtitle="suite?.description || 'Test suite configuration'">
      <template #actions>
        <RouterLink :to="{ name: 'suites' }" class="btn-ghost">
          <ArrowLeft class="h-4 w-4" />
          Back
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="notFound" class="card rise p-14" style="--i: 1">
      <EmptyState title="Test suite not found" message="It may have been deleted.">
        <RouterLink :to="{ name: 'suites' }" class="btn-primary btn-sm mt-2">Back to list</RouterLink>
      </EmptyState>
    </div>

    <div
      v-else-if="loadError"
      class="rise flex items-center gap-3 rounded-xl border border-fail/30 bg-fail/10 p-4 text-sm text-fail"
      style="--i: 1"
    >
      <AlertCircle class="h-4 w-4 shrink-0" />
      {{ loadError }}
      <button class="ml-auto cursor-pointer font-semibold hover:underline" @click="load">Retry</button>
    </div>

    <div v-else-if="!suite" class="grid items-start gap-6 lg:grid-cols-3">
      <div class="card rise space-y-4 p-5 lg:col-span-2" style="--i: 1">
        <div v-for="i in 7" :key="i" class="skeleton h-4" :style="{ width: `${90 - i * 8}%` }"></div>
      </div>
      <div class="card rise space-y-3 p-5" style="--i: 2">
        <div v-for="i in 5" :key="i" class="skeleton h-8"></div>
      </div>
    </div>

    <div v-else class="grid items-start gap-6 lg:grid-cols-3">
      <div class="card rise lg:col-span-2" style="--i: 1">
        <div class="flex items-center justify-between border-b border-edge px-5 py-3.5">
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Suite Configuration</h2>
          <span
            class="badge border"
            :class="suite.isActive ? 'border-pass/25 bg-pass/10 text-pass' : 'border-edge bg-panel-2 text-ink-mute'"
          >
            {{ suite.isActive ? 'Active' : 'Inactive' }}
          </span>
        </div>
        <dl class="divide-y divide-edge">
          <div class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Type</dt>
            <dd><SuiteTypeBadge :type="suite.type" /></dd>
          </div>
          <div class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Test Pattern</dt>
            <dd>
              <code class="mono-id block w-fit max-w-full break-all rounded-md bg-panel-2 px-2 py-1">{{ suite.testPattern }}</code>
            </dd>
          </div>
          <div v-if="suite.type === 'mftf_group'" class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Excluded Tests</dt>
            <dd>
              <code
                v-if="suite.excludedTests"
                class="mono-id block w-fit max-w-full break-all rounded-md bg-panel-2 px-2 py-1"
              >{{ suite.excludedTests }}</code>
              <span v-else class="text-sm text-ink-faint">None</span>
            </dd>
          </div>
          <div class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Schedule</dt>
            <dd>
              <template v-if="suite.cronExpression">
                <code class="mono-id block w-fit rounded-md bg-panel-2 px-2 py-1">{{ suite.cronExpression }}</code>
                <p v-if="nextRun" class="mt-1 text-xs text-ink-faint">{{ nextRun }}</p>
              </template>
              <span v-else class="text-sm text-ink-faint">Manual execution only</span>
            </dd>
          </div>
          <div class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Environments</dt>
            <dd>
              <div v-if="environments.length" class="flex flex-wrap gap-1">
                <span v-for="env in environments" :key="env.id" class="badge border border-edge bg-panel-2 text-ink-mute">
                  {{ env.name }}
                </span>
              </div>
              <span v-else class="text-sm text-ink-faint">No environments selected</span>
            </dd>
          </div>
          <div class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Created</dt>
            <dd class="font-mono text-[13px] text-ink">{{ formatDateTime(suite.createdAt) }}</dd>
          </div>
          <div v-if="suite.updatedAt" class="grid gap-1 px-5 py-3 sm:grid-cols-[160px_1fr] sm:gap-4">
            <dt class="pt-0.5 text-xs font-semibold uppercase tracking-wider text-ink-mute">Updated</dt>
            <dd class="font-mono text-[13px] text-ink">{{ formatDateTime(suite.updatedAt) }}</dd>
          </div>
        </dl>
      </div>

      <div class="card rise" style="--i: 2">
        <div class="border-b border-edge px-5 py-3.5">
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Actions</h2>
        </div>
        <div class="flex flex-col gap-2 p-5">
          <button class="btn-primary" @click="runSuite">
            <Play class="h-4 w-4" />
            Run Suite
          </button>
          <RouterLink :to="{ name: 'suite-edit', params: { id: suite.id } }" class="btn-ghost">
            <Pencil class="h-4 w-4" />
            Edit
          </RouterLink>
          <button class="btn-ghost" @click="askConfirm('duplicate')">
            <Copy class="h-4 w-4" />
            Duplicate
          </button>
          <button class="btn-ghost" @click="askConfirm('toggle')">
            <Power class="h-4 w-4" :class="suite.isActive ? 'text-skip' : 'text-pass'" />
            {{ suite.isActive ? 'Deactivate' : 'Activate' }}
          </button>
          <RouterLink :to="{ name: 'suites' }" class="btn-ghost">
            <ArrowLeft class="h-4 w-4" />
            Back to List
          </RouterLink>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :open="confirmAction !== null"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :confirm-label="confirmConfig.confirmLabel"
      :busy="confirmBusy"
      @confirm="runConfirmed"
      @cancel="confirmAction = null"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertCircle, ArrowLeft, Copy, Pencil, Play, Power } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import SuiteTypeBadge from './components/SuiteTypeBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const suiteId = computed(() => Number(route.params.id));
const suite = ref(null);
const environments = ref([]);
const nextRun = ref('');
const notFound = ref(false);
const loadError = ref('');
const confirmAction = ref(null);
const confirmBusy = ref(false);

const confirmConfig = computed(() => {
  if (!confirmAction.value || !suite.value) return {};
  if (confirmAction.value === 'duplicate') {
    return {
      title: 'Duplicate test suite',
      message: `Create a copy of "${suite.value.name}"?`,
      confirmLabel: 'Duplicate',
    };
  }
  return suite.value.isActive
    ? {
        title: 'Deactivate test suite',
        message: `Deactivate "${suite.value.name}"? Scheduled runs will stop until it is reactivated.`,
        confirmLabel: 'Deactivate',
      }
    : {
        title: 'Activate test suite',
        message: `Activate "${suite.value.name}"?`,
        confirmLabel: 'Activate',
      };
});

async function load() {
  loadError.value = '';
  try {
    const [suiteData, envs] = await Promise.all([
      api.get(`/api/test-suites/${suiteId.value}`),
      api.get(`/api/test-suites/${suiteId.value}/environments`),
    ]);
    suite.value = suiteData;
    environments.value = envs;
    if (suiteData.cronExpression) loadNextRun(suiteData.cronExpression);
  } catch (e) {
    if (e.status === 404) {
      notFound.value = true;
      return;
    }
    loadError.value = e.message;
  }
}

async function loadNextRun(cronExpression) {
  try {
    const result = await api.post('/api/test-suites/validate-cron', { cronExpression });
    nextRun.value = result.valid ? result.message : '';
  } catch {
    nextRun.value = '';
  }
}

function runSuite() {
  router.push({ name: 'test-run-new', query: { suiteId: suite.value.id } });
}

function askConfirm(action) {
  confirmAction.value = action;
}

async function runConfirmed() {
  confirmBusy.value = true;
  try {
    if (confirmAction.value === 'duplicate') {
      const result = await api.post(`/api/test-suites/${suite.value.id}/duplicate`);
      toasts.success(result.message);
      confirmAction.value = null;
      router.push({ name: 'suite-edit', params: { id: result.id } });
    } else {
      const result = await api.post(`/api/test-suites/${suite.value.id}/toggle-active`);
      toasts.success(result.message);
      confirmAction.value = null;
      suite.value.isActive = result.isActive;
    }
  } catch (e) {
    toasts.error(e.message);
  } finally {
    confirmBusy.value = false;
  }
}

function formatDateTime(iso) {
  return new Date(iso).toLocaleString();
}

onMounted(load);
</script>
