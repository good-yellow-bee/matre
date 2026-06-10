<template>
  <div class="mx-auto max-w-4xl">
    <PageHeader
      :title="isEditMode ? `Edit Environment${form.name ? `: ${form.name}` : ''}` : 'Add Test Environment'"
      :subtitle="isEditMode ? 'Update environment configuration' : 'Configure a new target environment for tests'"
    >
      <template #actions>
        <RouterLink class="btn-ghost" :to="{ name: 'environments' }">
          <ArrowLeft class="h-4 w-4" />
          Back to List
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loading" class="card rise space-y-3 p-6" style="--i: 1">
      <div v-for="i in 5" :key="i" class="skeleton h-9" :style="{ width: `${100 - i * 8}%` }"></div>
    </div>

    <form v-else class="space-y-6" @submit.prevent="handleSubmit">
      <section class="card rise" style="--i: 1">
        <div class="border-b border-edge px-5 py-3.5">
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Environment Details</h2>
        </div>
        <div class="space-y-4 p-5">
          <div class="grid gap-4 md:grid-cols-3">
            <div>
              <label class="label" for="env-name">Environment Name <span class="text-fail">*</span></label>
              <input
                id="env-name"
                v-model="form.name"
                type="text"
                class="input"
                :class="fieldClass('name', nameValid)"
                placeholder="dev-us"
                maxlength="100"
                @input="handleNameInput"
                @blur="validateName"
              >
              <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
              <p v-else-if="nameValid && form.name" class="mt-1 text-xs text-pass">Name is available</p>
              <p class="mt-1 text-xs text-ink-faint">Unique name like "dev-us" or "stage-es"</p>
            </div>

            <div>
              <label class="label" for="env-code">Code <span class="text-fail">*</span></label>
              <input
                id="env-code"
                v-model="form.code"
                type="text"
                class="input font-mono"
                :class="fieldClass('code', codeValid)"
                placeholder="dev"
                maxlength="50"
                @input="handleCodeInput"
                @blur="validateCode"
              >
              <p v-if="errors.code" class="field-error">{{ errors.code }}</p>
              <p v-else-if="codeValid && form.code" class="mt-1 text-xs text-pass">Code is available</p>
              <p class="mt-1 text-xs text-ink-faint">Short code like "dev", "stage", "preprod"</p>
            </div>

            <div>
              <label class="label" for="env-region">Region <span class="text-fail">*</span></label>
              <input
                id="env-region"
                v-model="form.region"
                type="text"
                class="input"
                :class="fieldClass('region')"
                placeholder="us"
                maxlength="50"
              >
              <p v-if="errors.region" class="field-error">{{ errors.region }}</p>
              <p class="mt-1 text-xs text-ink-faint">Region code like "us", "es", "uk"</p>
            </div>
          </div>

          <div>
            <label class="label" for="env-baseurl">Base URL <span class="text-fail">*</span></label>
            <input
              id="env-baseurl"
              v-model="form.baseUrl"
              type="url"
              class="input font-mono"
              :class="fieldClass('baseUrl')"
              placeholder="https://dev-us.example.com/"
              maxlength="255"
            >
            <p v-if="errors.baseUrl" class="field-error">{{ errors.baseUrl }}</p>
            <p class="mt-1 text-xs text-ink-faint">Full URL to the Magento storefront</p>
          </div>

          <div>
            <label class="label" for="env-backend">Backend Name <span class="text-fail">*</span></label>
            <input
              id="env-backend"
              v-model="form.backendName"
              type="text"
              class="input font-mono"
              :class="fieldClass('backendName')"
              placeholder="admin"
              maxlength="100"
            >
            <p v-if="errors.backendName" class="field-error">{{ errors.backendName }}</p>
            <p class="mt-1 text-xs text-ink-faint">Admin panel path (typically "admin")</p>
          </div>
        </div>
      </section>

      <section class="card rise" style="--i: 2">
        <div class="border-b border-edge px-5 py-3.5">
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Additional Info</h2>
        </div>
        <div class="space-y-4 p-5">
          <div>
            <label class="label" for="env-description">Description</label>
            <textarea
              id="env-description"
              v-model="form.description"
              class="input"
              rows="3"
              placeholder="Describe this environment"
            ></textarea>
          </div>

          <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-edge bg-panel-2 p-3 transition-colors hover:border-edge-2">
            <input v-model="form.isActive" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-edge accent-(--m-accent)">
            <span>
              <span class="block text-sm font-semibold text-ink">Active</span>
              <span class="block text-xs text-ink-mute">{{ form.isActive ? 'Environment is available for testing' : 'Environment is disabled' }}</span>
            </span>
          </label>
        </div>
      </section>

      <div class="rise flex flex-wrap items-center gap-2" style="--i: 3">
        <button type="submit" class="btn-primary" :disabled="submitting || !isFormValid">
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <CheckCircle2 v-else class="h-4 w-4" />
          {{ isEditMode ? 'Update Environment' : 'Create Environment' }}
        </button>
        <RouterLink class="btn-ghost" :to="{ name: 'environments' }">
          <XCircle class="h-4 w-4" />
          Cancel
        </RouterLink>
        <button v-if="isEditMode && hasChanges" type="button" class="btn-ghost" @click="resetToOriginal">
          <RotateCcw class="h-4 w-4" />
          Reset
        </button>
      </div>

      <div v-if="Object.keys(errors).length > 0" class="rise rounded-lg border border-fail/30 bg-fail/10 p-4 text-sm text-fail">
        <p class="flex items-center gap-2 font-semibold">
          <AlertTriangle class="h-4 w-4" />
          Please fix the following errors:
        </p>
        <ul class="mt-2 list-inside list-disc space-y-0.5 text-xs">
          <li v-for="(error, field) in errors" :key="field">{{ error }}</li>
        </ul>
      </div>
    </form>

    <section v-if="isEditMode && !loading" class="card rise mt-6" style="--i: 4">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-edge px-5 py-3.5">
        <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-ink">
          <Braces class="h-4 w-4 text-accent" />
          Environment-Specific Variables
          <span class="badge border border-accent/25 bg-accent-soft text-accent">{{ envVariables.length }}</span>
        </h2>
        <div class="flex items-center gap-2">
          <button type="button" class="btn-ghost btn-sm" @click="showImportModal = true">
            <Upload class="h-3.5 w-3.5" />
            Import
          </button>
          <button type="button" class="btn-primary btn-sm" @click="addVariable">
            <Plus class="h-3.5 w-3.5" />
            Add
          </button>
        </div>
      </div>

      <div class="space-y-5 p-5">
        <div v-if="varsLoading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="skeleton h-8"></div>
        </div>
        <template v-else>
          <div v-if="globalVariables.length">
            <h3 class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink-mute">
              <Globe class="h-3.5 w-3.5" />
              Inherited Global Variables
              <span class="badge border border-edge-2 bg-panel-2 text-ink-mute">{{ globalVariables.length }}</span>
            </h3>
            <VariablesTable :variables="globalVariables" />
            <p class="mt-2 flex items-center gap-1.5 text-xs text-ink-faint">
              <Info class="h-3.5 w-3.5 shrink-0" />
              Global variables are managed in
              <RouterLink class="link" :to="{ name: 'env-variables' }">Env Variables</RouterLink>.
              Add environment-specific variables below to override them.
            </p>
          </div>

          <div class="overflow-x-auto rounded-lg border border-edge">
            <table class="w-full">
              <thead class="border-b border-edge bg-panel-2">
                <tr>
                  <th class="th-base w-64 py-2">Name</th>
                  <th class="th-base py-2">Value</th>
                  <th class="th-base w-44 py-2">Used in Tests</th>
                  <th class="th-base w-12 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-edge">
                <tr v-for="(variable, index) in envVariables" :key="variable.id || `new-${index}`" :class="{ 'bg-run/5': variable._dirty }">
                  <td class="px-3 py-2">
                    <input
                      v-model="variable.name"
                      type="text"
                      class="input px-2.5 py-1.5 font-mono text-xs"
                      placeholder="VARIABLE_NAME"
                      @input="variable._dirty = true"
                    >
                  </td>
                  <td class="px-3 py-2">
                    <input
                      v-model="variable.value"
                      type="text"
                      class="input px-2.5 py-1.5 font-mono text-xs"
                      placeholder="value"
                      @input="variable._dirty = true"
                    >
                  </td>
                  <td class="px-3 py-2">
                    <input
                      v-model="variable.usedInTests"
                      type="text"
                      class="input px-2.5 py-1.5 font-mono text-xs"
                      placeholder="MOEC1676"
                      @input="variable._dirty = true"
                    >
                  </td>
                  <td class="px-3 py-2 text-center">
                    <button type="button" class="btn-danger btn-sm" title="Remove" @click="removeVariable(index)">
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </td>
                </tr>
                <tr v-if="envVariables.length === 0">
                  <td colspan="4" class="px-4 py-6 text-center text-xs text-ink-faint">
                    No environment-specific variables. Click "Add" to create one.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center gap-3">
            <button type="button" class="btn-primary" :disabled="savingVars || !hasVarChanges" @click="saveVariables">
              <Loader2 v-if="savingVars" class="h-4 w-4 animate-spin" />
              <Check v-else class="h-4 w-4" />
              Save Variables
            </button>
            <span v-if="hasVarChanges" class="flex items-center gap-1.5 text-xs text-run">
              <AlertTriangle class="h-3.5 w-3.5" />
              Unsaved changes
            </span>
          </div>
        </template>
      </div>
    </section>

    <Modal :open="showImportModal" title="Import from .env" @close="closeImportModal">
      <textarea
        v-model="importContent"
        class="input font-mono text-xs"
        rows="8"
        :placeholder="'VARIABLE_NAME=value\nANOTHER_VAR=another_value'"
      ></textarea>
      <template #footer>
        <button type="button" class="btn-ghost btn-sm" @click="closeImportModal">Cancel</button>
        <button type="button" class="btn-primary btn-sm" :disabled="!importContent.trim() || importing" @click="handleImport">
          <Loader2 v-if="importing" class="h-3.5 w-3.5 animate-spin" />
          Import
        </button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  AlertTriangle, ArrowLeft, Braces, Check, CheckCircle2, Globe, Info,
  Loader2, Plus, RotateCcw, Upload, X, XCircle,
} from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import Modal from '../../components/ui/Modal.vue';
import VariablesTable from './components/VariablesTable.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const environmentId = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEditMode = computed(() => !!environmentId.value);

const form = reactive({
  name: '',
  code: '',
  region: '',
  baseUrl: '',
  backendName: 'admin',
  description: '',
  isActive: true,
});

const errors = reactive({});
const loading = ref(false);
const submitting = ref(false);
const originalData = ref(null);
const nameValid = ref(false);
const codeValid = ref(false);

const hasChanges = computed(() => {
  if (!originalData.value) return false;
  return Object.keys(originalData.value).some((key) => form[key] !== originalData.value[key]);
});

const isFormValid = computed(() => {
  if (!form.name || !form.code || !form.region || !form.baseUrl || !form.backendName) return false;
  return Object.keys(errors).length === 0;
});

function fieldClass(field, valid = false) {
  if (errors[field]) return 'border-fail focus:border-fail focus:ring-fail/25';
  if (valid && form[field]) return 'border-pass/60';
  return '';
}

function clearErrors() {
  Object.keys(errors).forEach((key) => delete errors[key]);
}

function handleNameInput() {
  nameValid.value = false;
  delete errors.name;
}

async function validateName() {
  if (!form.name) {
    errors.name = 'Name is required';
    nameValid.value = false;
    return;
  }
  if (form.name.length < 2) {
    errors.name = 'Name must be at least 2 characters';
    nameValid.value = false;
    return;
  }
  try {
    const result = await api.post('/api/test-environments/validate-name', { name: form.name, excludeId: environmentId.value });
    if (result.valid) {
      delete errors.name;
      nameValid.value = true;
    } else {
      errors.name = result.message;
      nameValid.value = false;
    }
  } catch {
    errors.name = 'Could not validate name. Check your connection.';
    nameValid.value = false;
  }
}

function handleCodeInput() {
  codeValid.value = false;
  delete errors.code;
}

async function validateCode() {
  if (!form.code) {
    errors.code = 'Code is required';
    codeValid.value = false;
    return;
  }
  try {
    const result = await api.post('/api/test-environments/validate-code', { code: form.code, excludeId: environmentId.value });
    if (result.valid) {
      delete errors.code;
      codeValid.value = true;
    } else {
      errors.code = result.message;
      codeValid.value = false;
    }
  } catch {
    errors.code = 'Could not validate code. Check your connection.';
    codeValid.value = false;
  }
}

async function fetchEnvironment() {
  loading.value = true;
  try {
    const data = await api.get(`/api/test-environments/${environmentId.value}`);
    form.name = data.name || '';
    form.code = data.code || '';
    form.region = data.region || '';
    form.baseUrl = data.baseUrl || '';
    form.backendName = data.backendName || 'admin';
    form.description = data.description || '';
    form.isActive = data.isActive ?? true;
    originalData.value = { ...form };
  } catch (e) {
    toasts.error('Failed to load environment');
    if (e.status === 404) {
      router.replace({ name: 'environments' });
      return;
    }
  } finally {
    loading.value = false;
  }
  await Promise.all([validateName(), validateCode(), fetchVariables()]);
}

function resetToOriginal() {
  if (!originalData.value) return;
  Object.assign(form, originalData.value);
  clearErrors();
  nameValid.value = false;
  codeValid.value = false;
}

async function handleSubmit() {
  clearErrors();
  await validateName();
  await validateCode();

  if (!form.region) errors.region = 'Region is required';
  if (!form.baseUrl) errors.baseUrl = 'Base URL is required';
  if (!form.backendName) errors.backendName = 'Backend name is required';

  if (!isFormValid.value) {
    toasts.error('Please fix all errors before submitting');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      name: form.name,
      code: form.code,
      region: form.region,
      baseUrl: form.baseUrl,
      backendName: form.backendName,
      description: form.description,
      isActive: form.isActive,
    };
    const result = isEditMode.value
      ? await api.put(`/api/test-environments/${environmentId.value}`, payload)
      : await api.post('/api/test-environments', payload);

    toasts.success(result.message);
    router.push({ name: 'environment-detail', params: { id: isEditMode.value ? environmentId.value : result.id } });
  } catch (e) {
    if (e.payload?.errors) Object.assign(errors, e.payload.errors);
    toasts.error(e.message);
  } finally {
    submitting.value = false;
  }
}

// --- Environment variables editor (edit mode) ---

const globalVariables = ref([]);
const envVariables = ref([]);
const varsLoading = ref(false);
const savingVars = ref(false);
const varsRemoved = ref(false);
const showImportModal = ref(false);
const importContent = ref('');
const importing = ref(false);

const hasVarChanges = computed(() => varsRemoved.value || envVariables.value.some((v) => v._dirty));

async function fetchVariables() {
  varsLoading.value = true;
  try {
    const data = await api.get(`/api/test-environments/${environmentId.value}/env-variables`);
    globalVariables.value = data.global || [];
    envVariables.value = (data.environment || []).map((v) => ({ ...v, usedInTests: v.usedInTests || '', _dirty: false }));
    varsRemoved.value = false;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    varsLoading.value = false;
  }
}

function addVariable() {
  envVariables.value.push({ id: null, name: '', value: '', usedInTests: '', _dirty: true });
}

function removeVariable(index) {
  envVariables.value.splice(index, 1);
  varsRemoved.value = true;
}

async function saveVariables() {
  savingVars.value = true;
  try {
    const result = await api.post(`/api/test-environments/${environmentId.value}/env-variables`, {
      variables: envVariables.value
        .filter((v) => v.name.trim())
        .map((v) => ({ name: v.name, value: v.value, usedInTests: v.usedInTests || null })),
    });
    toasts.success(result.message);
    envVariables.value.forEach((v) => { v._dirty = false; });
    varsRemoved.value = false;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    savingVars.value = false;
  }
}

function closeImportModal() {
  showImportModal.value = false;
}

async function handleImport() {
  importing.value = true;
  try {
    const data = await api.post(`/api/test-environments/${environmentId.value}/env-variables/import`, { content: importContent.value });
    for (const parsed of data.variables) {
      const existing = envVariables.value.find((v) => v.name === parsed.name);
      if (existing) {
        existing.value = parsed.value;
        existing._dirty = true;
      } else {
        envVariables.value.push({ id: null, name: parsed.name, value: parsed.value, usedInTests: '', _dirty: true });
      }
    }
    toasts.success(`Imported ${data.count} variable(s)`);
    showImportModal.value = false;
    importContent.value = '';
  } catch (e) {
    toasts.error(e.message);
  } finally {
    importing.value = false;
  }
}

onMounted(() => {
  if (isEditMode.value) fetchEnvironment();
});
</script>
