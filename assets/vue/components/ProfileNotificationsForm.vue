<template>
  <div class="notifications-form-container">
    <form @submit.prevent="handleSubmit" class="notifications-form">
      <!-- Enable Notifications -->
      <div class="form-section">
        <h3 class="section-title">Enable Notifications</h3>
        <div class="toggle-row">
          <div class="form-check form-switch mb-0">
            <input
              id="notifications-enabled"
              v-model="form.notificationsEnabled"
              type="checkbox"
              class="form-check-input"
              role="switch"
            />
          </div>
          <div class="toggle-content">
            <strong>Enable Notifications</strong>
            <span class="text-muted d-block small">Master toggle for all notifications</span>
          </div>
        </div>
      </div>

      <!-- Notification Options (shown when enabled) -->
      <template v-if="form.notificationsEnabled">
        <!-- Notification Trigger -->
        <div class="form-section">
          <h3 class="section-title">Notification Trigger</h3>
          <div class="mb-3">
            <label class="form-label">Notify When</label>
            <div class="trigger-options">
              <label class="trigger-option">
                <input
                  v-model="form.notificationTrigger"
                  type="radio"
                  value="failures"
                  class="form-check-input"
                />
                <span>Only failures</span>
              </label>
              <label class="trigger-option">
                <input
                  v-model="form.notificationTrigger"
                  type="radio"
                  value="all"
                  class="form-check-input"
                />
                <span>All test runs</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Email Notifications -->
        <div class="form-section">
          <h3 class="section-title">Email Notifications</h3>
          <div class="channel-card">
            <label class="flex items-center gap-2 cursor-pointer mb-2">
              <input
                id="notify-email"
                v-model="form.notifyByEmail"
                type="checkbox"
                class="form-check-input"
              />
              <span class="font-medium"><i class="bi bi-envelope me-1"></i> Email</span>
            </label>
            <div class="text-sm text-muted">
              Receive email notifications to <strong>{{ userEmail }}</strong>
            </div>
          </div>
        </div>

        <!-- Environments -->
        <div v-if="environments.length > 0" class="form-section">
          <h3 class="section-title">Environments</h3>
          <p class="text-sm text-muted mb-3">Select which test environments you want to receive notifications for</p>
          <div class="environment-options">
            <label
              v-for="env in environments"
              :key="env.id"
              class="environment-option"
            >
              <input
                v-model="form.notificationEnvironments"
                type="checkbox"
                class="form-check-input"
                :value="env.id"
              />
              <span>{{ env.name }}</span>
            </label>
          </div>
        </div>
        <div v-else-if="environmentsError" class="form-section">
          <h3 class="section-title">Environments</h3>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ environmentsError }}
          </div>
        </div>
        <div v-else class="form-section">
          <h3 class="section-title">Environments</h3>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No test environments configured. Create environments first to enable notifications.
          </div>
        </div>
      </template>

      <!-- Form Actions -->
      <div class="form-actions">
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="submitting"
        >
          <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
          <i v-else class="bi bi-check-circle me-1"></i>
          Save Preferences
        </button>

        <a :href="cancelUrl" class="btn btn-secondary">
          <i class="bi bi-x-circle me-1"></i>
          Cancel
        </a>

        <button
          v-if="hasChanges"
          type="button"
          class="btn btn-outline-secondary"
          @click="resetToOriginal"
        >
          <i class="bi bi-arrow-counterclockwise me-1"></i>
          Reset
        </button>
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
  apiUrl: {
    type: String,
    required: true,
  },
  environmentsUrl: {
    type: String,
    required: true,
  },
  cancelUrl: {
    type: String,
    default: '/admin/dashboard',
  },
  userEmail: {
    type: String,
    required: true,
  },
});

const { showToast } = useToast();

const form = reactive({
  notificationsEnabled: false,
  notificationTrigger: 'failures',
  notifyByEmail: false,
  notificationEnvironments: [],
});

const originalData = ref(null);
const environments = ref([]);
const environmentsError = ref('');
const submitting = ref(false);
const loading = ref(true);

const hasChanges = computed(() => {
  if (!originalData.value) return false;
  return (
    form.notificationsEnabled !== originalData.value.notificationsEnabled ||
    form.notificationTrigger !== originalData.value.notificationTrigger ||
    form.notifyByEmail !== originalData.value.notifyByEmail ||
    JSON.stringify(form.notificationEnvironments.slice().sort()) !==
      JSON.stringify(originalData.value.notificationEnvironments.slice().sort())
  );
});

const resetToOriginal = () => {
  if (originalData.value) {
    form.notificationsEnabled = originalData.value.notificationsEnabled;
    form.notificationTrigger = originalData.value.notificationTrigger;
    form.notifyByEmail = originalData.value.notifyByEmail;
    form.notificationEnvironments = [...originalData.value.notificationEnvironments];
  }
};

const fetchNotifications = async () => {
  try {
    const response = await fetch(props.apiUrl);
    if (!response.ok) throw new Error('Failed to fetch notifications');
    const data = await response.json();

    form.notificationsEnabled = data.notificationsEnabled ?? false;
    form.notificationTrigger = data.notificationTrigger || 'failures';
    form.notifyByEmail = data.notifyByEmail ?? false;
    form.notificationEnvironments = data.notificationEnvironments || [];

    originalData.value = {
      notificationsEnabled: form.notificationsEnabled,
      notificationTrigger: form.notificationTrigger,
      notifyByEmail: form.notifyByEmail,
      notificationEnvironments: [...form.notificationEnvironments],
    };
  } catch (error) {
    console.error('Error fetching notifications:', error);
    showToast('Failed to load notification settings', 'error');
  }
};

const fetchEnvironments = async () => {
  environmentsError.value = '';
  try {
    const response = await fetch(props.environmentsUrl);
    if (!response.ok) {
      environmentsError.value = 'Failed to load environments';
      return;
    }
    environments.value = await response.json();
  } catch (error) {
    console.error('Failed to fetch environments:', error);
    environmentsError.value = 'Network error loading environments';
  }
};

const handleSubmit = async () => {
  submitting.value = true;

  try {
    const response = await fetch(props.apiUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        notificationsEnabled: form.notificationsEnabled,
        notificationTrigger: form.notificationTrigger,
        notifyByEmail: form.notifyByEmail,
        notificationEnvironments: form.notificationEnvironments,
      }),
    });

    const data = await response.json();

    if (response.ok) {
      showToast(data.message || 'Settings saved successfully', 'success');
      originalData.value = {
        notificationsEnabled: form.notificationsEnabled,
        notificationTrigger: form.notificationTrigger,
        notifyByEmail: form.notifyByEmail,
        notificationEnvironments: [...form.notificationEnvironments],
      };
    } else {
      showToast(data.error || 'Failed to save settings', 'error');
    }
  } catch (error) {
    console.error('Error saving notifications:', error);
    showToast('Network error occurred', 'error');
  } finally {
    submitting.value = false;
  }
};

onMounted(async () => {
  await Promise.all([fetchEnvironments(), fetchNotifications()]);
  loading.value = false;
});
</script>

<style scoped>
.notifications-form-container {
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

.notifications-form {
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

.toggle-row .form-check {
  padding-left: 0;
}

.notifications-form :deep(.form-switch .form-check-input) {
  width: 2.75rem;
  height: 1.5rem;
  border-radius: 1rem;
  background-color: var(--slate-200);
  border: none;
  margin-left: 0;
  cursor: pointer;
}

.notifications-form :deep(.form-switch .form-check-input:checked) {
  background-color: var(--success);
}

.trigger-options {
  display: flex;
  gap: 1.5rem;
}

.trigger-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid var(--slate-200);
  cursor: pointer;
  transition: all 0.15s ease;
}

.trigger-option:hover {
  border-color: var(--primary);
  background: rgba(59, 130, 246, 0.02);
}

.trigger-option span {
  font-size: 0.875rem;
  color: var(--slate-700);
}

.channel-card {
  padding: 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid var(--slate-200);
  max-width: 400px;
}

.environment-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.environment-option {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid var(--slate-200);
  cursor: pointer;
  transition: all 0.15s ease;
}

.environment-option:hover {
  border-color: var(--primary);
  background: rgba(59, 130, 246, 0.02);
}

.environment-option span {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--slate-700);
}

.notifications-form :deep(.form-check-input) {
  width: 1.125rem;
  height: 1.125rem;
  border: 1.5px solid var(--slate-300);
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.notifications-form :deep(.form-check-input:checked) {
  background-color: var(--primary);
  border-color: var(--primary);
}

.notifications-form :deep(.form-check-input[type="radio"]) {
  border-radius: 50%;
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

.notifications-form :deep(.btn-primary) {
  background: var(--primary);
  border: none;
  color: white;
}

.notifications-form :deep(.btn-primary:hover:not(:disabled)) {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
}

.notifications-form :deep(.btn-primary:disabled) {
  opacity: 0.6;
  cursor: not-allowed;
}

.notifications-form :deep(.btn-secondary) {
  background: var(--slate-100);
  border: 1px solid var(--slate-200);
  color: var(--slate-700);
  text-decoration: none;
}

.notifications-form :deep(.btn-secondary:hover) {
  background: var(--slate-200);
  color: var(--slate-900);
}

.notifications-form :deep(.btn-outline-secondary) {
  background: transparent;
  border: 1px solid var(--slate-200);
  color: var(--slate-600);
}

.notifications-form :deep(.btn-outline-secondary:hover) {
  background: var(--slate-50);
  border-color: var(--slate-300);
  color: var(--slate-900);
}

.alert-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  color: #92400e;
  padding: 1rem;
  font-size: 0.875rem;
}

.spinner-border-sm {
  width: 1rem;
  height: 1rem;
  border-width: 0.15em;
}

@media (max-width: 768px) {
  .notifications-form {
    padding: 1.25rem;
  }

  .form-section {
    padding: 1rem;
  }

  .trigger-options {
    flex-direction: column;
    gap: 0.75rem;
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
