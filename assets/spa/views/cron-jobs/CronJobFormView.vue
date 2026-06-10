<template>
  <div class="max-w-3xl">
    <PageHeader
      :title="isEditMode ? 'Edit Cron Job' : 'Create Cron Job'"
      :subtitle="isEditMode ? `Modify scheduled task: ${originalData?.name || ''}` : 'Add a scheduled task to run automatically'"
    >
      <template #actions>
        <RouterLink class="btn-ghost" :to="{ name: 'cron-jobs' }">
          <ArrowLeft class="h-4 w-4" />
          Back to List
        </RouterLink>
      </template>
    </PageHeader>

    <div v-if="loading" class="card p-5">
      <div class="skeleton mb-4 h-9 w-full"></div>
      <div class="skeleton mb-4 h-9 w-full"></div>
      <div class="skeleton h-24 w-full"></div>
    </div>

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
      <!-- Job Configuration -->
      <section class="card rise p-5" style="--i: 1">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <CalendarClock class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Job Configuration</h2>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
          <!-- Name -->
          <div>
            <label class="label" for="cron-name">Job Name <span class="text-fail">*</span></label>
            <input
              id="cron-name"
              v-model="form.name"
              class="input"
              type="text"
              maxlength="100"
              placeholder="e.g., Daily Cleanup"
              :disabled="validatingName"
              @input="onNameInput"
              @blur="validateName"
            >
            <p v-if="errors.name" class="field-error">{{ errors.name }}</p>
            <p v-else-if="nameValid && form.name" class="mt-1 flex items-center gap-1 text-xs text-pass">
              <Check class="h-3 w-3" /> Name is available
            </p>
            <p v-else class="mt-1 text-xs text-ink-faint">Unique name for this cron job (3-100 characters)</p>
          </div>

          <!-- Cron Expression -->
          <div>
            <label class="label" for="cron-expression">Cron Expression <span class="text-fail">*</span></label>
            <input
              id="cron-expression"
              v-model="form.cronExpression"
              class="input font-mono"
              type="text"
              maxlength="100"
              placeholder="e.g., 0 * * * * (hourly)"
              @input="onCronInput"
              @blur="validateCron"
            >
            <p v-if="errors.cronExpression" class="field-error">{{ errors.cronExpression }}</p>
            <p v-else-if="cronValid && cronMessage" class="mt-1 flex items-center gap-1 text-xs text-pass">
              <Check class="h-3 w-3" /> {{ cronMessage }}
            </p>
            <p v-else class="mt-1 text-xs text-ink-faint">Format: minute hour day month weekday</p>
          </div>
        </div>

        <!-- Quick picks -->
        <div class="mt-3 flex flex-wrap items-center gap-1.5">
          <span class="text-xs text-ink-faint">Quick picks:</span>
          <button
            v-for="preset in cronPresets"
            :key="preset.expression"
            type="button"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-edge bg-panel-2 px-2 py-1 text-xs text-ink-mute transition-colors hover:border-accent/40 hover:text-ink"
            @click="applyPreset(preset)"
          >
            <code class="font-mono text-accent">{{ preset.expression }}</code>
            {{ preset.label }}
          </button>
        </div>

        <!-- Command combobox -->
        <div class="relative mt-4">
          <label class="label" for="cron-command">Console Command <span class="text-fail">*</span></label>
          <div class="relative">
            <input
              id="cron-command"
              v-model="form.command"
              class="input pr-9 font-mono"
              type="text"
              maxlength="255"
              placeholder="e.g., app:cleanup --days=30"
              autocomplete="off"
              @focus="commandsOpen = true"
              @input="commandsOpen = true; delete errors.command"
              @blur="closeCommands"
              @keydown.escape="commandsOpen = false"
            >
            <button
              type="button"
              class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer rounded p-1 text-ink-faint hover:text-ink"
              tabindex="-1"
              title="Toggle command picker"
              @mousedown.prevent="commandsOpen = !commandsOpen"
            >
              <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': commandsOpen }" />
            </button>
          </div>
          <div
            v-if="commandsOpen"
            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-edge bg-panel shadow-lg"
          >
            <button
              v-for="cmd in filteredCommands"
              :key="cmd.name"
              type="button"
              class="block w-full cursor-pointer px-3 py-2 text-left transition-colors hover:bg-panel-2"
              @mousedown.prevent="pickCommand(cmd)"
            >
              <span class="block font-mono text-xs font-semibold text-accent">{{ cmd.name }}</span>
              <span v-if="cmd.description" class="block truncate text-[11px] text-ink-mute">{{ cmd.description }}</span>
            </button>
            <div v-if="!filteredCommands.length" class="px-3 py-2.5 text-xs text-ink-faint">
              No matching commands — free text with arguments is allowed
            </div>
          </div>
          <p v-if="errors.command" class="field-error">{{ errors.command }}</p>
          <p v-else-if="commandsError" class="mt-1 text-xs text-run">{{ commandsError }}</p>
          <p v-else class="mt-1 text-xs text-ink-faint">Symfony console command with optional arguments</p>
        </div>

        <!-- Description -->
        <div class="mt-4">
          <label class="label" for="cron-description">Description</label>
          <textarea
            id="cron-description"
            v-model="form.description"
            class="input resize-y"
            rows="3"
            placeholder="Describe what this job does"
          ></textarea>
          <p class="mt-1 text-xs text-ink-faint">Optional description of the job's purpose</p>
        </div>
      </section>

      <!-- Status -->
      <section class="card rise p-5" style="--i: 2">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <Power class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Status</h2>
        </header>
        <Toggle
          v-model="form.isActive"
          label="Active"
          :help="form.isActive ? 'Job will run on schedule' : 'Job is disabled'"
        />
      </section>

      <!-- Last execution (edit mode) -->
      <div
        v-if="isEditMode && lastExecution?.lastStatus"
        class="rise flex items-center gap-3 rounded-lg border border-edge bg-panel p-3 text-sm text-ink-mute"
        style="--i: 3"
      >
        <History class="h-4 w-4 shrink-0 text-ink-faint" />
        <span>
          <strong class="font-semibold text-ink">Last execution:</strong>
          {{ formatDate(lastExecution.lastRunAt) }}
        </span>
        <CronStatusBadge :status="lastExecution.lastStatus" />
      </div>

      <!-- Actions -->
      <div class="rise flex flex-wrap items-center gap-2" style="--i: 4">
        <button class="btn-primary" type="submit" :disabled="submitting || !isFormValid">
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <Check v-else class="h-4 w-4" />
          {{ isEditMode ? 'Update Cron Job' : 'Create Cron Job' }}
        </button>
        <RouterLink class="btn-ghost" :to="{ name: 'cron-jobs' }">Cancel</RouterLink>
        <button v-if="isEditMode && hasChanges" class="btn-ghost" type="button" @click="resetToOriginal">
          <RotateCcw class="h-4 w-4" />
          Reset
        </button>
      </div>

      <!-- Validation summary -->
      <div
        v-if="Object.keys(errors).length"
        class="rise rounded-lg border border-fail/30 bg-fail/10 p-4 text-sm text-fail"
      >
        <p class="font-semibold">Please fix the following errors:</p>
        <ul class="mt-1.5 list-inside list-disc space-y-0.5 text-xs">
          <li v-for="(message, field) in errors" :key="field">{{ message }}</li>
        </ul>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, CalendarClock, Check, ChevronDown, History, Loader2, Power, RotateCcw } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import Toggle from '../settings/components/Toggle.vue';
import CronStatusBadge from './components/CronStatusBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const jobId = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEditMode = computed(() => !!jobId.value);

const form = reactive({
  name: '',
  cronExpression: '* * * * *',
  command: '',
  description: '',
  isActive: true,
});

const errors = reactive({});
const loading = ref(false);
const submitting = ref(false);
const originalData = ref(null);
const lastExecution = ref(null);

const commands = ref([]);
const commandsError = ref('');
const commandsOpen = ref(false);

const nameValid = ref(false);
const validatingName = ref(false);
const cronValid = ref(false);
const cronMessage = ref('');

const cronPresets = [
  { expression: '* * * * *', label: 'Every minute' },
  { expression: '0 * * * *', label: 'Hourly' },
  { expression: '0 0 * * *', label: 'Daily at midnight' },
  { expression: '0 0 * * 0', label: 'Weekly on Sunday' },
  { expression: '0 0 1 * *', label: 'Monthly on the 1st' },
];

const filteredCommands = computed(() => {
  const query = form.command.trim().toLowerCase();
  if (!query) return commands.value;
  return commands.value.filter(
    (cmd) => cmd.name.toLowerCase().includes(query) || cmd.description?.toLowerCase().includes(query),
  );
});

const hasChanges = computed(() => {
  if (!originalData.value) return false;
  return (
    form.name !== originalData.value.name
    || form.cronExpression !== originalData.value.cronExpression
    || form.command !== originalData.value.command
    || form.description !== originalData.value.description
    || form.isActive !== originalData.value.isActive
  );
});

const isFormValid = computed(() => {
  if (!form.name || !form.cronExpression || !form.command) return false;
  return !Object.keys(errors).length;
});

function formatDate(iso) {
  return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function onNameInput() {
  nameValid.value = false;
  delete errors.name;
}

async function validateName() {
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
    const result = await api.post('/api/cron-jobs/validate-name', { name: form.name, excludeId: jobId.value });
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
  } finally {
    validatingName.value = false;
  }
}

function onCronInput() {
  cronValid.value = false;
  cronMessage.value = '';
  delete errors.cronExpression;
}

async function validateCron() {
  if (!form.cronExpression) {
    errors.cronExpression = 'Cron expression is required';
    cronValid.value = false;
    return;
  }
  try {
    const result = await api.post('/api/cron-jobs/validate-cron', { expression: form.cronExpression });
    if (result.valid) {
      delete errors.cronExpression;
      cronValid.value = true;
      cronMessage.value = result.message;
    } else {
      errors.cronExpression = result.message;
      cronValid.value = false;
    }
  } catch {
    errors.cronExpression = 'Could not validate expression. Check your connection.';
    cronValid.value = false;
  }
}

function applyPreset(preset) {
  form.cronExpression = preset.expression;
  validateCron();
}

function pickCommand(cmd) {
  form.command = cmd.name;
  commandsOpen.value = false;
  delete errors.command;
}

function closeCommands() {
  setTimeout(() => { commandsOpen.value = false; }, 120);
}

async function fetchCommands() {
  commandsError.value = '';
  try {
    commands.value = await api.get('/api/cron-jobs/commands');
  } catch {
    commandsError.value = 'Failed to load command list';
  }
}

async function fetchCronJob() {
  loading.value = true;
  try {
    const data = await api.get(`/api/cron-jobs/${jobId.value}`);
    form.name = data.name || '';
    form.cronExpression = data.cronExpression || '* * * * *';
    form.command = data.command || '';
    form.description = data.description || '';
    form.isActive = data.isActive ?? true;
    originalData.value = { ...form };
    lastExecution.value = { lastStatus: data.lastStatus, lastRunAt: data.lastRunAt };
    await Promise.all([validateName(), validateCron()]);
  } catch (e) {
    toasts.error(e.status === 404 ? 'Cron job not found' : 'Failed to load cron job');
    router.push({ name: 'cron-jobs' });
  } finally {
    loading.value = false;
  }
}

function resetToOriginal() {
  Object.assign(form, originalData.value);
  Object.keys(errors).forEach((key) => delete errors[key]);
  nameValid.value = false;
  cronValid.value = false;
  cronMessage.value = '';
}

async function submit() {
  Object.keys(errors).forEach((key) => delete errors[key]);

  await Promise.all([validateName(), validateCron()]);
  if (!form.command) errors.command = 'Command is required';

  if (!isFormValid.value) {
    toasts.error('Please fix all errors before submitting');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      name: form.name,
      cronExpression: form.cronExpression,
      command: form.command,
      description: form.description,
      isActive: form.isActive,
    };
    const data = isEditMode.value
      ? await api.put(`/api/cron-jobs/${jobId.value}`, payload)
      : await api.post('/api/cron-jobs', payload);
    toasts.success(data.message);
    router.push({ name: 'cron-jobs' });
  } catch (e) {
    if (e.payload?.errors) Object.assign(errors, e.payload.errors);
    toasts.error(e.payload?.errors ? 'Please fix the highlighted fields' : e.message);
  } finally {
    submitting.value = false;
  }
}

onMounted(async () => {
  await fetchCommands();
  if (isEditMode.value) await fetchCronJob();
});
</script>
