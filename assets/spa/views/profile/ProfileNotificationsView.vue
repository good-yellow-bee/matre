<template>
  <div class="mx-auto max-w-4xl">
    <PageHeader title="Notification Settings" subtitle="Configure when and how you receive test run notifications" />

    <div v-if="loading" class="space-y-4">
      <div class="skeleton h-64 w-full"></div>
    </div>

    <div v-else class="grid items-start gap-6 lg:grid-cols-3">
      <form class="card rise space-y-6 p-6 lg:col-span-2" style="--i: 1" @submit.prevent="save">
        <div class="rounded-lg border border-edge bg-panel-2/50 p-4">
          <Toggle
            v-model="form.notificationsEnabled"
            label="Enable Notifications"
            help="Master toggle for all notifications"
          />
        </div>

        <template v-if="form.notificationsEnabled">
          <div>
            <span class="label">Notify When</span>
            <div class="flex flex-wrap gap-3">
              <label
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 transition-colors"
                :class="form.notificationTrigger === 'failures' ? 'border-accent/50 bg-accent-soft' : 'border-edge bg-panel-2/50 hover:border-accent/40'"
              >
                <input v-model="form.notificationTrigger" type="radio" value="failures" class="h-4 w-4 accent-(--m-accent)">
                <span class="text-sm font-medium text-ink">Only failures</span>
              </label>
              <label
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2.5 transition-colors"
                :class="form.notificationTrigger === 'all' ? 'border-accent/50 bg-accent-soft' : 'border-edge bg-panel-2/50 hover:border-accent/40'"
              >
                <input v-model="form.notificationTrigger" type="radio" value="all" class="h-4 w-4 accent-(--m-accent)">
                <span class="text-sm font-medium text-ink">All test runs</span>
              </label>
            </div>
          </div>

          <div>
            <span class="label">Email Notifications</span>
            <div class="rounded-lg border border-edge bg-panel-2/50 p-4">
              <label class="flex cursor-pointer items-center gap-2">
                <input v-model="form.notifyByEmail" type="checkbox" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
                <Mail class="h-4 w-4 text-ink-mute" />
                <span class="text-sm font-medium text-ink">Email</span>
              </label>
              <p class="mt-2 text-xs text-ink-faint">
                Receive email notifications to <strong class="text-ink-mute">{{ userEmail }}</strong>
              </p>
            </div>
          </div>

          <div>
            <span class="label">Environments</span>
            <template v-if="environments.length > 0">
              <p class="mb-2 text-xs text-ink-faint">Select which test environments you want to receive notifications for</p>
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="env in environments"
                  :key="env.id"
                  class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-edge bg-panel-2/50 px-3 py-2 transition-colors hover:border-accent/50"
                >
                  <input v-model="form.notificationEnvironments" type="checkbox" :value="env.id" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
                  <span class="font-mono text-sm text-ink">{{ env.name }}</span>
                </label>
              </div>
            </template>
            <div v-else class="flex items-start gap-2 rounded-lg border border-run/30 bg-run/10 p-3 text-sm text-run">
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
              {{ environmentsError || 'No test environments configured. Create environments first to enable notifications.' }}
            </div>
          </div>
        </template>

        <div class="flex flex-wrap items-center gap-3 border-t border-edge pt-5">
          <button type="submit" class="btn-primary" :disabled="submitting">
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <Check v-else class="h-4 w-4" />
            Save Preferences
          </button>
          <RouterLink :to="{ name: 'dashboard' }" class="btn-ghost">
            <X class="h-4 w-4" />
            Cancel
          </RouterLink>
          <button v-if="hasChanges" type="button" class="btn-ghost" @click="resetToOriginal">
            <RotateCcw class="h-4 w-4" />
            Reset
          </button>
        </div>
      </form>

      <aside class="card rise p-5" style="--i: 2">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-ink">
          <Info class="h-4 w-4 text-accent" />
          How notifications work
        </h2>
        <ul class="space-y-2 text-xs text-ink-mute">
          <li><strong class="text-ink">Email:</strong> You receive individual emails when test runs complete for your selected environments.</li>
          <li><strong class="text-ink">Failures only:</strong> Only notified when tests fail or the run itself fails.</li>
          <li><strong class="text-ink">All runs:</strong> Notified for all completed test runs regardless of status.</li>
        </ul>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { AlertTriangle, Check, Info, Loader2, Mail, RotateCcw, X } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import Toggle from '../../components/ui/Toggle.vue';
import { api } from '../../api/client';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toasts';

const auth = useAuthStore();
const toasts = useToastStore();

const userEmail = computed(() => auth.user?.email ?? '');

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

function snapshot() {
  originalData.value = {
    notificationsEnabled: form.notificationsEnabled,
    notificationTrigger: form.notificationTrigger,
    notifyByEmail: form.notifyByEmail,
    notificationEnvironments: [...form.notificationEnvironments],
  };
}

function resetToOriginal() {
  if (!originalData.value) return;
  form.notificationsEnabled = originalData.value.notificationsEnabled;
  form.notificationTrigger = originalData.value.notificationTrigger;
  form.notifyByEmail = originalData.value.notifyByEmail;
  form.notificationEnvironments = [...originalData.value.notificationEnvironments];
}

async function fetchNotifications() {
  try {
    const data = await api.get('/api/profile/notifications');
    form.notificationsEnabled = data.notificationsEnabled ?? false;
    form.notificationTrigger = data.notificationTrigger || 'failures';
    form.notifyByEmail = data.notifyByEmail ?? false;
    form.notificationEnvironments = data.notificationEnvironments || [];
    snapshot();
  } catch {
    toasts.error('Failed to load notification settings');
  }
}

async function fetchEnvironments() {
  environmentsError.value = '';
  try {
    environments.value = await api.get('/api/profile/environments');
  } catch {
    environmentsError.value = 'Failed to load environments';
  }
}

async function save() {
  submitting.value = true;
  try {
    const result = await api.put('/api/profile/notifications', {
      notificationsEnabled: form.notificationsEnabled,
      notificationTrigger: form.notificationTrigger,
      notifyByEmail: form.notifyByEmail,
      notificationEnvironments: form.notificationEnvironments,
    });
    toasts.success(result.message || 'Settings saved successfully');
    snapshot();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  await Promise.all([fetchEnvironments(), fetchNotifications()]);
  loading.value = false;
});
</script>
