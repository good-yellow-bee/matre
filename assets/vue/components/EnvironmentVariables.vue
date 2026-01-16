<template>
  <div class="environment-variables">
    <!-- Global Variables Section (read-only) -->
    <div class="mb-4" v-if="globalVariables.length > 0">
      <h4 class="text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
        <i class="bi bi-globe"></i> Inherited Global Variables
        <span class="badge bg-secondary">{{ globalVariables.length }}</span>
      </h4>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 200px">Name</th>
              <th>Value</th>
              <th style="width: 150px">Used in Tests</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="v in globalVariables" :key="v.id" class="table-secondary">
              <td><code class="text-muted">{{ v.name }}</code></td>
              <td class="text-muted small">{{ truncate(v.value, 60) }}</td>
              <td class="text-muted small">{{ v.usedInTests || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-muted small mt-2">
        <i class="bi bi-info-circle"></i>
        Global variables are managed in <a href="/admin/env-variables" target="_blank">Env Variables</a>.
        Add environment-specific variables below to override them.
      </p>
    </div>

    <!-- Environment Variables Section (editable) -->
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h4 class="text-sm font-semibold text-slate-700 mb-0 flex items-center gap-2">
          <i class="bi bi-braces"></i> Environment-Specific Variables
          <span class="badge bg-primary">{{ envVariables.length }}</span>
        </h4>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-primary" @click="showImportModal = true">
            <i class="bi bi-upload"></i> Import
          </button>
          <button class="btn btn-primary" @click="addVariable">
            <i class="bi bi-plus"></i> Add
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 200px">Name</th>
              <th>Value</th>
              <th style="width: 150px">Used in Tests</th>
              <th style="width: 50px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(v, index) in envVariables" :key="v.id || index" :class="{ 'table-warning': v._dirty }">
              <td>
                <input
                  v-model="v.name"
                  type="text"
                  class="form-control form-control-sm font-monospace"
                  placeholder="VARIABLE_NAME"
                  @input="markDirty(v)"
                />
              </td>
              <td>
                <input
                  v-model="v.value"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="value"
                  @input="markDirty(v)"
                />
              </td>
              <td>
                <input
                  v-model="v.usedInTests"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="MOEC1676"
                  @input="markDirty(v)"
                />
              </td>
              <td class="text-center">
                <button @click="removeVariable(index)" class="btn btn-sm btn-outline-danger" title="Remove">
                  <i class="bi bi-x"></i>
                </button>
              </td>
            </tr>
            <tr v-if="envVariables.length === 0">
              <td colspan="4" class="text-center text-muted py-3">
                No environment-specific variables. Click "Add" to create one.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Save Button -->
    <div class="d-flex gap-2">
      <button
        class="btn btn-success"
        @click="saveVariables"
        :disabled="saving || !hasChanges"
      >
        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="bi bi-check-lg me-1"></i>
        Save Variables
      </button>
      <span v-if="hasChanges" class="text-warning align-self-center small">
        <i class="bi bi-exclamation-triangle"></i> Unsaved changes
      </span>
    </div>

    <!-- Import Modal -->
    <div
      v-if="showImportModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.5);"
      @click.self="showImportModal = false"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import from .env</h5>
            <button type="button" class="btn-close" @click="showImportModal = false"></button>
          </div>
          <div class="modal-body">
            <textarea
              v-model="importContent"
              class="form-control font-monospace"
              rows="8"
              placeholder="VARIABLE_NAME=value
ANOTHER_VAR=another_value"
            ></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showImportModal = false">Cancel</button>
            <button class="btn btn-primary" @click="handleImport" :disabled="!importContent.trim()">
              Import
            </button>
          </div>
        </div>
      </div>
    </div>

    <ToastNotification />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '../composables/useToast.js';
import ToastNotification from './ToastNotification.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  csrfToken: { type: String, required: true },
});

const { showToast } = useToast();

const globalVariables = ref([]);
const envVariables = ref([]);
const loading = ref(false);
const saving = ref(false);
const showImportModal = ref(false);
const importContent = ref('');

const hasChanges = computed(() => envVariables.value.some(v => v._dirty));

const fetchVariables = async () => {
  loading.value = true;
  try {
    const response = await fetch(props.apiUrl);
    if (!response.ok) throw new Error('Failed to load variables');
    const data = await response.json();
    globalVariables.value = data.global || [];
    envVariables.value = (data.environment || []).map(v => ({ ...v, _dirty: false }));
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    loading.value = false;
  }
};

const addVariable = () => {
  envVariables.value.push({
    id: null,
    name: '',
    value: '',
    usedInTests: '',
    _dirty: true,
  });
};

const removeVariable = (index) => {
  envVariables.value.splice(index, 1);
  // Mark as changed
  if (envVariables.value.length > 0) {
    envVariables.value[0]._dirty = true;
  }
};

const markDirty = (v) => {
  v._dirty = true;
};

const saveVariables = async () => {
  saving.value = true;
  try {
    const response = await fetch(props.apiUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': props.csrfToken,
      },
      body: JSON.stringify({
        variables: envVariables.value.filter(v => v.name.trim()).map(v => ({
          name: v.name,
          value: v.value,
          usedInTests: v.usedInTests || null,
        })),
      }),
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.error || 'Failed to save');
    }

    const data = await response.json();
    showToast(data.message, 'success');

    // Reset dirty flags
    envVariables.value.forEach(v => v._dirty = false);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    saving.value = false;
  }
};

const handleImport = async () => {
  try {
    const response = await fetch(props.apiUrl + '/import', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': props.csrfToken,
      },
      body: JSON.stringify({ content: importContent.value }),
    });

    if (!response.ok) throw new Error('Failed to parse');

    const data = await response.json();

    // Add parsed variables
    for (const v of data.variables) {
      const existing = envVariables.value.find(e => e.name === v.name);
      if (existing) {
        existing.value = v.value;
        existing._dirty = true;
      } else {
        envVariables.value.push({
          id: null,
          name: v.name,
          value: v.value,
          usedInTests: '',
          _dirty: true,
        });
      }
    }

    showToast(`Imported ${data.count} variable(s)`, 'success');
    showImportModal.value = false;
    importContent.value = '';
  } catch (err) {
    showToast(err.message, 'error');
  }
};

const truncate = (str, len) => {
  if (!str) return '';
  return str.length > len ? str.slice(0, len) + '...' : str;
};

onMounted(fetchVariables);
</script>

<style scoped>
.font-monospace {
  font-family: 'SF Mono', SFMono-Regular, ui-monospace, monospace;
  font-size: 0.85rem;
}

.modal.show { display: block; }
</style>
