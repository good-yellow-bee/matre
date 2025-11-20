<template>
  <div class="cms-feedback card">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <p class="mb-1 fw-semibold">Was this page helpful?</p>
          <p class="text-muted small mb-0">Your feedback helps us improve {{ siteName }}.</p>
        </div>
        <span class="badge bg-primary text-uppercase">Vue</span>
      </div>

      <div class="d-flex gap-2 mb-3">
        <button
          type="button"
          class="btn btn-outline-success btn-sm"
          :class="{ active: response === 'yes' }"
          @click="setResponse('yes')"
        >
          👍 Yes
        </button>
        <button
          type="button"
          class="btn btn-outline-danger btn-sm"
          :class="{ active: response === 'no' }"
          @click="setResponse('no')"
        >
          👎 No
        </button>
      </div>

      <div v-if="response" class="mb-2">
        <label class="form-label small text-muted">Comment (optional)</label>
        <textarea
          class="form-control"
          rows="2"
          placeholder="Tell us what we could do better"
          v-model="comment"
          @input="saveDraft"
        ></textarea>
      </div>

      <div class="d-flex align-items-center justify-content-between">
        <small class="text-muted">
          {{ statusMessage }}
        </small>
        <button
          type="button"
          class="btn btn-primary btn-sm"
          :disabled="!response || isSubmitting"
          @click="submit"
        >
          <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1" role="status"></span>
          Send feedback
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  pageSlug: {
    type: String,
    default: '',
  },
  siteName: {
    type: String,
    default: 'ReSymf CMS',
  },
});

const response = ref('');
const comment = ref('');
const isSubmitting = ref(false);
const statusMessage = computed(() => {
  if (isSubmitting.value) return 'Sending...';
  if (response.value) return 'Ready to send your feedback.';
  return 'Choose Yes/No and optionally leave a note.';
});

const storageKey = computed(() => `cms_feedback_${props.pageSlug || 'page'}`);

const saveDraft = () => {
  const payload = { response: response.value, comment: comment.value };
  localStorage.setItem(storageKey.value, JSON.stringify(payload));
};

const loadDraft = () => {
  const raw = localStorage.getItem(storageKey.value);
  if (!raw) return;
  try {
    const payload = JSON.parse(raw);
    response.value = payload.response || '';
    comment.value = payload.comment || '';
  } catch (e) {
    // ignore invalid JSON
  }
};

const setResponse = (value) => {
  response.value = value;
  saveDraft();
};

const submit = async () => {
  isSubmitting.value = true;
  try {
    // Placeholder: here you would POST to an API endpoint
    await new Promise((res) => setTimeout(res, 600));
    localStorage.removeItem(storageKey.value);
    comment.value = '';
    response.value = '';
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(() => {
  loadDraft();
});
</script>
