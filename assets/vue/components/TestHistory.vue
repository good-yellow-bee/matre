<template>
  <div class="test-history">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-3">Loading test history...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      {{ error }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="fetchHistory">
        Try Again
      </button>
    </div>

    <!-- History Timeline -->
    <div v-else-if="history.length" class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
          <i class="bi bi-clock-history me-2"></i>
          Last {{ history.length }} Executions
        </h5>
        <select
          v-model="selectedEnvironmentId"
          class="form-select form-select-sm w-auto"
          @change="onEnvironmentChange"
        >
          <option v-for="env in environments" :key="env.id" :value="env.id">
            {{ env.name }}
          </option>
        </select>
      </div>

      <div class="history-timeline">
        <div
          v-for="(result, index) in history"
          :key="result.id"
          class="history-item"
          :class="{ 'border-top': index > 0 }"
        >
          <div class="d-flex align-items-start p-3">
            <!-- Status indicator -->
            <div class="status-indicator me-3">
              <span
                :class="['status-badge', `status-${result.status}`]"
                :title="result.status"
              >
                <i :class="getStatusIcon(result.status)"></i>
              </span>
            </div>

            <!-- Details -->
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <span :class="['badge', getStatusBadgeClass(result.status)]">
                    {{ result.status.toUpperCase() }}
                  </span>
                  <span class="text-muted ms-2 small">
                    {{ formatDate(result.testRun.startedAt) }}
                  </span>
                </div>
                <div class="text-end">
                  <span v-if="result.duration" class="text-muted small">
                    <i class="bi bi-stopwatch me-1"></i>
                    {{ result.durationFormatted }}
                  </span>
                  <a
                    :href="getTestRunUrl(result.testRun.id)"
                    class="btn btn-sm btn-outline-primary ms-2"
                    title="View full test run"
                  >
                    <i class="bi bi-box-arrow-up-right"></i>
                    Run #{{ result.testRun.id }}
                  </a>
                </div>
              </div>

              <!-- Error message (collapsed by default) -->
              <div v-if="result.errorMessage" class="mt-2">
                <button
                  class="btn btn-sm btn-outline-danger"
                  @click="toggleError(result.id)"
                >
                  <i class="bi bi-bug me-1"></i>
                  {{ expandedErrors.has(result.id) ? 'Hide' : 'Show' }} Error
                </button>
                <pre
                  v-if="expandedErrors.has(result.id)"
                  class="error-message mt-2 p-2 bg-light rounded small"
                >{{ result.errorMessage }}</pre>
              </div>

              <!-- Trigger info -->
              <div class="text-muted small mt-1">
                <i class="bi bi-play-circle me-1"></i>
                Triggered by {{ result.testRun.triggeredBy }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary Footer -->
      <div class="card-footer bg-white">
        <div class="row text-center">
          <div class="col">
            <div class="h4 mb-0 text-success">{{ stats.passed }}</div>
            <small class="text-muted">Passed</small>
          </div>
          <div class="col">
            <div class="h4 mb-0 text-danger">{{ stats.failed }}</div>
            <small class="text-muted">Failed</small>
          </div>
          <div class="col">
            <div class="h4 mb-0 text-warning">{{ stats.broken }}</div>
            <small class="text-muted">Broken</small>
          </div>
          <div class="col">
            <div class="h4 mb-0 text-secondary">{{ stats.skipped }}</div>
            <small class="text-muted">Skipped</small>
          </div>
          <div class="col">
            <div class="h4 mb-0 text-info">{{ passRate }}%</div>
            <small class="text-muted">Pass Rate</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-5">
      <i class="bi bi-clock-history" style="font-size: 4rem; color: var(--neutral-300);"></i>
      <p class="text-muted mt-3">No execution history found for this test.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  testId: { type: String, required: true },
  environmentId: { type: Number, required: true },
  environments: { type: Array, default: () => [] },
  testRunBaseUrl: { type: String, required: true },
});

const history = ref([]);
const meta = ref({});
const loading = ref(false);
const error = ref(null);
const expandedErrors = ref(new Set());
const selectedEnvironmentId = ref(props.environmentId);

const stats = computed(() => {
  const counts = { passed: 0, failed: 0, broken: 0, skipped: 0 };
  history.value.forEach(r => {
    if (counts[r.status] !== undefined) counts[r.status]++;
  });
  return counts;
});

const passRate = computed(() => {
  const total = history.value.length;
  if (total === 0) return 0;
  return Math.round((stats.value.passed / total) * 100);
});

const fetchHistory = async () => {
  loading.value = true;
  error.value = null;

  try {
    const params = new URLSearchParams({
      testId: props.testId,
      environmentId: selectedEnvironmentId.value.toString(),
    });

    const response = await fetch(`${props.apiUrl}?${params}`, {
      headers: { 'Accept': 'application/json' },
    });

    if (!response.ok) {
      throw new Error('Failed to load test history');
    }

    const data = await response.json();
    history.value = data.data;
    meta.value = data.meta;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
};

const onEnvironmentChange = () => {
  const url = new URL(window.location);
  url.searchParams.set('environmentId', selectedEnvironmentId.value.toString());
  window.history.pushState({}, '', url);
  fetchHistory();
};

const getTestRunUrl = (runId) => {
  return props.testRunBaseUrl.replace('__ID__', runId);
};

const getStatusIcon = (status) => {
  const icons = {
    passed: 'bi bi-check-circle-fill',
    failed: 'bi bi-x-circle-fill',
    broken: 'bi bi-exclamation-triangle-fill',
    skipped: 'bi bi-dash-circle-fill',
  };
  return icons[status] || 'bi bi-question-circle-fill';
};

const getStatusBadgeClass = (status) => {
  const classes = {
    passed: 'bg-success',
    failed: 'bg-danger',
    broken: 'bg-warning',
    skipped: 'bg-secondary',
  };
  return classes[status] || 'bg-secondary';
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const toggleError = (resultId) => {
  if (expandedErrors.value.has(resultId)) {
    expandedErrors.value.delete(resultId);
  } else {
    expandedErrors.value.add(resultId);
  }
  expandedErrors.value = new Set(expandedErrors.value);
};

onMounted(() => {
  fetchHistory();
});
</script>

<style scoped>
.test-history {
  width: 100%;
}

.history-timeline {
  max-height: 600px;
  overflow-y: auto;
}

.history-item {
  transition: background-color 0.2s;
}

.history-item:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-size: 1rem;
}

.status-passed { background-color: #d1fae5; color: #059669; }
.status-failed { background-color: #fee2e2; color: #dc2626; }
.status-broken { background-color: #fef3c7; color: #d97706; }
.status-skipped { background-color: #f3f4f6; color: #6b7280; }

.error-message {
  max-height: 200px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-word;
  font-family: monospace;
  font-size: 0.8rem;
}
</style>
