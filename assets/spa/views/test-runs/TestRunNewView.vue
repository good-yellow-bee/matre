<template>
  <div class="mx-auto max-w-2xl">
    <PageHeader title="Start Test Run" subtitle="Execute a suite or a hand-picked set of tests against an environment">
      <template #actions>
        <RouterLink :to="{ name: 'test-runs' }" class="btn-ghost">
          <ArrowLeft class="h-4 w-4" />
          Back to List
        </RouterLink>
      </template>
    </PageHeader>

    <form class="rise card p-6" style="--i: 1" @submit.prevent="submit">
      <div class="mb-6 inline-flex rounded-lg border border-edge bg-panel-2 p-1">
        <button
          v-for="option in MODES"
          :key="option.value"
          type="button"
          class="cursor-pointer rounded-md px-4 py-1.5 text-sm font-semibold transition-colors"
          :class="mode === option.value ? 'bg-accent text-accent-ink shadow-sm' : 'text-ink-mute hover:text-ink'"
          @click="setMode(option.value)"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="space-y-5">
        <template v-if="mode === 'suite'">
          <div>
            <label class="label" for="suite-select">Test Suite</label>
            <select
              id="suite-select"
              v-model="form.suiteId"
              class="input"
              :disabled="loadingSuites"
              @change="onSuiteChange"
            >
              <option :value="null">{{ loadingSuites ? 'Loading…' : '-- Select Suite --' }}</option>
              <option v-for="suite in suites" :key="suite.id" :value="suite.id">
                {{ suite.name }} ({{ suite.typeLabel }})
              </option>
            </select>
            <p v-if="fieldErrors.suiteId" class="field-error">{{ fieldErrors.suiteId }}</p>
          </div>

          <div>
            <label class="label" for="suite-environment-select">Environment</label>
            <div class="relative">
              <select
                id="suite-environment-select"
                v-model="form.environmentId"
                class="input"
                :disabled="environmentState.disabled"
              >
                <option :value="null">{{ environmentState.hint || '-- Select Environment --' }}</option>
                <option v-for="env in suiteEnvironments" :key="env.id" :value="env.id">{{ env.name }}</option>
              </select>
              <Loader2 v-if="loadingEnvironments" class="absolute top-1/2 right-9 h-4 w-4 -translate-y-1/2 animate-spin text-accent" />
            </div>
            <p v-if="environmentState.error" class="field-error">{{ environmentState.hint }}</p>
            <p v-if="fieldErrors.environmentId" class="field-error">{{ fieldErrors.environmentId }}</p>
          </div>
        </template>

        <template v-else>
          <div>
            <label class="label" for="environment-select">Environment</label>
            <select id="environment-select" v-model="form.environmentId" class="input" :disabled="loadingAllEnvironments">
              <option :value="null">{{ loadingAllEnvironments ? 'Loading…' : '-- Select Environment --' }}</option>
              <option v-for="env in allEnvironments" :key="env.id" :value="env.id">{{ env.name }}</option>
            </select>
            <p v-if="fieldErrors.environmentId" class="field-error">{{ fieldErrors.environmentId }}</p>
          </div>

          <div>
            <label class="label" for="type-select">Test Type</label>
            <select id="type-select" v-model="form.type" class="input">
              <option v-for="option in TYPES" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="fieldErrors.type" class="field-error">{{ fieldErrors.type }}</p>
          </div>

          <div>
            <label class="label" for="test-filter">Test Filter</label>
            <input
              id="test-filter"
              v-model="form.testFilter"
              type="text"
              class="input font-mono"
              placeholder="e.g. MOEC13447 or AdminLoginTest,StorefrontCheckoutTest"
              spellcheck="false"
            >
            <p class="mt-1 text-xs text-ink-faint">Test name, comma-separated list, or group pattern passed to the runner.</p>
            <p v-if="fieldErrors.testFilter" class="field-error">{{ fieldErrors.testFilter }}</p>
          </div>
        </template>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-mute">
          <input v-model="form.sendNotifications" type="checkbox" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
          Send notifications when complete
        </label>
      </div>

      <div class="mt-6 flex items-center gap-3 border-t border-edge pt-5">
        <button type="submit" class="btn-primary" :disabled="!canSubmit">
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <Play v-else class="h-4 w-4" />
          {{ submitting ? 'Starting…' : 'Start Run' }}
        </button>
        <RouterLink :to="{ name: 'test-runs' }" class="btn-ghost">Cancel</RouterLink>
      </div>
    </form>

    <p class="rise mt-4 flex items-start gap-2 text-xs text-ink-faint" style="--i: 2">
      <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
      Tests are executed asynchronously — you can monitor progress on the run detail page.
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Info, Loader2, Play } from 'lucide-vue-next';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import PageHeader from '../../components/ui/PageHeader.vue';

const MODES = [
  { value: 'suite', label: 'Suite-based' },
  { value: 'manual', label: 'Manual' },
];

const TYPES = [
  { value: 'mftf', label: 'MFTF' },
  { value: 'playwright', label: 'Playwright' },
];

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const mode = ref('suite');
const suites = ref([]);
const suiteEnvironments = ref([]);
const allEnvironments = ref([]);
const loadingSuites = ref(false);
const loadingEnvironments = ref(false);
const loadingAllEnvironments = ref(false);
const submitting = ref(false);
const fieldErrors = ref({});

const form = reactive({
  suiteId: null,
  environmentId: null,
  type: 'mftf',
  testFilter: '',
  sendNotifications: true,
});

const environmentState = computed(() => {
  if (!form.suiteId) return { disabled: true, hint: 'Select a suite first' };
  if (loadingEnvironments.value) return { disabled: true, hint: 'Loading…' };
  if (suiteEnvironments.value.length === 0) {
    return { disabled: true, hint: 'No environments assigned to this suite', error: true };
  }
  return { disabled: false, hint: null };
});

const canSubmit = computed(() => {
  if (submitting.value) return false;
  if (mode.value === 'suite') return !!(form.suiteId && form.environmentId);
  return !!(form.environmentId && form.type && form.testFilter.trim());
});

function setMode(value) {
  mode.value = value;
  fieldErrors.value = {};
  form.environmentId = null;
}

// Derive run type from the selected suite.s type
function deriveType(suite) {
  if (suite?.type?.startsWith('playwright')) return 'playwright';
  return 'mftf';
}

async function loadSuites() {
  loadingSuites.value = true;
  try {
    suites.value = await api.get('/api/test-suites');
  } catch (e) {
    toasts.error(`Failed to load test suites: ${e.message}`);
  } finally {
    loadingSuites.value = false;
  }
}

async function loadAllEnvironments() {
  loadingAllEnvironments.value = true;
  try {
    allEnvironments.value = await api.get('/api/test-suites/environments');
  } catch (e) {
    toasts.error(`Failed to load environments: ${e.message}`);
  } finally {
    loadingAllEnvironments.value = false;
  }
}

async function fetchSuiteEnvironments(suiteId) {
  if (!suiteId) {
    suiteEnvironments.value = [];
    form.environmentId = null;
    return;
  }
  loadingEnvironments.value = true;
  try {
    suiteEnvironments.value = await api.get(`/api/test-suites/${suiteId}/environments`);
    form.environmentId = suiteEnvironments.value.length === 1 ? suiteEnvironments.value[0].id : null;
  } catch (e) {
    suiteEnvironments.value = [];
    toasts.error(`Failed to load environments: ${e.message}`);
  } finally {
    loadingEnvironments.value = false;
  }
}

function onSuiteChange() {
  fetchSuiteEnvironments(form.suiteId);
}

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  fieldErrors.value = {};

  const payload = { environmentId: form.environmentId, sendNotifications: form.sendNotifications };
  if (mode.value === 'suite') {
    const suite = suites.value.find((s) => s.id === form.suiteId);
    payload.suiteId = form.suiteId;
    payload.type = deriveType(suite);
  } else {
    payload.type = form.type;
    payload.testFilter = form.testFilter.trim();
  }

  try {
    const data = await api.post('/api/test-runs', payload);
    toasts.success(`Test run #${data.id} started`);
    router.push({ name: 'test-run-detail', params: { id: data.id } });
  } catch (e) {
    if (e.payload?.errors) {
      fieldErrors.value = e.payload.errors;
    } else {
      toasts.error(e.message);
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  const suiteId = Number(route.query.suiteId) || null;
  const environmentId = Number(route.query.environmentId) || null;

  await Promise.all([loadSuites(), loadAllEnvironments()]);

  if (suiteId && suites.value.some((s) => s.id === suiteId)) {
    mode.value = 'suite';
    form.suiteId = suiteId;
    await fetchSuiteEnvironments(suiteId);
    if (environmentId && suiteEnvironments.value.some((env) => env.id === environmentId)) {
      form.environmentId = environmentId;
    }
  } else if (environmentId) {
    mode.value = 'manual';
    if (allEnvironments.value.some((env) => env.id === environmentId)) {
      form.environmentId = environmentId;
    }
  }
});
</script>
