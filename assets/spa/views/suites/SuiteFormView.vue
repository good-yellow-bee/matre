<template>
  <div class="mx-auto max-w-3xl">
    <PageHeader
      :title="isEdit ? 'Edit Test Suite' : 'Add Test Suite'"
      :subtitle="isEdit ? 'Update test suite configuration' : 'Create a reusable test collection'"
    >
      <template #actions>
        <RouterLink :to="{ name: 'suites' }" class="btn-ghost">
          <ArrowLeft class="h-4 w-4" />
          Back to List
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loading" class="space-y-6">
      <div class="card p-5">
        <div class="skeleton mb-4 h-9 w-full"></div>
        <div class="skeleton mb-4 h-9 w-full"></div>
        <div class="skeleton h-24 w-full"></div>
      </div>
      <div class="card p-5">
        <div class="skeleton mb-4 h-9 w-full"></div>
        <div class="skeleton h-24 w-full"></div>
      </div>
    </div>

    <form v-else class="space-y-6" novalidate @submit.prevent="submit">
      <section class="card rise p-5" style="--i: 1">
        <h2 class="mb-4 border-b border-edge pb-2 text-sm font-bold uppercase tracking-wider text-ink">
          Suite Configuration
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="suite-name">Name *</label>
            <input
              id="suite-name"
              v-model="form.name"
              class="input"
              :class="{ 'border-fail': errors.name }"
              type="text"
              maxlength="100"
              required
              @blur="validateName"
              @input="clearField('name')"
            >
            <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
            <p v-else-if="valid.name" class="mt-1 text-xs text-pass">{{ valid.name }}</p>
          </div>
          <div>
            <label class="label" for="suite-type">Type *</label>
            <select
              id="suite-type"
              v-model="form.type"
              class="input"
              :class="{ 'border-fail': errors.type }"
              required
              @change="onTypeChange"
            >
              <option value="">Select type…</option>
              <option v-for="t in suiteTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <p v-if="errors.type" class="field-error">{{ errors.type }}</p>
            <p v-if="suiteTypesError" class="mt-1 text-xs text-run">{{ suiteTypesError }}</p>
          </div>
        </div>

        <div class="mt-4">
          <label class="label">Test / Group *</label>
          <TestPatternSelector
            v-model="form.testPattern"
            :type="form.type"
            :error="errors.testPattern"
            @update:model-value="clearField('testPattern')"
          />
        </div>

        <div v-if="isMftfGroup" class="mt-4">
          <label class="label" for="suite-excluded">Excluded Tests</label>
          <textarea
            id="suite-excluded"
            v-model="form.excludedTests"
            class="input font-mono"
            :class="{ 'border-fail': errors.excludedTests }"
            rows="3"
            placeholder="MOEC11676, MOEC2609ES"
          ></textarea>
          <p v-if="errors.excludedTests" class="field-error">{{ errors.excludedTests }}</p>
          <p class="mt-1 text-xs text-ink-faint">
            Comma-separated exact MFTF test names from XML <span class="kbd">&lt;test name="..."&gt;</span>.
          </p>
        </div>

        <div class="mt-4">
          <label class="label" for="suite-description">Description</label>
          <textarea id="suite-description" v-model="form.description" class="input" rows="3"></textarea>
        </div>
      </section>

      <section class="card rise p-5" style="--i: 2">
        <h2 class="mb-4 border-b border-edge pb-2 text-sm font-bold uppercase tracking-wider text-ink">
          Schedule
        </h2>

        <div>
          <label class="label" for="suite-cron">Cron Expression</label>
          <input
            id="suite-cron"
            v-model="form.cronExpression"
            class="input font-mono"
            :class="{ 'border-fail': errors.cronExpression }"
            type="text"
            placeholder="0 6 * * *"
            @blur="validateCron"
            @input="clearField('cronExpression')"
          >
          <p v-if="errors.cronExpression" class="field-error">{{ errors.cronExpression }}</p>
          <p v-else-if="valid.cronExpression" class="mt-1 text-xs text-pass">{{ valid.cronExpression }}</p>
          <p class="mt-1 text-xs text-ink-faint">Leave empty for manual execution only</p>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <button
              v-for="preset in cronPresets"
              :key="preset.expression"
              type="button"
              class="kbd cursor-pointer py-1 transition-colors hover:border-accent hover:text-accent"
              @click="applyCronPreset(preset)"
            >
              {{ preset.expression }} · {{ preset.label }}
            </button>
          </div>
        </div>

        <div class="mt-5">
          <label class="label">Target Environments</label>
          <div
            v-if="availableEnvironments.length"
            class="grid gap-2 rounded-lg border border-edge bg-panel-2/60 p-3 sm:grid-cols-2 lg:grid-cols-3"
          >
            <label
              v-for="env in availableEnvironments"
              :key="env.id"
              class="flex cursor-pointer items-center gap-2 text-sm text-ink"
            >
              <input
                v-model="form.environments"
                type="checkbox"
                :value="env.id"
                class="h-4 w-4 rounded border-edge accent-(--m-accent)"
              >
              {{ env.name }}
            </label>
          </div>
          <p v-else-if="environmentsError" class="mt-1 text-xs text-run">{{ environmentsError }}</p>
          <p v-else class="mt-1 text-xs text-ink-faint">No active environments available</p>
          <p class="mt-1 text-xs text-ink-faint">Select environments to run scheduled tests on</p>
        </div>
      </section>

      <section class="card rise p-5" style="--i: 3">
        <h2 class="mb-4 border-b border-edge pb-2 text-sm font-bold uppercase tracking-wider text-ink">
          Status
        </h2>
        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-ink">
          <input v-model="form.isActive" type="checkbox" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
          Active
        </label>
        <p class="mt-1 text-xs text-ink-faint">Inactive suites cannot be executed</p>
      </section>

      <div class="rise flex items-center gap-2" style="--i: 4">
        <button class="btn-primary" type="submit" :disabled="submitting">
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <CheckCircle2 v-else class="h-4 w-4" />
          {{ isEdit ? 'Save Changes' : 'Create Suite' }}
        </button>
        <RouterLink :to="{ name: 'suites' }" class="btn-ghost">Cancel</RouterLink>
      </div>
    </form>

    <div class="rise mt-6 rounded-lg border border-accent/25 bg-accent-soft p-4 text-sm" style="--i: 5">
      <p class="font-semibold text-ink">Test pattern examples</p>
      <ul class="mt-2 space-y-1 text-ink-mute">
        <li>
          <span class="font-semibold">MFTF:</span>
          <span class="kbd">MOEC1625</span> (single test) or <span class="kbd">checkout</span> (group)
        </li>
        <li>
          <span class="font-semibold">Playwright:</span>
          <span class="kbd">@checkout</span> (tag) or <span class="kbd">CheckoutTest</span> (file/test name)
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, CheckCircle2, Loader2 } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import TestPatternSelector from './components/TestPatternSelector.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const suiteId = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEdit = computed(() => suiteId.value !== null);

const form = reactive({
  name: '',
  type: '',
  testPattern: '',
  excludedTests: '',
  description: '',
  cronExpression: '',
  environments: [],
  isActive: true,
});

const errors = reactive({});
const valid = reactive({});
const submitting = ref(false);
const loading = ref(isEdit.value);

const suiteTypes = ref([]);
const suiteTypesError = ref('');
const availableEnvironments = ref([]);
const environmentsError = ref('');

const isMftfGroup = computed(() => form.type === 'mftf_group');

const cronPresets = [
  { expression: '0 6 * * *', label: 'Daily 06:00' },
  { expression: '0 */4 * * *', label: 'Every 4 hours' },
  { expression: '0 22 * * 1-5', label: 'Weeknights 22:00' },
  { expression: '0 8 * * 1', label: 'Mondays 08:00' },
];

function clearField(field) {
  delete errors[field];
  delete valid[field];
}

function onTypeChange() {
  form.testPattern = '';
  if (!isMftfGroup.value) form.excludedTests = '';
  clearField('type');
  clearField('testPattern');
  clearField('excludedTests');
}

async function validateName() {
  clearField('name');
  if (!form.name) {
    errors.name = 'Name is required';
    return;
  }
  if (form.name.length < 2) {
    errors.name = 'Name must be at least 2 characters';
    return;
  }
  try {
    const result = await api.post('/api/test-suites/validate-name', { name: form.name, excludeId: suiteId.value });
    if (result.valid) valid.name = result.message;
    else errors.name = result.message;
  } catch {
    errors.name = 'Could not validate name. Check your connection.';
  }
}

async function validateCron() {
  clearField('cronExpression');
  if (!form.cronExpression) return;
  try {
    const result = await api.post('/api/test-suites/validate-cron', { cronExpression: form.cronExpression });
    if (result.valid) valid.cronExpression = result.message;
    else errors.cronExpression = result.message;
  } catch {
    errors.cronExpression = 'Could not validate expression. Check your connection.';
  }
}

function applyCronPreset(preset) {
  clearField('cronExpression');
  form.cronExpression = preset.expression;
  validateCron();
}

async function loadTypes() {
  try {
    suiteTypes.value = await api.get('/api/test-suites/types');
  } catch {
    suiteTypesError.value = 'Failed to load suite types';
  }
}

async function loadEnvironments() {
  try {
    availableEnvironments.value = await api.get('/api/test-suites/environments');
  } catch {
    environmentsError.value = 'Failed to load environments';
  }
}

async function loadSuite() {
  try {
    const data = await api.get(`/api/test-suites/${suiteId.value}`);
    form.name = data.name;
    form.type = data.type;
    form.testPattern = data.testPattern;
    form.excludedTests = data.excludedTests || '';
    form.description = data.description || '';
    form.cronExpression = data.cronExpression || '';
    form.environments = data.environments || [];
    form.isActive = data.isActive;
  } catch (e) {
    toasts.error(e.status === 404 ? 'Test suite not found' : 'Failed to load suite data');
    router.push({ name: 'suites' });
  }
}

async function submit() {
  Object.keys(errors).forEach((field) => delete errors[field]);
  if (!form.name) errors.name = 'Name is required';
  if (!form.type) errors.type = 'Type is required';
  if (!form.testPattern) errors.testPattern = 'Test pattern is required';
  if (Object.keys(errors).length) return;

  submitting.value = true;
  try {
    const payload = { ...form, excludedTests: isMftfGroup.value ? form.excludedTests : null };
    const result = isEdit.value
      ? await api.put(`/api/test-suites/${suiteId.value}`, payload)
      : await api.post('/api/test-suites', payload);
    toasts.success(result.message);
    router.push({ name: 'suite-detail', params: { id: isEdit.value ? suiteId.value : result.id } });
  } catch (e) {
    if (e.payload?.errors) {
      Object.assign(errors, e.payload.errors);
    } else {
      toasts.error(e.message || 'Failed to save test suite');
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  await Promise.all([loadTypes(), loadEnvironments()]);
  if (isEdit.value) await loadSuite();
  loading.value = false;
});
</script>
