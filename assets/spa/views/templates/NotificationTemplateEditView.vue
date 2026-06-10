<template>
  <div>
    <div v-if="loading" class="space-y-5">
      <div class="skeleton h-8 w-72"></div>
      <div class="grid gap-5 lg:grid-cols-2">
        <div class="card p-5"><div class="skeleton h-96 w-full"></div></div>
        <div class="card p-5"><div class="skeleton h-96 w-full"></div></div>
      </div>
    </div>

    <template v-else-if="template">
      <PageHeader>
        <template #title>
          <span class="flex flex-wrap items-center gap-2.5">
            <span
              class="badge border"
              :class="isEmail ? 'text-accent border-accent/25 bg-accent-soft' : 'text-broken border-broken/25 bg-broken/10'"
            >
              <component :is="isEmail ? Mail : Slack" class="h-3 w-3" />
              {{ template.channel }}
            </span>
            {{ template.nameLabel }}
          </span>
        </template>
        <template #subtitle>
          <RouterLink class="link inline-flex items-center gap-1" :to="{ name: 'notification-templates' }">
            <ArrowLeft class="h-3.5 w-3.5" />
            Back to Templates
          </RouterLink>
        </template>
        <template #actions>
          <span
            class="badge border"
            :class="template.isActive ? 'text-pass border-pass/25 bg-pass/10' : 'text-skip border-skip/25 bg-skip/10'"
          >
            {{ template.isActive ? 'Active' : 'Inactive' }}
          </span>
          <Toggle
            :model-value="template.isActive"
            :disabled="togglingActive"
            @update:model-value="toggleActive"
          />
        </template>
      </PageHeader>

      <form novalidate @submit.prevent="save">
        <div class="grid items-start gap-5 lg:grid-cols-2">
          <!-- Editor -->
          <section class="card rise p-5" style="--i: 1">
            <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
                <Pencil class="h-4 w-4" />
              </span>
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">
                Template Body
                <span class="font-normal normal-case tracking-normal text-ink-faint">({{ isEmail ? 'HTML' : 'Slack Markdown' }})</span>
              </h2>
            </header>

            <div v-if="isEmail" class="mb-4">
              <label class="label" for="template-subject">Email Subject</label>
              <input
                id="template-subject"
                v-model="form.subject"
                class="input"
                type="text"
                placeholder="Enter email subject line"
              >
              <p class="mt-1 text-xs text-ink-faint">
                Use variables like <code v-pre class="font-mono text-accent">{{ run_id }}</code> for dynamic content
              </p>
            </div>

            <div class="mb-4">
              <span class="label">Insert Variable</span>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="variable in variables"
                  :key="variable.name"
                  type="button"
                  class="cursor-pointer rounded-md border border-edge bg-panel-2 px-2 py-1 font-mono text-[11px] text-ink-mute transition-colors hover:border-accent/40 hover:text-accent"
                  :title="variable.description"
                  @click="insertVariable(variable.name)"
                >
                  {{ variable.name }}
                </button>
              </div>
            </div>

            <textarea
              ref="bodyTextarea"
              v-model="form.body"
              class="input min-h-[440px] resize-y font-mono text-xs leading-relaxed"
              rows="20"
              spellcheck="false"
            ></textarea>
          </section>

          <!-- Preview -->
          <section class="card rise p-5" style="--i: 2">
            <header class="mb-4 flex flex-wrap items-center gap-2.5 border-b border-edge pb-3">
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
                <Eye class="h-4 w-4" />
              </span>
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Live Preview</h2>
              <div class="ml-auto flex items-center gap-1.5">
                <button class="btn-ghost btn-sm" type="button" :disabled="previewing" @click="generatePreview">
                  <Loader2 v-if="previewing" class="h-3.5 w-3.5 animate-spin" />
                  <RefreshCw v-else class="h-3.5 w-3.5" />
                  Refresh
                </button>
                <button
                  v-if="isEmail"
                  class="btn-ghost btn-sm"
                  type="button"
                  :disabled="testSending"
                  @click="confirmSendOpen = true"
                >
                  <Loader2 v-if="testSending" class="h-3.5 w-3.5 animate-spin" />
                  <Send v-else class="h-3.5 w-3.5" />
                  Send Test
                </button>
              </div>
            </header>

            <p v-if="isEmail && previewSubject" class="mb-2 truncate text-xs text-ink-mute" :title="previewSubject">
              <span class="font-semibold uppercase tracking-wider text-ink-faint">Subject:</span> {{ previewSubject }}
            </p>

            <iframe
              sandbox=""
              :srcdoc="previewDoc"
              title="Template preview"
              class="h-[440px] w-full rounded-lg border border-edge bg-white"
            ></iframe>
          </section>
        </div>

        <div class="rise mt-5 flex flex-wrap items-center gap-2" style="--i: 3">
          <button class="btn-primary" type="submit" :disabled="submitting">
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <Check v-else class="h-4 w-4" />
            {{ submitting ? 'Saving...' : 'Save Template' }}
          </button>
          <RouterLink class="btn-ghost" :to="{ name: 'notification-templates' }">Cancel</RouterLink>
          <button class="btn-danger ml-auto" type="button" @click="confirmResetOpen = true">
            <RotateCcw class="h-4 w-4" />
            Reset to Default
          </button>
        </div>
      </form>

      <ConfirmDialog
        :open="confirmSendOpen"
        title="Send Test Email"
        :message="`Send a real test email rendered from the current editor content to ${auth.user?.email || 'your account email'}?`"
        confirm-label="Send Test"
        :busy="testSending"
        @confirm="sendTest"
        @cancel="confirmSendOpen = false"
      />

      <ConfirmDialog
        :open="confirmResetOpen"
        title="Reset Template"
        message="Reset this template to its default content? Your customizations will be lost."
        confirm-label="Reset Template"
        danger
        :busy="resetting"
        @confirm="resetToDefault"
        @cancel="confirmResetOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Check, Eye, Loader2, Mail, Pencil, RefreshCw, RotateCcw, Send, Slack } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import Toggle from '../../components/ui/Toggle.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();
const auth = useAuthStore();

const templateId = computed(() => Number(route.params.id));

const template = ref(null);
const variables = ref([]);
const form = reactive({ subject: '', body: '' });

const loading = ref(true);
const submitting = ref(false);
const previewing = ref(false);
const testSending = ref(false);
const resetting = ref(false);
const togglingActive = ref(false);
const confirmSendOpen = ref(false);
const confirmResetOpen = ref(false);

const previewHtml = ref('');
const previewSubject = ref('');
const bodyTextarea = ref(null);
let previewTimeout = null;

const isEmail = computed(() => template.value?.channel === 'email');

const previewDoc = computed(
  () => `<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{margin:12px;font-family:system-ui,sans-serif;font-size:14px;color:#0e1726;}</style></head><body>${previewHtml.value}</body></html>`,
);

async function fetchTemplate() {
  template.value = await api.get(`/api/notification-templates/${templateId.value}`);
  form.subject = template.value.subject || '';
  form.body = template.value.body || '';
}

async function fetchVariables() {
  variables.value = await api.get('/api/notification-templates/variables');
}

async function generatePreview() {
  previewing.value = true;
  try {
    const data = await api.post(`/api/notification-templates/${templateId.value}/preview`, {
      subject: form.subject,
      body: form.body,
    });
    previewHtml.value = data.html;
    previewSubject.value = data.subject || '';
  } catch {
    previewHtml.value = '<div style="color:#dc2626;font-size:13px;">Preview unavailable. Please try again.</div>';
  } finally {
    previewing.value = false;
  }
}

watch([() => form.subject, () => form.body], () => {
  clearTimeout(previewTimeout);
  previewTimeout = setTimeout(generatePreview, 500);
});

function insertVariable(name) {
  const textarea = bodyTextarea.value;
  const insert = `{{ ${name} }}`;
  if (!textarea) {
    form.body += insert;
    return;
  }
  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  form.body = form.body.substring(0, start) + insert + form.body.substring(end);
  setTimeout(() => {
    textarea.focus();
    textarea.setSelectionRange(start + insert.length, start + insert.length);
  }, 0);
}

async function save() {
  submitting.value = true;
  try {
    const data = await api.put(`/api/notification-templates/${templateId.value}`, {
      subject: form.subject,
      body: form.body,
    });
    toasts.success(data.message || 'Template saved successfully');
  } catch (e) {
    toasts.error(e.message);
  } finally {
    submitting.value = false;
  }
}

async function resetToDefault() {
  resetting.value = true;
  try {
    const data = await api.post(`/api/notification-templates/${templateId.value}/reset`);
    form.subject = data.subject || '';
    form.body = data.body || '';
    toasts.success(data.message || 'Template reset to default');
    confirmResetOpen.value = false;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    resetting.value = false;
  }
}

async function sendTest() {
  testSending.value = true;
  try {
    const data = await api.post(`/api/notification-templates/${templateId.value}/test-send`, {
      subject: form.subject,
      body: form.body,
    });
    toasts.success(data.message || 'Test notification sent');
    confirmSendOpen.value = false;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    testSending.value = false;
  }
}

async function toggleActive() {
  togglingActive.value = true;
  try {
    const data = await api.post(`/api/notification-templates/${templateId.value}/toggle-active`);
    template.value.isActive = data.isActive;
    toasts.success(data.message);
  } catch (e) {
    toasts.error(e.message);
  } finally {
    togglingActive.value = false;
  }
}

onMounted(async () => {
  try {
    await Promise.all([fetchTemplate(), fetchVariables()]);
    await generatePreview();
  } catch (e) {
    toasts.error(e.status === 404 ? 'Template not found' : e.message);
    router.push({ name: 'notification-templates' });
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => clearTimeout(previewTimeout));
</script>
