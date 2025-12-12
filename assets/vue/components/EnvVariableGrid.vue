<template>
  <div class="env-variable-grid">
    <!-- Header with Search and Actions -->
    <div class="grid-header mb-4">
      <div class="row align-items-center g-3">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text">
              <i class="bi bi-search"></i>
            </span>
            <input
              v-model="searchQuery"
              type="text"
              class="form-control"
              placeholder="Search variables..."
            />
            <button v-if="searchQuery" class="btn btn-outline-secondary" @click="searchQuery = ''">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>
        <div class="col-md-8 text-end">
          <div class="btn-group me-2">
            <button class="btn btn-outline-primary" @click="showImportModal = true">
              <i class="bi bi-upload me-1"></i> Import .env
            </button>
            <button class="btn btn-primary" @click="addVariable">
              <i class="bi bi-plus-lg me-1"></i> Add Variable
            </button>
          </div>
          <button
            v-if="hasChanges"
            class="btn btn-success"
            @click="handleSave"
            :disabled="saving"
          >
            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="bi bi-check-lg me-1"></i>
            Save All
          </button>
          <button
            v-if="hasChanges"
            class="btn btn-outline-secondary ms-1"
            @click="discardChanges"
          >
            Discard
          </button>
        </div>
      </div>
    </div>

    <!-- Unsaved Changes Warning -->
    <div v-if="hasChanges" class="alert alert-warning py-2 mb-3">
      <i class="bi bi-exclamation-triangle me-2"></i>
      You have unsaved changes
    </div>

    <!-- Loading State -->
    <div v-if="loading && filteredVariables.length === 0" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-3">Loading variables...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      {{ error }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="fetchVariables">
        Try Again
      </button>
    </div>

    <!-- Variables Table -->
    <div v-else class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th @click="sort('name')" class="sortable" style="width: 220px">
                Name
                <i :class="getSortIcon('name')"></i>
              </th>
              <th>Value</th>
              <th @click="sort('usedInTests')" class="sortable" style="width: 180px">
                Used in Tests
                <i :class="getSortIcon('usedInTests')"></i>
              </th>
              <th style="width: 200px">Description</th>
              <th class="text-center" style="width: 60px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(variable, index) in filteredVariables"
              :key="variable.id || `new-${index}`"
              :class="{ 'table-warning': variable._dirty, 'table-danger': variable._deleted }"
            >
              <td>
                <input
                  v-model="variable.name"
                  type="text"
                  class="form-control form-control-sm font-monospace"
                  :class="{ 'is-invalid': !isValidName(variable.name) }"
                  placeholder="VARIABLE_NAME"
                  @input="markDirty(index)"
                  :disabled="variable._deleted"
                />
                <div v-if="!isValidName(variable.name) && variable.name" class="invalid-feedback">
                  Must be UPPERCASE with underscores
                </div>
              </td>
              <td>
                <input
                  v-model="variable.value"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="value"
                  @input="markDirty(index)"
                  :disabled="variable._deleted"
                />
              </td>
              <td>
                <input
                  v-model="variable.usedInTests"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="MOEC1676,MOEC1677"
                  @input="markDirty(index)"
                  :disabled="variable._deleted"
                />
              </td>
              <td>
                <input
                  v-model="variable.description"
                  type="text"
                  class="form-control form-control-sm"
                  placeholder="Optional description"
                  @input="markDirty(index)"
                  :disabled="variable._deleted"
                />
              </td>
              <td class="text-center">
                <button
                  v-if="!variable._deleted"
                  @click="removeVariable(index)"
                  class="btn btn-sm btn-outline-danger"
                  title="Delete variable"
                >
                  <i class="bi bi-trash"></i>
                </button>
                <button
                  v-else
                  @click="undoDelete(index)"
                  class="btn btn-sm btn-outline-secondary"
                  title="Undo delete"
                >
                  <i class="bi bi-arrow-counterclockwise"></i>
                </button>
              </td>
            </tr>
            <tr v-if="filteredVariables.length === 0">
              <td colspan="5" class="text-center py-4 text-muted">
                {{ searchQuery ? 'No variables match your search.' : 'No variables yet. Click "Add Variable" to create one.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer -->
      <div class="card-footer bg-white">
        <div class="text-muted small">
          {{ filteredVariables.length }} variable(s)
          <span v-if="filteredVariables.length !== variables.length">
            (filtered from {{ variables.length }})
          </span>
        </div>
      </div>
    </div>

    <!-- Import Modal -->
    <div
      v-if="showImportModal"
      class="modal fade show d-block"
      tabindex="-1"
      style="background: rgba(0, 0, 0, 0.5);"
      @click.self="showImportModal = false"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import from .env</h5>
            <button type="button" class="btn-close" @click="showImportModal = false"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-3">
              Paste your .env file content below. Variables will be parsed and added to the list.
              Existing variables with the same name will be updated.
            </p>
            <textarea
              v-model="importContent"
              class="form-control font-monospace"
              rows="12"
              placeholder="SELENIUM_HOST=selenium-hub
ALLURE_URL=http://allure:5050
MAGENTO_VERSION=2.4.6"
            ></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showImportModal = false">
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="handleImport"
              :disabled="!importContent.trim()"
            >
              <i class="bi bi-upload me-1"></i> Import
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      :class="['toast-notification', `toast-${toast.type}`]"
      role="alert"
    >
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useEnvVariableGrid } from '../composables/useEnvVariableGrid.js';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
  csrfToken: {
    type: String,
    required: true,
  },
});

const {
  variables,
  filteredVariables,
  loading,
  saving,
  error,
  searchQuery,
  sortField,
  sortOrder,
  hasChanges,
  fetchVariables,
  sort: doSort,
  addVariable,
  removeVariable: doRemoveVariable,
  saveAll,
  importFromEnv,
  discardChanges,
} = useEnvVariableGrid(props.apiUrl, props.csrfToken);

const showImportModal = ref(false);
const importContent = ref('');
const toast = ref({ show: false, message: '', type: 'success' });

const sort = (field) => {
  doSort(field);
};

const getSortIcon = (field) => {
  if (sortField.value !== field) {
    return 'bi bi-chevron-expand text-muted';
  }
  return sortOrder.value === 'asc' ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
};

const isValidName = (name) => {
  if (!name) return true; // Allow empty for new rows
  return /^[A-Z][A-Z0-9_]*$/.test(name);
};

const markDirty = (index) => {
  const variable = filteredVariables.value[index];
  if (variable) {
    variable._dirty = true;
    hasChanges.value = true;
  }
};

const removeVariable = (index) => {
  doRemoveVariable(index);
};

const undoDelete = (index) => {
  const variable = filteredVariables.value[index];
  if (variable) {
    delete variable._deleted;
    // Check if there are still changes
    hasChanges.value = variables.value.some(v => v._dirty || v._deleted);
  }
};

const handleSave = async () => {
  // Validate all variables
  const invalid = filteredVariables.value.filter(v => v.name && !isValidName(v.name));
  if (invalid.length > 0) {
    showToast('Please fix invalid variable names before saving', 'error');
    return;
  }

  const result = await saveAll();
  showToast(result.message, result.success ? 'success' : 'error');
};

const handleImport = async () => {
  const result = await importFromEnv(importContent.value);
  if (result.success) {
    showToast(`Imported ${result.count} variable(s)`, 'success');
    showImportModal.value = false;
    importContent.value = '';
  } else {
    showToast(result.message, 'error');
  }
};

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

onMounted(() => {
  fetchVariables();
});
</script>

<style scoped>
.env-variable-grid {
  width: 100%;
}

.sortable {
  cursor: pointer;
  user-select: none;
  transition: background-color 0.2s;
}

.sortable:hover {
  background-color: var(--neutral-100, #f1f5f9);
}

.sortable i {
  font-size: 0.75rem;
  margin-left: 0.25rem;
}

.table > :not(caption) > * > * {
  padding: 0.5rem;
}

.form-control-sm {
  font-size: 0.875rem;
}

.font-monospace {
  font-family: 'SF Mono', SFMono-Regular, ui-monospace, monospace;
}

.modal.show {
  display: block;
}

.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 12px 24px;
  border-radius: 8px;
  color: white;
  font-weight: 500;
  z-index: 9999;
  animation: slideIn 0.3s ease-out;
}

.toast-success {
  background-color: #10b981;
}

.toast-error {
  background-color: #ef4444;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
</style>
