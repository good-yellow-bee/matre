<template>
  <div class="test-step-tree">
    <!-- Loading state -->
    <div v-if="loading" class="text-center py-4">
      <div class="spinner-border spinner-border-sm text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <span class="ms-2 text-muted">Loading steps...</span>
    </div>

    <!-- Error state -->
    <div v-else-if="error && !steps.length" class="alert alert-warning mb-0">
      <i class="bi bi-exclamation-triangle me-2"></i>{{ error }}
    </div>

    <!-- Steps content -->
    <div v-else-if="steps.length">
      <!-- Header with test info -->
      <div class="step-header mb-3 pb-2 border-bottom d-flex align-items-center gap-3">
        <span :class="['badge', statusBadgeClass]">{{ testStatus }}</span>
        <span v-if="totalDuration" class="text-muted small">
          <i class="bi bi-clock me-1"></i>{{ formatDuration(totalDuration) }}
        </span>
        <span class="text-muted small">{{ steps.length }} top-level steps</span>
      </div>

      <!-- Step list -->
      <div class="step-list">
        <StepNode
          v-for="(step, index) in steps"
          :key="index"
          :step="step"
          :depth="0"
        />
      </div>

      <!-- Error message if present -->
      <div v-if="errorMessage" class="mt-3 p-3 bg-danger-subtle border border-danger rounded">
        <div class="fw-semibold text-danger mb-1">
          <i class="bi bi-x-circle me-1"></i>Error Details
        </div>
        <pre class="mb-0 small text-danger-emphasis">{{ errorMessage }}</pre>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-4 text-muted">
      <i class="bi bi-list-task fs-3 d-block mb-2"></i>
      No step data available
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import StepNode from './StepNode.vue';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
});

const loading = ref(true);
const error = ref(null);
const testName = ref('');
const testStatus = ref('');
const totalDuration = ref(null);
const steps = ref([]);
const errorMessage = ref(null);

const statusBadgeClass = computed(() => {
  const status = testStatus.value?.toLowerCase();
  return {
    passed: 'bg-success',
    failed: 'bg-danger',
    broken: 'bg-warning text-dark',
    skipped: 'bg-secondary',
  }[status] || 'bg-secondary';
});

const formatDuration = (seconds) => {
  if (seconds === null || seconds === undefined) return '';
  if (seconds < 0.001) return '<1ms';
  if (seconds < 1) return `${Math.round(seconds * 1000)}ms`;
  if (seconds < 60) return `${seconds.toFixed(2)}s`;
  const mins = Math.floor(seconds / 60);
  const secs = Math.round(seconds % 60);
  return `${mins}m ${secs}s`;
};

const fetchSteps = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await fetch(props.apiUrl);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    testName.value = data.testName || '';
    testStatus.value = data.status || '';
    totalDuration.value = data.duration;
    steps.value = data.steps || [];
    errorMessage.value = data.error || null;

    if (data.error && !data.steps?.length) {
      error.value = data.error;
    }

    // Emit event if test name was updated (backfilled from Allure)
    if (testName.value && testName.value !== 'Unknown') {
      window.dispatchEvent(new CustomEvent('test-name-updated', {
        detail: { testName: testName.value, apiUrl: props.apiUrl }
      }));
    }
  } catch (e) {
    error.value = `Failed to load steps: ${e.message}`;
  } finally {
    loading.value = false;
  }
};

onMounted(fetchSteps);
</script>

<style scoped>
.test-step-tree {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.85rem;
}

.step-header {
  font-family: inherit;
}

pre {
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
