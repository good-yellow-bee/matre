import { ref, reactive, watch, computed } from 'vue';

// Simple debounce helper
function debounce(fn, delay) {
  let timeout = null;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn.apply(this, args), delay);
  };
}

// HTML escape helper to prevent XSS
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

export function useNotificationTemplateForm(apiUrl, templateId, channel) {
  const form = reactive({
    subject: '',
    body: '',
  });

  const template = ref(null);
  const previewHtml = ref('');
  const previewSubject = ref('');
  const availableVariables = ref([]);
  const loading = ref(false);
  const submitting = ref(false);
  const testSending = ref(false);
  const error = ref(null);
  const successMessage = ref(null);

  const isEmail = computed(() => channel === 'email');

  const fetchTemplate = async () => {
    loading.value = true;
    error.value = null;

    try {
      const response = await fetch(`${apiUrl}/${templateId}`, {
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Failed to fetch template');

      const data = await response.json();
      template.value = data;
      form.subject = data.subject || '';
      form.body = data.body || '';
    } catch (err) {
      error.value = 'Failed to load template';
      console.error('Error fetching template:', err);
    } finally {
      loading.value = false;
    }
  };

  const fetchVariables = async () => {
    try {
      const response = await fetch(`${apiUrl}/variables`, {
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Failed to fetch variables');

      availableVariables.value = await response.json();
    } catch (err) {
      error.value = 'Failed to load available variables';
      console.error('Error fetching variables:', err);
    }
  };

  const generatePreview = async () => {
    try {
      const response = await fetch(`${apiUrl}/${templateId}/preview`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          subject: form.subject,
          body: form.body,
        }),
      });

      if (!response.ok) throw new Error('Failed to generate preview');

      const data = await response.json();
      previewHtml.value = data.html;
      previewSubject.value = data.subject;
    } catch (err) {
      previewHtml.value = '<div class="text-danger">Preview unavailable. Please try again.</div>';
      console.error('Error generating preview:', err);
    }
  };

  const debouncedPreview = debounce(generatePreview, 500);

  watch([() => form.subject, () => form.body], () => {
    debouncedPreview();
  });

  const saveTemplate = async () => {
    submitting.value = true;
    error.value = null;
    successMessage.value = null;

    try {
      const response = await fetch(`${apiUrl}/${templateId}`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          subject: form.subject,
          body: form.body,
        }),
      });

      const data = await response.json();

      if (response.ok) {
        successMessage.value = data.message || 'Template saved successfully';
        return { success: true };
      } else {
        error.value = data.error || 'Failed to save template';
        return { success: false };
      }
    } catch (err) {
      error.value = 'Network error while saving';
      console.error('Error saving template:', err);
      return { success: false };
    } finally {
      submitting.value = false;
    }
  };

  const resetToDefault = async () => {
    if (!confirm('Reset this template to its default content? Your customizations will be lost.')) {
      return { success: false };
    }

    submitting.value = true;
    error.value = null;

    try {
      const response = await fetch(`${apiUrl}/${templateId}/reset`, {
        method: 'POST',
        credentials: 'same-origin',
      });

      const data = await response.json();

      if (response.ok) {
        form.subject = data.subject || '';
        form.body = data.body || '';
        successMessage.value = data.message || 'Template reset to default';
        return { success: true };
      } else {
        error.value = data.error || 'Failed to reset template';
        return { success: false };
      }
    } catch (err) {
      error.value = 'Network error while resetting';
      console.error('Error resetting template:', err);
      return { success: false };
    } finally {
      submitting.value = false;
    }
  };

  const sendTest = async () => {
    testSending.value = true;
    error.value = null;
    successMessage.value = null;

    try {
      const response = await fetch(`${apiUrl}/${templateId}/test-send`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          subject: form.subject,
          body: form.body,
        }),
      });

      const data = await response.json();

      if (response.ok && data.success) {
        successMessage.value = data.message || 'Test notification sent';
        return { success: true };
      } else {
        error.value = data.error || data.message || 'Failed to send test';
        return { success: false };
      }
    } catch (err) {
      error.value = 'Network error while sending test';
      console.error('Error sending test:', err);
      return { success: false };
    } finally {
      testSending.value = false;
    }
  };

  return {
    form,
    template,
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
  };
}
