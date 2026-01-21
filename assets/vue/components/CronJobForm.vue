<template>
  <div class="cron-job-form-container">
    <form @submit.prevent="handleSubmit" class="cron-job-form">
      <!-- Job Configuration -->
      <div class="form-section">
        <h3 class="section-title">Job Configuration</h3>

        <div class="form-grid">
          <!-- Name Field -->
          <div class="mb-3">
            <label for="cron-name" class="form-label">
              Job Name <span class="text-danger">*</span>
            </label>
            <input
              id="cron-name"
              v-model="form.name"
              type="text"
              class="form-control"
              :class="{
                'is-invalid': errors.name,
                'is-valid': nameValid && !errors.name && form.name
              }"
              placeholder="e.g., Daily Cleanup"
              maxlength="100"
              :disabled="validatingName"
              @input="handleNameInput"
              @blur="validateName"
            />
            <div v-if="errors.name" class="invalid-feedback">
              {{ errors.name }}
            </div>
            <div v-if="nameValid && !errors.name && form.name" class="valid-feedback">
              Name is available
            </div>
            <small class="form-text text-muted">
              Unique name for this cron job (3-100 characters)
            </small>
          </div>

          <!-- Cron Expression Field -->
          <div class="mb-3">
            <label for="cron-expression" class="form-label">
              Cron Expression <span class="text-danger">*</span>
            </label>
            <input
              id="cron-expression"
              v-model="form.cronExpression"
              type="text"
              class="form-control"
              :class="{
                'is-invalid': errors.cronExpression,
                'is-valid': cronValid && !errors.cronExpression && form.cronExpression
              }"
              placeholder="e.g., 0 * * * * (hourly)"
              maxlength="100"
              @input="handleCronInput"
              @blur="validateCron"
            />
            <div v-if="errors.cronExpression" class="invalid-feedback">
              {{ errors.cronExpression }}
            </div>
            <div v-if="cronValid && cronMessage && !errors.cronExpression" class="valid-feedback">
              {{ cronMessage }}
            </div>
            <small class="form-text text-muted">
              Format: minute hour day month weekday
            </small>
          </div>
        </div>

        <!-- Command Field -->
        <div class="mb-3">
          <label for="cron-command" class="form-label">
            Console Command <span class="text-danger">*</span>
          </label>
          <div class="command-input-group">
            <select
              v-if="showCommandSelect"
              v-model="selectedCommand"
              class="form-select command-select"
              @change="handleCommandSelect"
            >
              <option value="">-- Select a command --</option>
              <option
                v-for="cmd in commands"
                :key="cmd.name"
                :value="cmd.name"
              >
                {{ cmd.name }}
              </option>
            </select>
            <input
              id="cron-command"
              v-model="form.command"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors.command }"
              placeholder="e.g., app:cleanup --days=30"
              maxlength="255"
            />
            <button
              type="button"
              class="btn btn-outline-secondary"
              @click="showCommandSelect = !showCommandSelect"
              tabindex="-1"
              title="Toggle command picker"
            >
              <i :class="['bi', showCommandSelect ? 'bi-chevron-up' : 'bi-list']"></i>
            </button>
          </div>
          <div v-if="errors.command" class="invalid-feedback d-block">
            {{ errors.command }}
          </div>
          <div v-if="commandsError" class="text-warning small mt-1">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ commandsError }}
          </div>
          <small class="form-text text-muted">
            Symfony console command with optional arguments
          </small>
        </div>

        <!-- Description Field -->
        <div class="mb-3">
          <label for="cron-description" class="form-label">Description</label>
          <textarea
            id="cron-description"
            v-model="form.description"
            class="form-control"
            rows="3"
            placeholder="Describe what this job does"
          ></textarea>
          <small class="form-text text-muted">
            Optional description of the job's purpose
          </small>
        </div>
      </div>

      <!-- Status -->
      <div class="form-section">
        <h3 class="section-title">Status</h3>
        <div class="toggle-row">
          <div class="form-check form-switch mb-0">
            <input
              id="cron-active"
              v-model="form.isActive"
              type="checkbox"
              class="form-check-input"
              role="switch"
            />
          </div>
          <div class="toggle-content">
            <strong>Active</strong>
            <span class="text-muted d-block small">{{ form.isActive ? 'Job will run on schedule' : 'Job is disabled' }}</span>
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
          {{ isEditMode ? 'Update Cron Job' : 'Create Cron Job' }}
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
  cronJobId: {
    type: Number,
    default: null,
  },
  apiUrl: {
    type: String,
    required: true,
  },
  cancelUrl: {
    type: String,
    default: '/admin/cron-jobs',
  },
});

const { showToast } = useToast();

const form = reactive({
  name: '',
  cronExpression: '* * * * *',
  command: '',
  description: '',
  isActive: true,
});

const errors = reactive({});
const submitting = ref(false);
const originalData = ref(null);
const commands = ref([]);
const commandsError = ref('');
const showCommandSelect = ref(false);
const selectedCommand = ref('');
const nameValid = ref(false);
const validatingName = ref(false);
const cronValid = ref(false);
const cronMessage = ref('');

const isEditMode = computed(() => !!props.cronJobId);

const hasChanges = computed(() => {
  if (!originalData.value) return false;
  return (
    form.name !== originalData.value.name ||
    form.cronExpression !== originalData.value.cronExpression ||
    form.command !== originalData.value.command ||
    form.description !== originalData.value.description ||
    form.isActive !== originalData.value.isActive
  );
});

const isFormValid = computed(() => {
  if (!form.name || !form.cronExpression || !form.command) return false;
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

  if (form.name.length < 3) {
    errors.name = 'Name must be at least 3 characters';
    nameValid.value = false;
    return;
  }

  validatingName.value = true;
  try {
    const response = await fetch(`${props.apiUrl}/validate-name`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: form.name, excludeId: props.cronJobId }),
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
  } finally {
    validatingName.value = false;
  }
};

const handleCronInput = () => {
  cronValid.value = false;
  cronMessage.value = '';
  delete errors.cronExpression;
};

const validateCron = async () => {
  if (!form.cronExpression) {
    errors.cronExpression = 'Cron expression is required';
    cronValid.value = false;
    return;
  }

  try {
    const response = await fetch(`${props.apiUrl}/validate-cron`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ expression: form.cronExpression }),
    });
    const result = await response.json();

    if (result.valid) {
      delete errors.cronExpression;
      cronValid.value = true;
      cronMessage.value = result.message;
    } else {
      errors.cronExpression = result.message;
      cronValid.value = false;
    }
  } catch (error) {
    console.error('Cron validation error:', error);
    errors.cronExpression = 'Could not validate expression. Check your connection.';
    cronValid.value = false;
  }
};

const handleCommandSelect = () => {
  if (selectedCommand.value) {
    form.command = selectedCommand.value;
  }
};

const fetchCommands = async () => {
  commandsError.value = '';
  try {
    const response = await fetch(`${props.apiUrl}/commands`);
    if (!response.ok) {
      commandsError.value = 'Failed to load command list';
      return;
    }
    commands.value = await response.json();
  } catch (error) {
    console.error('Failed to fetch commands:', error);
    commandsError.value = 'Network error loading commands';
  }
};

const fetchCronJob = async () => {
  try {
    const response = await fetch(`${props.apiUrl}/${props.cronJobId}`);
    if (!response.ok) throw new Error('Failed to fetch cron job');
    const data = await response.json();

    form.name = data.name || '';
    form.cronExpression = data.cronExpression || '* * * * *';
    form.command = data.command || '';
    form.description = data.description || '';
    form.isActive = data.isActive ?? true;

    originalData.value = {
      name: form.name,
      cronExpression: form.cronExpression,
      command: form.command,
      description: form.description,
      isActive: form.isActive,
    };

    // Validate fields
    await validateName();
    await validateCron();
  } catch (error) {
    console.error('Error fetching cron job:', error);
    showToast('Failed to load cron job', 'error');
  }
};

const resetToOriginal = () => {
  if (originalData.value) {
    form.name = originalData.value.name;
    form.cronExpression = originalData.value.cronExpression;
    form.command = originalData.value.command;
    form.description = originalData.value.description;
    form.isActive = originalData.value.isActive;
    clearErrors();
    nameValid.value = false;
    cronValid.value = false;
    cronMessage.value = '';
  }
};

const handleSubmit = async () => {
  clearErrors();

  // Validate all fields
  await validateName();
  await validateCron();

  if (!form.command) {
    errors.command = 'Command is required';
  }

  if (!isFormValid.value) {
    showToast('Please fix all errors before submitting', 'error');
    return;
  }

  submitting.value = true;

  try {
    const url = isEditMode.value ? `${props.apiUrl}/${props.cronJobId}` : props.apiUrl;
    const method = isEditMode.value ? 'PUT' : 'POST';

    const response = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: form.name,
        cronExpression: form.cronExpression,
        command: form.command,
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
  await fetchCommands();
  if (isEditMode.value) {
    await fetchCronJob();
  }
});
</script>

<style scoped>
.cron-job-form-container {
  --primary: #3b82f6;
  --primary-hover: #2563eb;
  --success: #10b981;
  --danger: #ef4444;
  --warning: #f59e0b;
  --slate-50: #f8fafc;
  --slate-100: #f1f5f9;
  --slate-200: #e2e8f0;
  --slate-300: #cbd5e1;
  --slate-500: #64748b;
  --slate-600: #475569;
  --slate-700: #334155;
  --slate-900: #0f172a;
}

.cron-job-form {
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

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.cron-job-form :deep(.form-label) {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--slate-700);
  margin-bottom: 0.375rem;
}

.cron-job-form :deep(.form-control),
.cron-job-form :deep(.form-select) {
  border: 1px solid var(--slate-200);
  border-radius: 8px;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  color: var(--slate-900);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.cron-job-form :deep(.form-control:focus),
.cron-job-form :deep(.form-select:focus) {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  outline: none;
}

.cron-job-form :deep(.form-control::placeholder) {
  color: var(--slate-300);
}

.cron-job-form :deep(.form-control.is-valid) {
  border-color: var(--success);
  background-image: none;
}

.cron-job-form :deep(.form-control.is-invalid) {
  border-color: var(--danger);
  background-image: none;
}

.command-input-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.command-input-group .command-select {
  margin-bottom: 0.25rem;
}

.command-input-group > div:last-child {
  display: flex;
  gap: 0;
}

.command-input-group input {
  flex: 1;
  border-top-right-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}

.command-input-group button {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
  border-left: 0;
  border: 1px solid var(--slate-200);
  background: var(--slate-50);
  color: var(--slate-600);
  padding: 0.625rem 1rem;
}

.command-input-group button:hover {
  background: var(--slate-100);
  color: var(--slate-900);
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

.cron-job-form :deep(.form-switch .form-check-input) {
  width: 2.75rem;
  height: 1.5rem;
  border-radius: 1rem;
  background-color: var(--slate-200);
  border: none;
  margin-left: 0;
  cursor: pointer;
}

.cron-job-form :deep(.form-switch .form-check-input:checked) {
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

.cron-job-form :deep(.btn-primary) {
  background: var(--primary);
  border: none;
  color: white;
}

.cron-job-form :deep(.btn-primary:hover:not(:disabled)) {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
}

.cron-job-form :deep(.btn-primary:disabled) {
  opacity: 0.6;
  cursor: not-allowed;
}

.cron-job-form :deep(.btn-secondary) {
  background: var(--slate-100);
  border: 1px solid var(--slate-200);
  color: var(--slate-700);
  text-decoration: none;
}

.cron-job-form :deep(.btn-secondary:hover) {
  background: var(--slate-200);
  color: var(--slate-900);
}

.cron-job-form :deep(.btn-outline-secondary) {
  background: transparent;
  border: 1px solid var(--slate-200);
  color: var(--slate-600);
}

.cron-job-form :deep(.btn-outline-secondary:hover) {
  background: var(--slate-50);
  border-color: var(--slate-300);
  color: var(--slate-900);
}

.cron-job-form :deep(.form-text) {
  font-size: 0.75rem;
  color: var(--slate-500);
  margin-top: 0.375rem;
}

.cron-job-form :deep(.invalid-feedback) {
  font-size: 0.75rem;
  color: var(--danger);
  margin-top: 0.375rem;
}

.cron-job-form :deep(.valid-feedback) {
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
  .cron-job-form {
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
