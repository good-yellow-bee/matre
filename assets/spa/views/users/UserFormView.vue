<template>
  <div class="mx-auto max-w-3xl">
    <PageHeader
      :title="isEditMode ? 'Edit User' : 'Create New User'"
      :subtitle="isEditMode
        ? (loadedUser ? `Modify user account: ${loadedUser.username} (${loadedUser.email})` : 'Modify user account')
        : 'Add a new user to the system'"
    >
      <template #actions>
        <RouterLink :to="{ name: 'users' }" class="btn-ghost">
          <ArrowLeft class="h-4 w-4" />
          Back to List
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loadingUser" class="space-y-4">
      <div class="skeleton h-64 w-full"></div>
    </div>

    <EmptyState v-else-if="loadFailed" title="User not found" message="This user does not exist or could not be loaded.">
      <RouterLink :to="{ name: 'users' }" class="btn-ghost btn-sm mt-2">
        <ArrowLeft class="h-3.5 w-3.5" />
        Back to Users
      </RouterLink>
    </EmptyState>

    <form v-else class="card rise space-y-6 p-6" style="--i: 1" @submit.prevent="handleSubmit">
      <div>
        <label class="label" for="user-username">Username <span class="text-fail">*</span></label>
        <input
          id="user-username"
          v-model="form.username"
          type="text"
          class="input"
          :class="fieldClass(errors.username, usernameValid && form.username)"
          placeholder="Enter username"
          maxlength="25"
          :disabled="validatingUsername"
          @input="handleUsernameInput"
          @blur="validateUsername"
        >
        <p v-if="errors.username" class="field-error">{{ errors.username }}</p>
        <p v-else-if="usernameValid && form.username" class="mt-1 text-xs text-pass">{{ usernameValidMessage }}</p>
        <p class="mt-1 text-xs text-ink-faint">3-25 characters, letters, numbers, underscores, hyphens only</p>
      </div>

      <div>
        <label class="label" for="user-email">Email <span class="text-fail">*</span></label>
        <input
          id="user-email"
          v-model="form.email"
          type="email"
          class="input"
          :class="fieldClass(errors.email, emailValid && form.email)"
          placeholder="user@example.com"
          maxlength="180"
          :disabled="validatingEmail"
          @input="handleEmailInput"
          @blur="validateEmail"
        >
        <p v-if="errors.email" class="field-error">{{ errors.email }}</p>
        <p v-else-if="emailValid && form.email" class="mt-1 text-xs text-pass">{{ emailValidMessage }}</p>
        <p class="mt-1 text-xs text-ink-faint">Valid email address (max 180 characters)</p>
      </div>

      <section class="rounded-lg border border-edge bg-panel-2/50 p-4">
        <h3 class="mb-4 flex items-center gap-2 border-b border-edge pb-2.5 text-xs font-bold uppercase tracking-wider text-ink">
          <span class="h-3.5 w-1 rounded-full bg-accent"></span>
          {{ isEditMode ? 'Change Password (Optional)' : 'Password' }}
          <span v-if="!isEditMode" class="text-fail">*</span>
        </h3>

        <div>
          <label class="label" for="user-password">
            {{ isEditMode ? 'New Password' : 'Password' }}
            <span v-if="!isEditMode" class="text-fail">*</span>
          </label>
          <div class="relative">
            <input
              id="user-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="input pr-10"
              :class="fieldClass(errors.password, false)"
              :placeholder="isEditMode ? 'Leave blank to keep current password' : 'Enter password'"
              autocomplete="new-password"
              @input="handlePasswordInput"
            >
            <button
              type="button"
              tabindex="-1"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer rounded p-0.5 text-ink-faint hover:text-ink"
              :title="showPassword ? 'Hide password' : 'Show password'"
              @click="showPassword = !showPassword"
            >
              <component :is="showPassword ? EyeOff : Eye" class="h-4 w-4" />
            </button>
          </div>
          <p v-if="errors.password" class="field-error">{{ errors.password }}</p>
          <p v-if="!isEditMode" class="mt-1 text-xs text-ink-faint">
            Minimum 8 characters with uppercase, lowercase, and a number
          </p>
          <PasswordStrength v-if="form.password" :password="form.password" />
        </div>

        <div v-if="form.password" class="mt-4">
          <label class="label" for="user-password-confirm">Confirm Password <span class="text-fail">*</span></label>
          <input
            id="user-password-confirm"
            v-model="form.passwordConfirm"
            :type="showPassword ? 'text' : 'password'"
            class="input"
            :class="fieldClass(errors.passwordConfirm || (form.passwordConfirm && form.password !== form.passwordConfirm), form.passwordConfirm && form.password === form.passwordConfirm)"
            placeholder="Confirm password"
            autocomplete="new-password"
            @input="delete errors.passwordConfirm"
          >
          <p v-if="errors.passwordConfirm" class="field-error">{{ errors.passwordConfirm }}</p>
          <p v-else-if="form.passwordConfirm && form.password !== form.passwordConfirm" class="field-error">Passwords do not match</p>
          <p v-else-if="form.passwordConfirm && form.password === form.passwordConfirm" class="mt-1 text-xs text-pass">Passwords match</p>
        </div>
      </section>

      <section class="rounded-lg border border-edge bg-panel-2/50 p-4">
        <h3 class="mb-4 flex items-center gap-2 border-b border-edge pb-2.5 text-xs font-bold uppercase tracking-wider text-ink">
          <span class="h-3.5 w-1 rounded-full bg-accent"></span>
          Roles &amp; Permissions
        </h3>
        <div class="space-y-3">
          <label class="flex cursor-not-allowed items-start gap-3 rounded-lg border border-edge bg-panel p-3 opacity-70">
            <input v-model="form.roles" type="checkbox" value="ROLE_USER" disabled class="mt-0.5 h-4 w-4 rounded border-edge accent-(--m-accent)">
            <span>
              <span class="block text-sm font-semibold text-ink">User</span>
              <span class="text-xs text-ink-faint">Basic user access (always enabled)</span>
            </span>
          </label>
          <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-edge bg-panel p-3 transition-colors hover:border-accent/50">
            <input v-model="form.roles" type="checkbox" value="ROLE_ADMIN" class="mt-0.5 h-4 w-4 rounded border-edge accent-(--m-accent)">
            <span>
              <span class="block text-sm font-semibold text-ink">Admin</span>
              <span class="text-xs text-ink-faint">Full administrative access to manage content and users</span>
            </span>
          </label>
        </div>
      </section>

      <div class="rounded-lg border border-edge bg-panel-2/50 p-4">
        <Toggle
          v-model="form.isActive"
          label="Active Account"
          :help="form.isActive ? 'User can log in' : 'User cannot log in'"
        />
      </div>

      <section class="rounded-lg border border-edge bg-panel-2/50 p-4">
        <h3 class="mb-4 flex items-center gap-2 border-b border-edge pb-2.5 text-xs font-bold uppercase tracking-wider text-ink">
          <span class="h-3.5 w-1 rounded-full bg-accent"></span>
          Notification Settings
        </h3>

        <div class="rounded-lg border border-edge bg-panel p-3">
          <Toggle
            v-model="form.notificationsEnabled"
            label="Enable Notifications"
            help="Master toggle for all notifications"
          />
        </div>

        <div v-if="form.notificationsEnabled" class="mt-4 space-y-4 border-t border-edge pt-4">
          <div>
            <label class="label" for="notification-trigger">Notify On</label>
            <select id="notification-trigger" v-model="form.notificationTrigger" class="input">
              <option value="all">All test runs</option>
              <option value="failures">Only failures</option>
            </select>
          </div>

          <div>
            <span class="label">Notification Channels</span>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-edge bg-panel px-4 py-2.5 transition-colors hover:border-accent/50">
              <input v-model="form.notifyByEmail" type="checkbox" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
              <Mail class="h-4 w-4 text-ink-mute" />
              <span class="text-sm font-medium text-ink">Email</span>
            </label>
          </div>

          <div v-if="environments.length > 0">
            <span class="label">Environments</span>
            <p class="mb-2 text-xs text-ink-faint">Select environments to receive notifications for (leave empty for all)</p>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="env in environments"
                :key="env.id"
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-edge bg-panel px-3 py-2 transition-colors hover:border-accent/50"
              >
                <input v-model="form.notificationEnvironments" type="checkbox" :value="env.id" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
                <span class="font-mono text-sm text-ink">{{ env.name }}</span>
              </label>
            </div>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center gap-3 border-t border-edge pt-5">
        <button
          type="submit"
          class="btn-primary"
          :disabled="submitting || !isFormValid || validatingUsername || validatingEmail"
        >
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <Check v-else class="h-4 w-4" />
          {{ isEditMode ? 'Update User' : 'Create User' }}
        </button>
        <RouterLink :to="{ name: 'users' }" class="btn-ghost">
          <X class="h-4 w-4" />
          Cancel
        </RouterLink>
        <button v-if="isEditMode && hasChanges" type="button" class="btn-ghost" @click="resetToOriginal">
          <RotateCcw class="h-4 w-4" />
          Reset
        </button>
      </div>

      <div v-if="Object.keys(errors).length > 0" class="flex items-start gap-2 rounded-lg border border-fail/30 bg-fail/10 p-3 text-sm text-fail">
        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
        <div>
          <strong>Please fix the following errors:</strong>
          <ul class="mt-1 list-inside list-disc">
            <li v-for="(message, field) in errors" :key="field">{{ message }}</li>
          </ul>
        </div>
      </div>
    </form>

    <p v-if="isEditMode && loadedUser" class="mt-4 text-xs text-ink-faint">
      Created: {{ formatDateTime(loadedUser.createdAt) }}
      <template v-if="loadedUser.updatedAt"> | Last Updated: {{ formatDateTime(loadedUser.updatedAt) }}</template>
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertTriangle, ArrowLeft, Check, Eye, EyeOff, Loader2, Mail, RotateCcw, X } from 'lucide-vue-next';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import PasswordStrength from './components/PasswordStrength.vue';
import Toggle from '../../components/ui/Toggle.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { capitalize, formatDateTime } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const userId = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEditMode = computed(() => !!userId.value);

const form = reactive({
  username: '',
  email: '',
  password: '',
  passwordConfirm: '',
  roles: ['ROLE_USER'],
  isActive: true,
  notificationsEnabled: false,
  notificationTrigger: 'failures',
  notifyByEmail: false,
  notifyBySlack: false,
  notificationEnvironments: [],
});

const errors = reactive({});
const submitting = ref(false);
const loadingUser = ref(false);
const loadFailed = ref(false);
const loadedUser = ref(null);
const originalData = ref(null);
const environments = ref([]);

const usernameValid = ref(false);
const usernameValidMessage = ref('');
const validatingUsername = ref(false);
const emailValid = ref(false);
const emailValidMessage = ref('');
const validatingEmail = ref(false);
const showPassword = ref(false);

const hasChanges = computed(() => {
  if (!originalData.value) return false;
  return (
    form.username !== originalData.value.username ||
    form.email !== originalData.value.email ||
    JSON.stringify(form.roles) !== JSON.stringify(originalData.value.roles) ||
    form.isActive !== originalData.value.isActive ||
    form.password !== '' ||
    form.notificationsEnabled !== originalData.value.notificationsEnabled ||
    form.notificationTrigger !== originalData.value.notificationTrigger ||
    form.notifyByEmail !== originalData.value.notifyByEmail ||
    JSON.stringify(form.notificationEnvironments) !== JSON.stringify(originalData.value.notificationEnvironments)
  );
});

const isFormValid = computed(() => {
  if (!form.username || !form.email) return false;
  if (Object.keys(errors).length > 0) return false;
  if (!isEditMode.value && !form.password) return false;
  if (form.password && form.password !== form.passwordConfirm) return false;
  if (!form.roles.includes('ROLE_USER')) return false;
  return true;
});

function fieldClass(invalid, valid) {
  if (invalid) return 'border-fail focus:border-fail focus:ring-fail/25';
  if (valid) return 'border-pass focus:border-pass focus:ring-pass/25';
  return '';
}

function clearErrors() {
  Object.keys(errors).forEach((key) => delete errors[key]);
}

function handleUsernameInput() {
  usernameValid.value = false;
  delete errors.username;
}

function handleEmailInput() {
  emailValid.value = false;
  delete errors.email;
}

function makeRemoteValidator(field, endpoint, localCheck, flags) {
  return async () => {
    const localError = localCheck();
    if (localError) {
      errors[field] = localError;
      flags.valid.value = false;
      return;
    }
    flags.validating.value = true;
    try {
      const result = await api.post(endpoint, { [field]: form[field], excludeId: userId.value });
      if (result.valid) {
        delete errors[field];
        flags.valid.value = true;
        flags.message.value = result.message || `${capitalize(field)} is available`;
      } else {
        errors[field] = result.message;
        flags.valid.value = false;
      }
    } catch {
      errors[field] = `Failed to validate ${field}`;
      flags.valid.value = false;
    } finally {
      flags.validating.value = false;
    }
  };
}

const validateUsername = makeRemoteValidator(
  'username',
  '/api/users/validate-username',
  () => {
    if (!form.username) return 'Username is required';
    if (form.username.length < 3) return 'Username must be at least 3 characters';
    if (!/^[a-zA-Z0-9_-]+$/.test(form.username)) return 'Username can only contain letters, numbers, underscores, and hyphens';
    return '';
  },
  { valid: usernameValid, validating: validatingUsername, message: usernameValidMessage },
);

const validateEmail = makeRemoteValidator(
  'email',
  '/api/users/validate-email',
  () => {
    if (!form.email) return 'Email is required';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) return 'Please enter a valid email address';
    return '';
  },
  { valid: emailValid, validating: validatingEmail, message: emailValidMessage },
);

function handlePasswordInput() {
  delete errors.password;
}

function resetToOriginal() {
  if (!originalData.value) return;
  form.username = originalData.value.username;
  form.email = originalData.value.email;
  form.roles = [...originalData.value.roles];
  form.isActive = originalData.value.isActive;
  form.notificationsEnabled = originalData.value.notificationsEnabled;
  form.notificationTrigger = originalData.value.notificationTrigger;
  form.notifyByEmail = originalData.value.notifyByEmail;
  form.notificationEnvironments = [...originalData.value.notificationEnvironments];
  form.password = '';
  form.passwordConfirm = '';
  clearErrors();
  usernameValid.value = false;
  emailValid.value = false;
}

async function handleSubmit() {
  clearErrors();

  await validateUsername();
  await validateEmail();

  if (!isEditMode.value && !form.password) {
    errors.password = 'Password is required';
  }
  if (form.password && form.password !== form.passwordConfirm) {
    errors.passwordConfirm = 'Passwords do not match';
  }

  if (!isFormValid.value) {
    toasts.error('Please fix all errors before submitting');
    return;
  }

  const payload = {
    username: form.username,
    email: form.email,
    roles: form.roles,
    isActive: form.isActive,
    notificationsEnabled: form.notificationsEnabled,
    notificationTrigger: form.notificationTrigger,
    notifyByEmail: form.notifyByEmail,
    notifyBySlack: form.notifyBySlack,
    notificationEnvironments: form.notificationEnvironments,
  };
  if (!isEditMode.value || form.password) {
    payload.password = form.password;
    payload.passwordConfirm = form.passwordConfirm;
  }

  submitting.value = true;
  try {
    const result = isEditMode.value
      ? await api.put(`/api/users/${userId.value}`, payload)
      : await api.post('/api/users', payload);
    toasts.success(result.message);
    router.push({ name: 'users' });
  } catch (e) {
    if (e.payload?.errors) {
      Object.assign(errors, e.payload.errors);
      toasts.error('Please fix all errors before submitting');
    } else {
      toasts.error(e.message);
    }
  } finally {
    submitting.value = false;
  }
}

async function fetchEnvironments() {
  try {
    environments.value = await api.get('/api/profile/environments');
  } catch {
    environments.value = [];
    toasts.error('Failed to load environments for notification settings');
  }
}

async function fetchUser() {
  loadingUser.value = true;
  try {
    const data = await api.get(`/api/users/${userId.value}`);
    loadedUser.value = data;
    form.username = data.username || '';
    form.email = data.email || '';
    form.roles = data.roles || ['ROLE_USER'];
    form.isActive = data.isActive ?? true;
    form.notificationsEnabled = data.notificationsEnabled ?? false;
    form.notificationTrigger = data.notificationTrigger || 'failures';
    form.notifyByEmail = data.notifyByEmail ?? false;
    form.notifyBySlack = data.notifyBySlack ?? false;
    form.notificationEnvironments = data.notificationEnvironments || [];

    originalData.value = {
      username: form.username,
      email: form.email,
      roles: [...form.roles],
      isActive: form.isActive,
      notificationsEnabled: form.notificationsEnabled,
      notificationTrigger: form.notificationTrigger,
      notifyByEmail: form.notifyByEmail,
      notificationEnvironments: [...form.notificationEnvironments],
    };

    await validateUsername();
    await validateEmail();
  } catch {
    loadFailed.value = true;
    toasts.error('Failed to load user');
  } finally {
    loadingUser.value = false;
  }
}

onMounted(async () => {
  const tasks = [fetchEnvironments()];
  if (isEditMode.value) tasks.push(fetchUser());
  await Promise.all(tasks);
});
</script>
