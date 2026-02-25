<template>
  <div class="test-env-form-container">
    <form @submit.prevent="handleSubmit" class="test-env-form">
      <!-- Environment Details -->
      <div class="form-section">
        <h3 class="section-title">Environment Details</h3>

        <div class="form-grid-3">
          <!-- Name Field -->
          <div class="mb-3">
            <label for="env-name" class="form-label">
              Environment Name <span class="text-danger">*</span>
            </label>
            <input
              id="env-name"
              v-model="form.name"
              type="text"
              class="form-control"
              :class="{
                'is-invalid': errors.name,
                'is-valid': nameValid && !errors.name && form.name
              }"
              placeholder="dev-us"
              maxlength="100"
              @input="handleNameInput"
              @blur="validateName"
            />
            <div v-if="errors.name" class="invalid-feedback">
              {{ errors.name }}
            </div>
            <div v-if="nameValid && !errors.name && form.name" class="valid-feedback">
              Name is available
            </div>
            <small class="form-text text-muted">Unique name like "dev-us" or "stage-es"</small>
          </div>

          <!-- Code Field -->
          <div class="mb-3">
            <label for="env-code" class="form-label">
              Code <span class="text-danger">*</span>
            </label>
            <input
              id="env-code"
              v-model="form.code"
              type="text"
              class="form-control"
              :class="{
                'is-invalid': errors.code,
                'is-valid': codeValid && !errors.code && form.code
              }"
              placeholder="dev"
              maxlength="50"
              @input="handleCodeInput"
              @blur="validateCode"
            />
            <div v-if="errors.code" class="invalid-feedback">
              {{ errors.code }}
            </div>
            <div v-if="codeValid && !errors.code && form.code" class="valid-feedback">
              Code is available
            </div>
            <small class="form-text text-muted">Short code like "dev", "stage", "preprod"</small>
          </div>

          <!-- Region Field -->
          <div class="mb-3">
            <label for="env-region" class="form-label">
              Region <span class="text-danger">*</span>
            </label>
            <input
              id="env-region"
              v-model="form.region"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors.region }"
              placeholder="us"
              maxlength="50"
            />
            <div v-if="errors.region" class="invalid-feedback">
              {{ errors.region }}
            </div>
            <small class="form-text text-muted">Region code like "us", "es", "uk"</small>
          </div>
        </div>

        <!-- Base URL Field -->
        <div class="mb-3">
          <label for="env-baseurl" class="form-label">
            Base URL <span class="text-danger">*</span>
          </label>
          <input
            id="env-baseurl"
            v-model="form.baseUrl"
            type="url"
            class="form-control"
            :class="{ 'is-invalid': errors.baseUrl }"
            placeholder="https://dev-us.example.com/"
            maxlength="255"
          />
          <div v-if="errors.baseUrl" class="invalid-feedback">
            {{ errors.baseUrl }}
          </div>
          <small class="form-text text-muted">Full URL to the Magento storefront</small>
        </div>

        <!-- Backend Name Field -->
        <div class="mb-3">
          <label for="env-backend" class="form-label">
            Backend Name <span class="text-danger">*</span>
          </label>
          <input
            id="env-backend"
            v-model="form.backendName"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': errors.backendName }"
            placeholder="admin"
            maxlength="100"
          />
          <div v-if="errors.backendName" class="invalid-feedback">
            {{ errors.backendName }}
          </div>
          <small class="form-text text-muted">Admin panel path (typically "admin")</small>
        </div>
      </div>

      <!-- Additional Info -->
      <div class="form-section">
        <h3 class="section-title">Additional Info</h3>

        <!-- Description -->
        <div class="mb-3">
          <label for="env-description" class="form-label">Description</label>
          <textarea
            id="env-description"
            v-model="form.description"
            class="form-control"
            rows="3"
            placeholder="Describe this environment"
          ></textarea>
        </div>

        <!-- Active Toggle -->
        <div class="toggle-row">
          <div class="form-check form-switch mb-0">
            <input
              id="env-active"
              v-model="form.isActive"
              type="checkbox"
              class="form-check-input"
              role="switch"
            />
          </div>
          <div class="toggle-content">
            <strong>Active</strong>
            <span class="text-muted d-block small">{{ form.isActive ? 'Environment is available for testing' : 'Environment is disabled' }}</span>
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="form-actions">
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="submitting || !isFormValid"
        >
          <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
          <i v-else class="bi bi-check-circle me-1"></i>
          {{ isEditMode ? 'Update Environment' : 'Create Environment' }}
        </button>

        <a :href="cancelUrl" class="btn btn-secondary">
          <i class="bi bi-x-circle me-1"></i>
          Cancel
        </a>

        <button
          v-if="isEditMode && hasChanges"
          type="button"
          class="btn btn-outline-secondary"
          @click="resetToOriginal"
        >
          <i class="bi bi-arrow-counterclockwise me-1"></i>
          Reset
        </button>
      </div>

      <!-- Validation Summary -->
      <div v-if="Object.keys(errors).length > 0" class="alert alert-danger mt-3">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
          <li v-for="(error, field) in errors" :key="field">{{ error }}</li>
        </ul>
      </div>
    </form>

    <ToastNotification />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from '../composables/useToast.js';
import ToastNotification from './ToastNotification.vue';

const props = defineProps({
  environmentId: {
    type: Number,
    default: null,
  },
  apiUrl: {
    type: String,
    required: true,
  },
  cancelUrl: {
    type: String,
    default: '/admin/test-environments',
  },
});

const { showToast } = useToast();

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
const submitting = ref(false);
const originalData = ref(null);
const nameValid = ref(false);
const codeValid = ref(false);

const isEditMode = computed(() => !!props.environmentId);

const hasChanges = computed(() => {
  if (!originalData.value) return false;
  return (
    form.name !== originalData.value.name ||
    form.code !== originalData.value.code ||
    form.region !== originalData.value.region ||
    form.baseUrl !== originalData.value.baseUrl ||
    form.backendName !== originalData.value.backendName ||
    form.description !== originalData.value.description ||
    form.isActive !== originalData.value.isActive
  );
});

const isFormValid = computed(() => {
  if (!form.name || !form.code || !form.region || !form.baseUrl || !form.backendName) return false;
  if (Object.keys(errors).length > 0) return false;
  return true;
});

const clearErrors = () => {
  Object.keys(errors).forEach(key => delete errors[key]);
};

const handleNameInput = () => {
  nameValid.value = false;
  delete errors.name;
};

const validateName = async () => {
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
    const response = await fetch(`${props.apiUrl}/validate-name`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: form.name, excludeId: props.environmentId }),
    });
    const result = await response.json();

    if (result.valid) {
      delete errors.name;
      nameValid.value = true;
    } else {
      errors.name = result.message;
      nameValid.value = false;
    }
  } catch (error) {
    console.error('Name validation error:', error);
    errors.name = 'Could not validate name. Check your connection.';
    nameValid.value = false;
  }
};

const handleCodeInput = () => {
  codeValid.value = false;
  delete errors.code;
};

const validateCode = async () => {
  if (!form.code) {
    errors.code = 'Code is required';
    codeValid.value = false;
    return;
  }

  try {
    const response = await fetch(`${props.apiUrl}/validate-code`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: form.code, excludeId: props.environmentId }),
    });
    const result = await response.json();

    if (result.valid) {
      delete errors.code;
      codeValid.value = true;
    } else {
      errors.code = result.message;
      codeValid.value = false;
    }
  } catch (error) {
    console.error('Code validation error:', error);
    errors.code = 'Could not validate code. Check your connection.';
    codeValid.value = false;
  }
};

const fetchEnvironment = async () => {
  try {
    const response = await fetch(`${props.apiUrl}/${props.environmentId}`);
    if (!response.ok) throw new Error('Failed to fetch environment');
    const data = await response.json();

    form.name = data.name || '';
    form.code = data.code || '';
    form.region = data.region || '';
    form.baseUrl = data.baseUrl || '';
    form.backendName = data.backendName || 'admin';
    form.description = data.description || '';
    form.isActive = data.isActive ?? true;

    originalData.value = { ...form };

    await validateName();
    await validateCode();
  } catch (error) {
    console.error('Error fetching environment:', error);
    showToast('Failed to load environment', 'error');
  }
};

const resetToOriginal = () => {
  if (originalData.value) {
    Object.assign(form, originalData.value);
    clearErrors();
    nameValid.value = false;
    codeValid.value = false;
  }
};

const handleSubmit = async () => {
  clearErrors();

  await validateName();
  await validateCode();

  if (!form.region) {
    errors.region = 'Region is required';
  }
  if (!form.baseUrl) {
    errors.baseUrl = 'Base URL is required';
  }
  if (!form.backendName) {
    errors.backendName = 'Backend name is required';
  }

  if (!isFormValid.value) {
    showToast('Please fix all errors before submitting', 'error');
    return;
  }

  submitting.value = true;

  try {
    const url = isEditMode.value ? `${props.apiUrl}/${props.environmentId}` : props.apiUrl;
    const method = isEditMode.value ? 'PUT' : 'POST';

    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.name,
        code: form.code,
        region: form.region,
        baseUrl: form.baseUrl,
        backendName: form.backendName,
        description: form.description,
        isActive: form.isActive,
      }),
    });

    const data = await response.json();

    if (response.ok) {
      showToast(data.message, 'success', true);
      window.location.href = props.cancelUrl;
    } else {
      if (data.errors) {
        Object.assign(errors, data.errors);
      }
      showToast(data.error || 'An error occurred', 'error');
    }
  } catch (error) {
    console.error('Submit error:', error);
    showToast('Network error occurred', 'error');
  } finally {
    submitting.value = false;
  }
};

onMounted(async () => {
  if (isEditMode.value) {
    await fetchEnvironment();
  }
});
</script>

<style scoped>
.test-env-form-container {
  --primary: #3b82f6;
  --primary-hover: #2563eb;
  --success: #10b981;
  --danger: #ef4444;
  --slate-50: #f8fafc;
  --slate-100: #f1f5f9;
  --slate-200: #e2e8f0;
  --slate-300: #cbd5e1;
  --slate-500: #64748b;
  --slate-600: #475569;
  --slate-700: #334155;
  --slate-900: #0f172a;
}

.test-env-form {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  border: 1px solid var(--slate-200);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.section-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--slate-900);
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--slate-200);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-title::before {
  content: '';
  width: 4px;
  height: 1rem;
  background: var(--primary);
  border-radius: 2px;
}

.form-section {
  background: var(--slate-50);
  padding: 1.5rem;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  border: 1px solid var(--slate-100);
}

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

@media (max-width: 768px) {
  .form-grid-2,
  .form-grid-3 {
    grid-template-columns: 1fr;
  }
}

.test-env-form :deep(.form-label) {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--slate-700);
  margin-bottom: 0.375rem;
}

.test-env-form :deep(.form-control) {
  border: 1px solid var(--slate-200);
  border-radius: 8px;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  color: var(--slate-900);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.test-env-form :deep(.form-control:focus) {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  outline: none;
}

.test-env-form :deep(.form-control::placeholder) {
  color: var(--slate-300);
}

.test-env-form :deep(.form-control.is-valid) {
  border-color: var(--success);
  background-image: none;
}

.test-env-form :deep(.form-control.is-invalid) {
  border-color: var(--danger);
  background-image: none;
}

.toggle-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid var(--slate-200);
}

.toggle-row:hover {
  border-color: var(--primary);
  background: rgba(59, 130, 246, 0.02);
}

.toggle-content strong {
  display: block;
  font-size: 0.875rem;
  color: var(--slate-900);
  font-weight: 500;
}

.test-env-form :deep(.form-switch .form-check-input) {
  width: 2.75rem;
  height: 1.5rem;
  border-radius: 1rem;
  background-color: var(--slate-200);
  border: none;
  margin-left: 0;
  cursor: pointer;
}

.test-env-form :deep(.form-switch .form-check-input:checked) {
  background-color: var(--success);
}

.form-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  padding-top: 1.25rem;
  margin-top: 0.5rem;
  border-top: 1px solid var(--slate-200);
}

.form-actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-weight: 500;
  font-size: 0.875rem;
  padding: 0.625rem 1.25rem;
  transition: all 0.15s ease;
}

.test-env-form :deep(.btn-primary) {
  background: var(--primary);
  border: none;
  color: white;
}

.test-env-form :deep(.btn-primary:hover:not(:disabled)) {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
}

.test-env-form :deep(.btn-primary:disabled) {
  opacity: 0.6;
  cursor: not-allowed;
}

.test-env-form :deep(.btn-secondary) {
  background: var(--slate-100);
  border: 1px solid var(--slate-200);
  color: var(--slate-700);
  text-decoration: none;
}

.test-env-form :deep(.btn-secondary:hover) {
  background: var(--slate-200);
  color: var(--slate-900);
}

.test-env-form :deep(.btn-outline-secondary) {
  background: transparent;
  border: 1px solid var(--slate-200);
  color: var(--slate-600);
}

.test-env-form :deep(.btn-outline-secondary:hover) {
  background: var(--slate-50);
  border-color: var(--slate-300);
  color: var(--slate-900);
}

.test-env-form :deep(.form-text) {
  font-size: 0.75rem;
  color: var(--slate-500);
  margin-top: 0.375rem;
}

.test-env-form :deep(.invalid-feedback) {
  font-size: 0.75rem;
  color: var(--danger);
  margin-top: 0.375rem;
}

.test-env-form :deep(.valid-feedback) {
  font-size: 0.75rem;
  color: var(--success);
  margin-top: 0.375rem;
}

.alert-danger {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  color: #991b1b;
  padding: 1rem;
  font-size: 0.875rem;
}

.spinner-border-sm {
  width: 1rem;
  height: 1rem;
  border-width: 0.15em;
}

@media (max-width: 768px) {
  .test-env-form {
    padding: 1.25rem;
  }

  .form-section {
    padding: 1rem;
  }

  .form-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .form-actions .btn {
    width: 100%;
  }
}
</style>
