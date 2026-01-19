<template>
  <div class="template-editor">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2 text-muted">Loading template...</p>
    </div>

    <!-- Main Content -->
    <div v-else>
      <!-- Alert Messages -->
      <div v-if="error" class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ error }}
        <button type="button" class="btn-close" @click="error = null"></button>
      </div>
      <div v-if="successMessage" class="alert alert-success alert-dismissible fade show" role="alert">
        {{ successMessage }}
        <button type="button" class="btn-close" @click="successMessage = null"></button>
      </div>

      <form @submit.prevent="handleSubmit">
        <!-- Subject (Email only) -->
        <div v-if="isEmail" class="mb-3">
          <label for="template-subject" class="form-label fw-medium">
            Email Subject
          </label>
          <input
            id="template-subject"
            v-model="form.subject"
            type="text"
            class="form-control"
            placeholder="Enter email subject line"
          />
          <small class="form-text text-muted">
            Use variables like <code>{{ exampleVar }}</code> for dynamic content
          </small>
        </div>

        <!-- Variable Toolbar -->
        <div class="variable-toolbar mb-3">
          <label class="form-label fw-medium d-block">Insert Variable</label>
          <div class="d-flex flex-wrap gap-1">
            <button
              v-for="variable in availableVariables"
              :key="variable.name"
              type="button"
              class="btn btn-sm btn-outline-secondary"
              @click="insertVariable(variable.name)"
              :title="variable.description"
            >
              {{ variable.name }}
            </button>
          </div>
        </div>

        <!-- Split Editor/Preview -->
        <div class="row">
          <!-- Editor -->
          <div class="col-lg-6 mb-3 mb-lg-0">
            <label class="form-label fw-medium">
              Template Body
              <span class="text-muted fw-normal">({{ isEmail ? 'HTML' : 'Slack Markdown' }})</span>
            </label>
            <textarea
              ref="bodyTextarea"
              v-model="form.body"
              class="form-control font-monospace"
              rows="20"
              @select="handleSelect"
            ></textarea>
          </div>

          <!-- Preview -->
          <div class="col-lg-6">
            <label class="form-label fw-medium d-flex justify-content-between align-items-center">
              <span>
                Live Preview
                <span v-if="previewSubject && isEmail" class="text-muted fw-normal ms-2">
                  Subject: {{ previewSubject }}
                </span>
              </span>
              <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="generatePreview">
                  <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button
                  v-if="isEmail"
                  type="button"
                  class="btn btn-sm btn-outline-info"
                  @click="handleSendTest"
                  :disabled="testSending"
                >
                  <i class="bi bi-send"></i>
                  {{ testSending ? 'Sending...' : 'Send Test' }}
                </button>
              </div>
            </label>
            <div class="preview-container border rounded p-3 bg-white" style="min-height: 440px; max-height: 440px; overflow-y: auto;">
              <div v-html="previewHtml"></div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2 mt-4 pt-3 border-top">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            <i class="bi bi-check-lg"></i>
            {{ submitting ? 'Saving...' : 'Save Template' }}
          </button>
          <a :href="cancelUrl" class="btn btn-secondary">
            Cancel
          </a>
          <button type="button" class="btn btn-outline-warning ms-auto" @click="handleReset">
            <i class="bi bi-arrow-counterclockwise"></i>
            Reset to Default
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useNotificationTemplateForm } from '../composables/useNotificationTemplateForm';

const props = defineProps({
  templateId: {
    type: Number,
    required: true,
  },
  apiUrl: {
    type: String,
    required: true,
  },
  channel: {
    type: String,
    required: true,
  },
  cancelUrl: {
    type: String,
    default: '/admin/notification-templates',
  },
});

const {
  form,
  previewHtml,
  previewSubject,
  availableVariables,
  loading,
  submitting,
  testSending,
  error,
  successMessage,
  isEmail,
  fetchTemplate,
  fetchVariables,
  generatePreview,
  saveTemplate,
  resetToDefault,
  sendTest,
} = useNotificationTemplateForm(props.apiUrl, props.templateId, props.channel);

const bodyTextarea = ref(null);
const selectionStart = ref(0);
const exampleVar = '{{ run_id }}';

onMounted(async () => {
  await Promise.all([fetchTemplate(), fetchVariables()]);
  // Initial preview after loading
  setTimeout(generatePreview, 100);
});

const handleSelect = (event) => {
  selectionStart.value = event.target.selectionStart;
};

const insertVariable = (variableName) => {
  const textarea = bodyTextarea.value;
  if (!textarea) return;

  const start = textarea.selectionStart;
  const end = textarea.selectionEnd;
  const text = form.body;
  const insert = `{{ ${variableName} }}`;

  form.body = text.substring(0, start) + insert + text.substring(end);

  // Restore cursor position
  setTimeout(() => {
    textarea.focus();
    textarea.setSelectionRange(start + insert.length, start + insert.length);
  }, 0);
};

const handleSubmit = async () => {
  const result = await saveTemplate();
  if (result.success) {
    // Auto-hide success message after 3 seconds
    setTimeout(() => {
      successMessage.value = null;
    }, 3000);
  }
};

const handleReset = async () => {
  await resetToDefault();
  // Refresh preview after reset
  setTimeout(generatePreview, 100);
};

const handleSendTest = async () => {
  await sendTest();
};
</script>

<style scoped>
.template-editor {
  max-width: 100%;
}

.variable-toolbar .btn {
  font-family: monospace;
  font-size: 0.8rem;
}

.preview-container {
  background: #fff;
}

.font-monospace {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.875rem;
}

textarea.form-control {
  resize: vertical;
}
</style>
