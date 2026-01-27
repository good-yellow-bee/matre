<template>
  <div class="audit-log-grid">
    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-2">
            <label class="form-label small text-muted">Entity Type</label>
            <select v-model="filters.entityType" class="form-select form-select-sm" @change="applyFilters">
              <option value="">All Types</option>
              <option v-for="type in filterOptions.entityTypes" :key="type" :value="type">
                {{ type }}
              </option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted">Action</label>
            <select v-model="filters.action" class="form-select form-select-sm" @change="applyFilters">
              <option value="">All Actions</option>
              <option v-for="action in filterOptions.actions" :key="action" :value="action">
                {{ action }}
              </option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted">User</label>
            <select v-model="filters.userId" class="form-select form-select-sm" @change="applyFilters">
              <option value="">All Users</option>
              <option v-for="user in filterOptions.users" :key="user.id" :value="user.id">
                {{ user.username }}
              </option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted">From Date</label>
            <input
              v-model="filters.dateFrom"
              type="date"
              class="form-control form-control-sm"
              @change="applyFilters"
            />
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted">To Date</label>
            <input
              v-model="filters.dateTo"
              type="date"
              class="form-control form-control-sm"
              @change="applyFilters"
            />
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-secondary btn-sm w-100" @click="resetFilters">
              <i class="bi bi-x-lg me-1"></i> Reset
            </button>
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-md-6">
            <div class="input-group input-group-sm">
              <span class="input-group-text">
                <i class="bi bi-search"></i>
              </span>
              <input
                v-model="searchQuery"
                type="text"
                class="form-control"
                placeholder="Search by entity label..."
                @input="handleSearch"
              />
              <button v-if="searchQuery" class="btn btn-outline-secondary" @click="clearSearch">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && logs.length === 0" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-3">Loading audit logs...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="alert alert-danger" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      {{ error }}
      <button class="btn btn-sm btn-outline-danger ms-3" @click="fetchLogs">
        Try Again
      </button>
    </div>

    <!-- Audit Logs Table -->
    <div v-else-if="logs.length" class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th @click="sort('createdAt')" class="sortable" style="width: 150px">
                Date/Time
                <i :class="getSortIcon('createdAt')"></i>
              </th>
              <th @click="sort('entityType')" class="sortable" style="width: 130px">
                Entity Type
                <i :class="getSortIcon('entityType')"></i>
              </th>
              <th>Entity</th>
              <th @click="sort('action')" class="sortable text-center" style="width: 100px">
                Action
                <i :class="getSortIcon('action')"></i>
              </th>
              <th style="width: 130px">User</th>
              <th style="width: 120px">IP Address</th>
              <th class="text-center" style="width: 80px">Details</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="log in logs" :key="log.id">
              <tr>
                <td class="text-muted small">{{ formatDate(log.createdAt) }}</td>
                <td>
                  <span class="badge bg-secondary">{{ log.entityType }}</span>
                </td>
                <td>
                  <span class="fw-semibold">{{ log.entityLabel || `ID: ${log.entityId}` }}</span>
                  <span v-if="log.entityLabel" class="text-muted small ms-1">(#{{ log.entityId }})</span>
                </td>
                <td class="text-center">
                  <span :class="getActionBadgeClass(log.action)">
                    {{ log.action }}
                  </span>
                </td>
                <td>
                  <span v-if="log.user">{{ log.user.username }}</span>
                  <span v-else class="text-muted">System</span>
                </td>
                <td class="text-muted small">{{ log.ipAddress || '—' }}</td>
                <td class="text-center">
                  <button
                    class="btn btn-sm btn-outline-secondary"
                    @click="toggleExpanded(log.id)"
                    :title="expandedLogs.has(log.id) ? 'Hide details' : 'Show details'"
                  >
                    <i :class="expandedLogs.has(log.id) ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
                  </button>
                </td>
              </tr>
              <!-- Expanded Details Row -->
              <tr v-if="expandedLogs.has(log.id)" class="expanded-row">
                <td colspan="7" class="bg-light">
                  <div class="p-3">
                    <div v-if="log.action === 'update' && log.changedFields" class="mb-3">
                      <strong class="text-muted small">Changed Fields:</strong>
                      <span class="ms-2">
                        <span
                          v-for="field in log.changedFields"
                          :key="field"
                          class="badge bg-info me-1"
                        >
                          {{ field }}
                        </span>
                      </span>
                    </div>
                    <div class="row">
                      <div v-if="log.oldData" class="col-md-6">
                        <div class="card">
                          <div class="card-header py-2 bg-danger bg-opacity-10">
                            <strong class="text-danger small">
                              <i class="bi bi-dash-circle me-1"></i>
                              {{ log.action === 'delete' ? 'Deleted Data' : 'Old Values' }}
                            </strong>
                          </div>
                          <div class="card-body p-2">
                            <pre class="mb-0 small">{{ formatJson(log.oldData) }}</pre>
                          </div>
                        </div>
                      </div>
                      <div v-if="log.newData" class="col-md-6">
                        <div class="card">
                          <div class="card-header py-2 bg-success bg-opacity-10">
                            <strong class="text-success small">
                              <i class="bi bi-plus-circle me-1"></i>
                              {{ log.action === 'create' ? 'Created Data' : 'New Values' }}
                            </strong>
                          </div>
                          <div class="card-body p-2">
                            <pre class="mb-0 small">{{ formatJson(log.newData) }}</pre>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <div class="text-muted small">
          Showing {{ ((currentPage - 1) * perPage) + 1 }} to
          {{ Math.min(currentPage * perPage, total) }} of {{ total }} entries
        </div>
        <nav v-if="totalPages > 1" aria-label="Audit log pagination">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
              <button class="page-link" @click="goToPage(currentPage - 1)" :disabled="currentPage === 1">
                <i class="bi bi-chevron-left"></i>
              </button>
            </li>
            <li
              v-for="pageNum in visiblePages"
              :key="pageNum"
              class="page-item"
              :class="{ active: currentPage === pageNum }"
            >
              <button class="page-link" @click="goToPage(pageNum)">
                {{ pageNum }}
              </button>
            </li>
            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
              <button class="page-link" @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages">
                <i class="bi bi-chevron-right"></i>
              </button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-5">
      <i class="bi bi-clock-history" style="font-size: 4rem; color: var(--neutral-300);"></i>
      <p class="text-muted mt-3">
        {{ hasActiveFilters ? 'No audit logs found matching your filters.' : 'No audit logs yet.' }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuditLogGrid } from '../composables/useAuditLogGrid.js';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
  filtersUrl: {
    type: String,
    required: true,
  },
});

const {
  logs,
  loading,
  error,
  searchQuery,
  sortField,
  sortOrder,
  currentPage,
  perPage,
  total,
  totalPages,
  filters,
  filterOptions,
  fetchLogs,
  fetchFilterOptions,
  search,
  sort: doSort,
  goToPage,
  applyFilters: doApplyFilters,
  resetFilters: doResetFilters,
} = useAuditLogGrid(props.apiUrl, props.filtersUrl);

const expandedLogs = ref(new Set());
let searchTimeout = null;

const hasActiveFilters = computed(() => {
  return filters.value.entityType ||
    filters.value.action ||
    filters.value.userId ||
    filters.value.dateFrom ||
    filters.value.dateTo ||
    searchQuery.value;
});

// Search with debounce
const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    search(searchQuery.value);
  }, 300);
};

const clearSearch = () => {
  searchQuery.value = '';
  search('');
};

// Sorting
const sort = (field) => {
  doSort(field);
};

const getSortIcon = (field) => {
  if (sortField.value !== field) {
    return 'bi bi-chevron-expand text-muted';
  }
  return sortOrder.value === 'asc' ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
};

// Filters
const applyFilters = () => {
  doApplyFilters();
};

const resetFilters = () => {
  doResetFilters();
  expandedLogs.value.clear();
};

// Expand/collapse
const toggleExpanded = (id) => {
  if (expandedLogs.value.has(id)) {
    expandedLogs.value.delete(id);
  } else {
    expandedLogs.value.add(id);
  }
};

// Helpers
const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatJson = (obj) => {
  if (!obj) return '';
  return JSON.stringify(obj, null, 2);
};

const getActionBadgeClass = (action) => {
  const classes = {
    create: 'badge bg-success',
    update: 'badge bg-warning text-dark',
    delete: 'badge bg-danger',
  };
  return classes[action] || 'badge bg-secondary';
};

// Pagination
const visiblePages = computed(() => {
  const pagesArr = [];
  const totalPagesVal = totalPages.value;
  const current = currentPage.value;

  if (totalPagesVal <= 7) {
    for (let i = 1; i <= totalPagesVal; i++) {
      pagesArr.push(i);
    }
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pagesArr.push(i);
      pagesArr.push(totalPagesVal);
    } else if (current >= totalPagesVal - 3) {
      pagesArr.push(1);
      for (let i = totalPagesVal - 4; i <= totalPagesVal; i++) pagesArr.push(i);
    } else {
      pagesArr.push(1);
      for (let i = current - 1; i <= current + 1; i++) pagesArr.push(i);
      pagesArr.push(totalPagesVal);
    }
  }

  return pagesArr;
});

onMounted(() => {
  fetchFilterOptions();
  fetchLogs();
});
</script>

<style scoped>
.audit-log-grid {
  width: 100%;
}

.sortable {
  cursor: pointer;
  user-select: none;
  transition: background-color 0.2s;
}

.sortable:hover {
  background-color: var(--neutral-100, #f1f5f9);
}

.sortable i {
  font-size: 0.75rem;
  margin-left: 0.25rem;
}

.table > :not(caption) > * > * {
  padding: 0.75rem 0.5rem;
}

.badge {
  font-weight: 500;
  font-size: 0.7rem;
  padding: 0.35em 0.65em;
}

.expanded-row td {
  border-top: none;
}

.expanded-row pre {
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 300px;
  overflow-y: auto;
  background: #f8f9fa;
  padding: 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}
</style>
