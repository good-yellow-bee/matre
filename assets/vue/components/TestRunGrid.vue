<template>
  <div class="test-run-grid">
    <!-- Filters -->
    <div class="grid-header mb-4">
      <div class="row align-items-center g-3">
        <div class="col-md-2">
          <select v-model="filters.status" class="form-select" @change="applyFilters">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="running">Running</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="col-md-2">
          <select v-model="filters.type" class="form-select" @change="applyFilters">
            <option value="">All Types</option>
            <option value="mftf">MFTF</option>
            <option value="playwright">Playwright</option>
            <option value="both">Both</option>
          </select>
        </div>
        <div class="col-md-3">
          <select v-model="filters.suite" class="form-select" @change="applyFilters">
            <option value="">All Suites</option>
            <option v-for="suite in props.suites" :key="suite.id" :value="suite.id">
              {{ suite.name }}
            </option>
          </select>
        </div>
        <div class="col-auto ms-auto d-flex align-items-center gap-3">
          <div class="form-check mb-0">
            <input
              id="autoRefresh"
              v-model="autoRefresh"
              class="form-check-input"
              type="checkbox"
            />
            <label class="form-check-label" for="autoRefresh">
              Auto-refresh (30s)
            </label>
          </div>
          <button class="btn btn-outline-secondary" @click="fetchRuns(filters, meta.page)">
            <i class="bi bi-arrow-clockwise"></i> Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && runs.length === 0" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-3">Loading test runs...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      {{ error }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="fetchRuns(filters, meta.page)">
        Try Again
      </button>
    </div>

    <!-- Test Runs Table -->
    <div v-else-if="runs.length" class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 60px">ID</th>
              <th>Environment</th>
              <th style="width: 100px">Type</th>
              <th style="width: 120px">Status</th>
              <th style="width: 140px">Results</th>
              <th style="width: 100px">Duration</th>
              <th style="width: 140px">Created</th>
              <th class="text-end" style="width: 120px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="run in runs" :key="run.id">
              <td class="text-muted">#{{ run.id }}</td>
              <td>
                <span class="fw-semibold">{{ run.environment.name }}</span>
                <div v-if="run.suite" class="text-muted small">
                  Suite: {{ run.suite.name }}
                </div>
                <div v-else-if="run.testFilter" class="text-muted small">
                  Filter: {{ truncate(run.testFilter, 30) }}
                </div>
              </td>
              <td>
                <span :class="['badge', run.type === 'mftf' ? 'bg-primary' : run.type === 'playwright' ? 'bg-info' : 'bg-purple']">
                  {{ run.type.toUpperCase() }}
                </span>
              </td>
              <td>
                <span :class="getStatusBadgeClass(run.status)">
                  <i v-if="isRunningStatus(run.status)" class="bi bi-arrow-repeat spin me-1"></i>
                  {{ run.status.toUpperCase() }}
                </span>
              </td>
              <td>
                <span v-if="run.resultCounts.total > 0" class="small">
                  <span class="text-success">{{ run.resultCounts.passed }}</span>
                  /
                  <span class="text-danger">{{ run.resultCounts.failed }}</span>
                  /
                  <span class="text-warning">{{ run.resultCounts.broken }}</span>
                  /
                  <span class="text-secondary">{{ run.resultCounts.skipped }}</span>
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="text-muted small">{{ run.duration || '—' }}</td>
              <td class="text-muted small">{{ formatDate(run.createdAt) }}</td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a
                    :href="`/admin/test-runs/${run.id}`"
                    class="btn btn-outline-info"
                    title="View details"
                  >
                    <i class="bi bi-eye"></i>
                  </a>
                  <button
                    v-if="run.canBeCancelled"
                    class="btn btn-outline-danger"
                    title="Cancel"
                    @click="handleCancel(run)"
                    :disabled="cancelling === run.id"
                  >
                    <span v-if="cancelling === run.id" class="spinner-border spinner-border-sm"></span>
                    <i v-else class="bi bi-x-circle"></i>
                  </button>
                  <button
                    v-if="['completed', 'failed', 'cancelled'].includes(run.status)"
                    class="btn btn-outline-warning"
                    title="Retry"
                    @click="handleRetry(run)"
                    :disabled="retrying === run.id"
                  >
                    <span v-if="retrying === run.id" class="spinner-border spinner-border-sm"></span>
                    <i v-else class="bi bi-arrow-repeat"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <div class="text-muted small">
          Showing {{ ((meta.page - 1) * meta.limit) + 1 }} to
          {{ Math.min(meta.page * meta.limit, meta.total) }} of {{ meta.total }} runs
        </div>
        <nav v-if="meta.pages > 1" aria-label="Test runs pagination">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: meta.page === 1 }">
              <button class="page-link" @click="goToPage(meta.page - 1)" :disabled="meta.page === 1">
                <i class="bi bi-chevron-left"></i>
              </button>
            </li>
            <li
              v-for="pageNum in visiblePages"
              :key="pageNum"
              class="page-item"
              :class="{ active: meta.page === pageNum }"
            >
              <button class="page-link" @click="goToPage(pageNum)">
                {{ pageNum }}
              </button>
            </li>
            <li class="page-item" :class="{ disabled: meta.page === meta.pages }">
              <button class="page-link" @click="goToPage(meta.page + 1)" :disabled="meta.page === meta.pages">
                <i class="bi bi-chevron-right"></i>
              </button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-5">
      <i class="bi bi-play-circle" style="font-size: 4rem; color: var(--neutral-300);"></i>
      <p class="text-muted mt-3">No test runs yet.</p>
      <a href="/admin/test-runs/new" class="btn btn-primary">
        <i class="bi bi-play-fill"></i> Start First Run
      </a>
    </div>

    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      :class="['toast-notification', `toast-${toast.type}`]"
      role="alert"
    >
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTestRunGrid } from '../composables/useTestRunGrid.js';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
  csrfToken: {
    type: String,
    required: true,
  },
  suites: {
    type: Array,
    default: () => [],
  },
});

const {
  runs,
  meta,
  loading,
  error,
  fetchRuns,
  cancelRun,
  retryRun,
  goToPage,
} = useTestRunGrid(props.apiUrl, props.csrfToken);

const filters = ref({
  status: '',
  type: '',
  suite: '',
});
const autoRefresh = ref(true);
const cancelling = ref(null);
const retrying = ref(null);
const toast = ref({ show: false, message: '', type: 'success' });
let refreshInterval = null;

const applyFilters = () => {
  fetchRuns(filters.value);
};

const handleCancel = async (run) => {
  cancelling.value = run.id;
  try {
    await cancelRun(run.id);
    showToast('Run cancelled', 'success');
    fetchRuns(filters.value);
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    cancelling.value = null;
  }
};

const handleRetry = async (run) => {
  retrying.value = run.id;
  try {
    const newRun = await retryRun(run.id);
    showToast(`New run #${newRun.id} created`, 'success');
    window.location.href = `/admin/test-runs/${newRun.id}`;
  } catch (err) {
    showToast(err.message, 'error');
    retrying.value = null;
  }
};

const truncate = (str, len) => {
  if (!str) return '';
  return str.length > len ? str.slice(0, len) + '...' : str;
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getStatusBadgeClass = (status) => {
  const classes = {
    pending: 'badge bg-secondary',
    preparing: 'badge bg-info',
    cloning: 'badge bg-info',
    running: 'badge bg-warning',
    reporting: 'badge bg-info',
    completed: 'badge bg-success',
    failed: 'badge bg-danger',
    cancelled: 'badge bg-secondary',
  };
  return classes[status] || 'badge bg-secondary';
};

const isRunningStatus = (status) => {
  return ['pending', 'preparing', 'cloning', 'running', 'reporting'].includes(status);
};

const visiblePages = computed(() => {
  const pagesArr = [];
  const total = meta.value.pages;
  const current = meta.value.page;

  if (total <= 7) {
    for (let i = 1; i <= total; i++) {
      pagesArr.push(i);
    }
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pagesArr.push(i);
      pagesArr.push(total);
    } else if (current >= total - 3) {
      pagesArr.push(1);
      for (let i = total - 4; i <= total; i++) pagesArr.push(i);
    } else {
      pagesArr.push(1);
      for (let i = current - 1; i <= current + 1; i++) pagesArr.push(i);
      pagesArr.push(total);
    }
  }

  return pagesArr;
});

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

// Visibility API handler - pause polling when tab is hidden
const isTabVisible = ref(true);
const handleVisibilityChange = () => {
  isTabVisible.value = !document.hidden;
};

onMounted(() => {
  fetchRuns(filters.value);

  // Listen for tab visibility changes
  document.addEventListener('visibilitychange', handleVisibilityChange);

  // Auto-refresh - only when tab is visible
  refreshInterval = setInterval(() => {
    if (autoRefresh.value && isTabVisible.value) {
      fetchRuns(filters.value);
    }
  }, 30000);
});

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<style scoped>
.test-run-grid {
  width: 100%;
}

.table > :not(caption) > * > * {
  padding: 1rem 0.75rem;
}

.badge {
  font-weight: 500;
  font-size: 0.75rem;
  padding: 0.35em 0.65em;
}

.bg-purple {
  background-color: #8b5cf6 !important;
}

.btn-group-sm > .btn {
  padding: 0.25rem 0.5rem;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 12px 24px;
  border-radius: 8px;
  color: white;
  font-weight: 500;
  z-index: 9999;
  animation: slideIn 0.3s ease-out;
}

.toast-success {
  background-color: #10b981;
}

.toast-error {
  background-color: #ef4444;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
</style>
