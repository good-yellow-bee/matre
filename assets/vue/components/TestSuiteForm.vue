<template>
  <div class="test-suite-form">
    <ToastNotification />

    <form @submit.prevent="submitForm" novalidate>
      <!-- Suite Configuration Section -->
      <div class="form-section">
        <h3 class="form-section-title">Suite Configuration</h3>

        <div class="form-grid">
          <!-- Name -->
          <div class="mb-3">
            <label for="name" class="form-label">Name *</label>
            <input
              type="text"
              id="name"
              v-model="form.name"
              class="form-control"
              :class="validationClass('name')"
              @blur="validateName"
              maxlength="100"
              required
            />
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
            <div v-if="validation.name?.valid" class="valid-feedback">{{ validation.name.message }}</div>
          </div>

          <!-- Type -->
          <div class="mb-3">
            <label for="suite_type" class="form-label">Type *</label>
            <select
              id="suite_type"
              v-model="form.type"
              class="form-select"
              :class="{ 'is-invalid': errors.type }"
              @change="onTypeChange"
              required
            >
              <option value="">Select type...</option>
              <option v-for="t in suiteTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <div v-if="errors.type" class="invalid-feedback">{{ errors.type }}</div>
            <div v-if="suiteTypesError" class="text-warning small mt-1">
              <i class="bi bi-exclamation-triangle me-1"></i>{{ suiteTypesError }}
            </div>
          </div>
        </div>

        <!-- Test Pattern -->
        <div class="mb-3">
          <label class="form-label">Test/Group *</label>

          <!-- Playwright: simple text input -->
          <div v-if="isPlaywrightType" class="pattern-input-wrapper">
            <input
              type="text"
              v-model="form.testPattern"
              class="form-control"
              :class="{ 'is-invalid': errors.testPattern }"
              :placeholder="patternPlaceholder"
            />
          </div>

          <!-- MFTF: searchable dropdown -->
          <div v-else class="pattern-input-wrapper">
            <div class="input-group">
              <div class="searchable-select flex-grow-1" ref="selectContainer">
                <input
                  ref="searchInput"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': errors.testPattern }"
                  v-model="searchQuery"
                  @focus="openDropdown"
                  @blur="handleBlur"
                  @keydown="handleKeydown"
                  :placeholder="inputPlaceholder"
                  :disabled="patternLoading"
                />

                <div v-if="form.testPattern && !isOpen" class="selected-value" @click="clearAndFocus">
                  <span class="value-text">{{ form.testPattern }}</span>
                  <span class="clear-icon">&times;</span>
                </div>

                <div v-if="isOpen && !patternLoading" class="dropdown-list">
                  <div
                    v-for="(item, index) in filteredItems"
                    :key="item.value"
                    class="dropdown-option"
                    :class="{ active: index === highlightedIndex }"
                    @mousedown.prevent="selectItem(item)"
                    @mouseenter="highlightedIndex = index"
                  >
                    {{ item.label }}
                  </div>
                  <div v-if="filteredItems.length === 0" class="dropdown-option disabled">
                    No matches found
                  </div>
                </div>
              </div>

              <button
                type="button"
                class="btn btn-outline-secondary"
                @click="refreshPatterns"
                :disabled="patternRefreshing"
                title="Refresh test list from repository"
              >
                <span v-if="patternRefreshing" class="spinner-border spinner-border-sm"></span>
                <span v-else>&#8635;</span>
              </button>
            </div>
          </div>

          <div v-if="errors.testPattern" class="text-danger small mt-1">{{ errors.testPattern }}</div>
          <small v-if="patternMessage" class="text-muted d-block mt-1">{{ patternMessage }}</small>
          <small v-if="patternLastUpdated && !isPlaywrightType && patternCached" class="text-muted d-block mt-1">
            Updated: {{ formatDate(patternLastUpdated) }}
          </small>
        </div>

        <!-- Description -->
        <div class="mb-3">
          <label for="description" class="form-label">Description</label>
          <textarea
            id="description"
            v-model="form.description"
            class="form-control"
            rows="3"
          ></textarea>
        </div>
      </div>

      <!-- Schedule Section -->
      <div class="form-section">
        <h3 class="form-section-title">Schedule</h3>

        <!-- Cron Expression -->
        <div class="mb-3">
          <label for="cronExpression" class="form-label">Cron Expression</label>
          <input
            type="text"
            id="cronExpression"
            v-model="form.cronExpression"
            class="form-control"
            :class="validationClass('cronExpression')"
            @blur="validateCron"
            placeholder="e.g., 0 6 * * * (daily at 6am)"
          />
          <div v-if="errors.cronExpression" class="invalid-feedback">{{ errors.cronExpression }}</div>
          <div v-if="validation.cronExpression?.valid" class="valid-feedback">{{ validation.cronExpression.message }}</div>
          <small class="form-text text-muted">Leave empty for manual execution only</small>
        </div>

        <!-- Environments -->
        <div class="mb-3">
          <label class="form-label">Target Environments</label>
          <div v-if="availableEnvironments.length > 0" class="environment-grid">
            <div
              v-for="env in availableEnvironments"
              :key="env.id"
              class="environment-checkbox"
            >
              <input
                type="checkbox"
                :id="'env_' + env.id"
                :value="env.id"
                v-model="form.environments"
                class="form-check-input"
              />
              <label :for="'env_' + env.id" class="form-check-label">{{ env.name }}</label>
            </div>
          </div>
          <div v-else-if="environmentsError" class="text-warning small">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ environmentsError }}
          </div>
          <p v-else class="text-muted small">No active environments available</p>
          <small class="form-text text-muted">Select environments to run scheduled tests on</small>
        </div>
      </div>

      <!-- Status Section -->
      <div class="form-section">
        <h3 class="form-section-title">Status</h3>

        <div class="form-check mb-3">
          <input
            type="checkbox"
            id="isActive"
            v-model="form.isActive"
            class="form-check-input"
          />
          <label for="isActive" class="form-check-label">Active</label>
          <small class="form-text text-muted d-block">Inactive suites cannot be executed</small>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="bi bi-check-circle me-1"></i>
          {{ isEditMode ? 'Save Changes' : 'Create Suite' }}
        </button>
        <a :href="cancelUrl" class="btn btn-secondary">
          <i class="bi bi-x-circle me-1"></i> Cancel
        </a>
      </div>
    </form>

    <!-- Help Info -->
    <div class="mt-4">
      <div class="alert alert-info">
        <strong>Test Pattern Examples:</strong>
        <ul class="mb-0 mt-2">
          <li><strong>MFTF:</strong> <code>MOEC1625Test</code> (single test) or <code>checkout</code> (group)</li>
          <li><strong>Playwright:</strong> <code>@checkout</code> (tag) or <code>CheckoutTest</code> (file/test name)</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue';
import ToastNotification from './ToastNotification.vue';
import { useToast } from '../composables/useToast.js';

const props = defineProps({
  suiteId: { type: Number, default: null },
  apiUrl: { type: String, required: true },
  cancelUrl: { type: String, default: '/admin/test-suites' },
  testDiscoveryUrl: { type: String, default: '/api/test-discovery' },
  csrfToken: { type: String, default: '' },
});

const { showToast } = useToast();

const isEditMode = computed(() => props.suiteId !== null);

const form = reactive({
  name: '',
  type: '',
  testPattern: '',
  description: '',
  cronExpression: '',
  environments: [],
  isActive: true,
});

const errors = reactive({});
const validation = reactive({});
const submitting = ref(false);
const loading = ref(false);

// Suite types
const suiteTypes = ref([]);
const suiteTypesError = ref('');

// Available environments
const availableEnvironments = ref([]);
const environmentsError = ref('');

// Pattern selector state
const patternItems = ref([]);
const patternLoading = ref(false);
const patternRefreshing = ref(false);
const patternCached = ref(false);
const patternMessage = ref('');
const patternLastUpdated = ref(null);
const searchQuery = ref('');
const isOpen = ref(false);
const highlightedIndex = ref(0);
const searchInput = ref(null);
const selectContainer = ref(null);

const isPlaywrightType = computed(() => form.type.startsWith('playwright_'));

const patternPlaceholder = computed(() => {
  return form.type === 'playwright_group'
    ? 'Enter tag pattern (e.g., @checkout)'
    : 'Enter test name';
});

const inputPlaceholder = computed(() => {
  if (patternLoading.value) return 'Loading...';
  if (!patternCached.value) return 'Click refresh to load';
  if (form.testPattern && !isOpen.value) return '';
  return `Search ${patternItems.value.length} items...`;
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return patternItems.value;
  const query = searchQuery.value.toLowerCase();
  return patternItems.value.filter(item => item.label.toLowerCase().includes(query));
});

const validationClass = (field) => {
  if (errors[field]) return 'is-invalid';
  if (validation[field]?.valid) return 'is-valid';
  return '';
};

const clearError = (field) => {
  errors[field] = null;
  validation[field] = null;
};

// Fetch suite types
const fetchSuiteTypes = async () => {
  suiteTypesError.value = '';
  try {
    const response = await fetch(`${props.apiUrl}/types`);
    if (!response.ok) {
      suiteTypesError.value = 'Failed to load suite types';
      return;
    }
    suiteTypes.value = await response.json();
  } catch (error) {
    console.error('Failed to fetch suite types:', error);
    suiteTypesError.value = 'Network error loading suite types';
  }
};

// Fetch available environments
const fetchEnvironments = async () => {
  environmentsError.value = '';
  try {
    const response = await fetch(`${props.apiUrl}/environments`);
    if (!response.ok) {
      environmentsError.value = 'Failed to load environments';
      return;
    }
    availableEnvironments.value = await response.json();
  } catch (error) {
    console.error('Failed to fetch environments:', error);
    environmentsError.value = 'Network error loading environments';
  }
};

// Fetch suite data for edit mode
const fetchSuiteData = async () => {
  if (!props.suiteId) return;

  loading.value = true;
  try {
    const response = await fetch(`${props.apiUrl}/${props.suiteId}`);
    if (!response.ok) {
      showToast('Failed to load suite data', 'error');
      return;
    }

    const data = await response.json();
    form.name = data.name;
    form.type = data.type;
    form.testPattern = data.testPattern;
    form.description = data.description || '';
    form.cronExpression = data.cronExpression || '';
    form.environments = data.environments || [];
    form.isActive = data.isActive;
  } catch (error) {
    showToast('Failed to load suite data', 'error');
  } finally {
    loading.value = false;
  }
};

// Validation
const validateName = async () => {
  clearError('name');

  if (!form.name) {
    errors.name = 'Name is required';
    return;
  }

  if (form.name.length < 2) {
    errors.name = 'Name must be at least 2 characters';
    return;
  }

  try {
    const response = await fetch(`${props.apiUrl}/validate-name`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.name,
        excludeId: props.suiteId,
      }),
    });

    const data = await response.json();
    if (!data.valid) {
      errors.name = data.message;
    } else {
      validation.name = { valid: true, message: data.message };
    }
  } catch (error) {
    console.error('Validation error:', error);
    errors.name = 'Could not validate name. Check your connection.';
  }
};

const validateCron = async () => {
  clearError('cronExpression');

  if (!form.cronExpression) {
    return;
  }

  try {
    const response = await fetch(`${props.apiUrl}/validate-cron`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cronExpression: form.cronExpression }),
    });

    const data = await response.json();
    if (!data.valid) {
      errors.cronExpression = data.message;
    } else {
      validation.cronExpression = { valid: true, message: data.message };
    }
  } catch (error) {
    console.error('Validation error:', error);
    errors.cronExpression = 'Could not validate expression. Check your connection.';
  }
};

// Pattern selector methods
const onTypeChange = () => {
  form.testPattern = '';
  patternItems.value = [];
  patternCached.value = false;
  patternMessage.value = '';
  closeDropdown();
};

const openDropdown = () => {
  if (patternItems.value.length > 0) {
    isOpen.value = true;
    searchQuery.value = '';
    highlightedIndex.value = 0;
  }
};

const closeDropdown = () => {
  isOpen.value = false;
  searchQuery.value = '';
};

const handleBlur = () => {
  setTimeout(() => closeDropdown(), 200);
};

const selectItem = (item) => {
  form.testPattern = item.value;
  closeDropdown();
};

const clearAndFocus = () => {
  form.testPattern = '';
  nextTick(() => searchInput.value?.focus());
};

const handleKeydown = (event) => {
  if (!isOpen.value) {
    if (event.key === 'ArrowDown' || event.key === 'Enter') {
      openDropdown();
      event.preventDefault();
    }
    return;
  }

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredItems.value.length - 1);
      break;
    case 'ArrowUp':
      event.preventDefault();
      highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
      break;
    case 'Enter':
      event.preventDefault();
      if (filteredItems.value[highlightedIndex.value]) {
        selectItem(filteredItems.value[highlightedIndex.value]);
      }
      break;
    case 'Escape':
      closeDropdown();
      break;
  }
};

const refreshPatterns = async () => {
  if (!form.type || isPlaywrightType.value) return;

  patternRefreshing.value = true;
  patternMessage.value = '';

  try {
    const response = await fetch(props.testDiscoveryUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': props.csrfToken,
      },
      body: JSON.stringify({
        type: form.type,
        refresh: true,
      }),
    });

    if (!response.ok) {
      patternMessage.value = 'Failed to refresh test list';
      return;
    }

    const data = await response.json();
    patternItems.value = (data.items || []).map(item => ({
      value: item.value || item,
      label: item.label || item.value || item,
    }));
    patternCached.value = true;
    patternLastUpdated.value = data.lastUpdated || new Date().toISOString();
    patternMessage.value = `Loaded ${patternItems.value.length} items`;
  } catch (error) {
    patternMessage.value = 'Error loading test list';
  } finally {
    patternRefreshing.value = false;
  }
};

const loadCachedPatterns = async () => {
  if (!form.type || isPlaywrightType.value) return;

  patternLoading.value = true;

  try {
    const response = await fetch(props.testDiscoveryUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': props.csrfToken,
      },
      body: JSON.stringify({
        type: form.type,
        refresh: false,
      }),
    });

    if (!response.ok) return;

    const data = await response.json();
    if (data.cached && data.items?.length > 0) {
      patternItems.value = data.items.map(item => ({
        value: item.value || item,
        label: item.label || item.value || item,
      }));
      patternCached.value = true;
      patternLastUpdated.value = data.lastUpdated;
    }
  } catch (error) {
    console.error('Error loading cached patterns:', error);
  } finally {
    patternLoading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleString();
};

// Submit form
const submitForm = async () => {
  // Clear previous errors
  Object.keys(errors).forEach(key => errors[key] = null);

  // Basic validation
  if (!form.name) {
    errors.name = 'Name is required';
    return;
  }
  if (!form.type) {
    errors.type = 'Type is required';
    return;
  }
  if (!form.testPattern) {
    errors.testPattern = 'Test pattern is required';
    return;
  }

  submitting.value = true;

  try {
    const url = isEditMode.value ? `${props.apiUrl}/${props.suiteId}` : props.apiUrl;
    const method = isEditMode.value ? 'PUT' : 'POST';

    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form),
    });

    const data = await response.json();

    if (!response.ok) {
      if (data.errors) {
        Object.assign(errors, data.errors);
      } else {
        showToast(data.error || 'Failed to save test suite', 'error');
      }
      return;
    }

    showToast(data.message, 'success', true);

    setTimeout(() => {
      window.location.href = props.cancelUrl;
    }, 1500);
  } catch (error) {
    showToast('An error occurred while saving', 'error');
  } finally {
    submitting.value = false;
  }
};

// Watch type changes to load patterns
watch(() => form.type, (newType) => {
  if (newType && !isPlaywrightType.value) {
    loadCachedPatterns();
  }
});

// Initialize
onMounted(async () => {
  await Promise.all([
    fetchSuiteTypes(),
    fetchEnvironments(),
  ]);

  if (isEditMode.value) {
    await fetchSuiteData();
    // Load patterns if MFTF type
    if (form.type && !isPlaywrightType.value) {
      await loadCachedPatterns();
    }
  }
});
</script>

<style scoped>
.test-suite-form {
  --section-bg: #ffffff;
  --section-border: #e2e8f0;
  --label-color: #475569;
  --input-border: #cbd5e1;
  --input-focus: #3b82f6;
}

.form-section {
  background: var(--section-bg);
  border: 1px solid var(--section-border);
  border-radius: 0.75rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.form-section-title {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--section-border);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--label-color);
  margin-bottom: 0.375rem;
}

.form-control,
.form-select {
  border-color: var(--input-border);
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.form-control:focus,
.form-select:focus {
  border-color: var(--input-focus);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.environment-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.75rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 0.5rem;
  border: 1px solid var(--section-border);
}

.environment-checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.environment-checkbox .form-check-input {
  margin: 0;
}

.environment-checkbox .form-check-label {
  font-size: 0.875rem;
  cursor: pointer;
  margin: 0;
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  padding-top: 1rem;
}

/* Pattern selector styles */
.pattern-input-wrapper {
  position: relative;
}

.searchable-select {
  position: relative;
}

.selected-value {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  right: 2.5rem;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #e2e8f0;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  cursor: pointer;
}

.selected-value:hover {
  background: #cbd5e1;
}

.clear-icon {
  font-weight: bold;
  color: #64748b;
  margin-left: 0.5rem;
}

.dropdown-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 250px;
  overflow-y: auto;
  background: white;
  border: 1px solid var(--input-border);
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  margin-top: 0.25rem;
}

.dropdown-option {
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.dropdown-option:hover,
.dropdown-option.active {
  background: #f1f5f9;
}

.dropdown-option.disabled {
  color: #94a3b8;
  cursor: default;
}

.is-valid {
  border-color: #22c55e !important;
}

.is-invalid {
  border-color: #ef4444 !important;
}

.valid-feedback {
  color: #22c55e;
  font-size: 0.8rem;
  margin-top: 0.25rem;
  display: block;
}

.invalid-feedback {
  display: block;
}
</style>
