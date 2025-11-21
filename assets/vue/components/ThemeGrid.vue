<template>
  <div class="theme-grid">
    <!-- Search Bar -->
    <div class="mb-3">
      <input
        type="text"
        class="form-control"
        placeholder="Search themes by name or description..."
        :value="searchQuery"
        @input="handleSearch"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading && themes.length === 0" class="text-center py-5">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>

    <!-- Grid Table -->
    <div v-if="!loading || themes.length > 0" class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th @click="sort('name')" class="sortable">
              Name
              <span v-if="sortField === 'name'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th>Description</th>
            <th>Colors</th>
            <th @click="sort('isActive')" class="sortable" style="width: 100px">
              Active
              <span v-if="sortField === 'isActive'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="sort('isDefault')" class="sortable" style="width: 100px">
              Default
              <span v-if="sortField === 'isDefault'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th style="width: 80px">Users</th>
            <th @click="sort('createdAt')" class="sortable" style="width: 120px">
              Created
              <span v-if="sortField === 'createdAt'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th style="width: 150px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="theme in themes" :key="theme.id">
            <td><strong>{{ theme.name }}</strong></td>
            <td>
              <span class="text-muted">{{ theme.description || '—' }}</span>
            </td>
            <td>
              <span
                v-if="theme.primaryColor"
                class="color-badge"
                :style="{ backgroundColor: theme.primaryColor }"
                :title="`Primary: ${theme.primaryColor}`"
              >
                {{ theme.primaryColor }}
              </span>
              <span
                v-if="theme.secondaryColor"
                class="color-badge ms-1"
                :style="{ backgroundColor: theme.secondaryColor }"
                :title="`Secondary: ${theme.secondaryColor}`"
              >
                {{ theme.secondaryColor }}
              </span>
              <span v-if="!theme.primaryColor && !theme.secondaryColor" class="text-muted">—</span>
            </td>
            <td>
              <button
                @click="handleToggleActive(theme)"
                :class="['btn', 'btn-sm', theme.isActive ? 'btn-success' : 'btn-secondary']"
              >
                {{ theme.isActive ? 'Yes' : 'No' }}
              </button>
            </td>
            <td>
              <button
                @click="handleToggleDefault(theme)"
                :class="['btn', 'btn-sm', theme.isDefault ? 'btn-primary' : 'btn-outline-primary']"
                :disabled="theme.isDefault"
              >
                {{ theme.isDefault ? 'Yes' : 'Set' }}
              </button>
            </td>
            <td>{{ theme.userCount }}</td>
            <td>{{ formatDate(theme.createdAt) }}</td>
            <td>
              <a
                :href="`/admin/themes/${theme.id}/edit`"
                class="btn btn-sm btn-warning me-1"
              >
                Edit
              </a>
              <button
                @click="handleDelete(theme)"
                :class="[
                  'btn',
                  'btn-sm',
                  deleteConfirm === theme.id ? 'btn-danger' : 'btn-outline-danger'
                ]"
                :disabled="theme.isDefault"
                :title="theme.isDefault ? 'Cannot delete default theme' : ''"
              >
                {{ deleteConfirm === theme.id ? 'Confirm?' : 'Delete' }}
              </button>
            </td>
          </tr>
          <tr v-if="themes.length === 0 && !loading">
            <td colspan="8" class="text-center text-muted py-4">
              No themes found
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav v-if="totalPages > 1" aria-label="Theme pagination">
      <ul class="pagination justify-content-center">
        <li class="page-item" :class="{ disabled: currentPage === 1 }">
          <a class="page-link" href="#" @click.prevent="goToPage(currentPage - 1)">Previous</a>
        </li>
        <li
          v-for="page in visiblePages"
          :key="page"
          class="page-item"
          :class="{ active: currentPage === page }"
        >
          <a class="page-link" href="#" @click.prevent="goToPage(page)">{{ page }}</a>
        </li>
        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
          <a class="page-link" href="#" @click.prevent="goToPage(currentPage + 1)">Next</a>
        </li>
      </ul>
    </nav>

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
import { ref, computed, onMounted } from 'vue';
import { useThemeGrid } from '../composables/useThemeGrid.js';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
  csrfToken: {
    type: String,
    required: true,
  },
});

const {
  themes,
  loading,
  error,
  searchQuery,
  sortField,
  sortOrder,
  currentPage,
  totalPages,
  fetchThemes,
  search,
  sort,
  goToPage,
  toggleActive,
  toggleDefault,
  deleteTheme,
} = useThemeGrid(props.apiUrl, props.csrfToken);

const deleteConfirm = ref(null);
const toast = ref({ show: false, message: '', type: 'success' });
let deleteTimeout = null;

const handleSearch = (event) => {
  search(event.target.value);
};

const handleToggleActive = async (theme) => {
  const result = await toggleActive(theme);
  showToast(result.message, result.success ? 'success' : 'error');
};

const handleToggleDefault = async (theme) => {
  const result = await toggleDefault(theme);
  showToast(result.message, result.success ? 'success' : 'error');
};

const handleDelete = async (theme) => {
  if (theme.isDefault) {
    showToast('Cannot delete default theme', 'error');
    return;
  }

  if (deleteConfirm.value === theme.id) {
    clearTimeout(deleteTimeout);
    deleteConfirm.value = null;

    const result = await deleteTheme(theme.id, props.csrfToken);
    showToast(result.message, result.success ? 'success' : 'error');
  } else {
    deleteConfirm.value = theme.id;
    deleteTimeout = setTimeout(() => {
      deleteConfirm.value = null;
    }, 3000);
  }
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 7;
  const total = totalPages.value;
  const current = currentPage.value;

  if (total <= maxVisible) {
    for (let i = 1; i <= total; i++) {
      pages.push(i);
    }
  } else {
    if (current <= 4) {
      for (let i = 1; i <= 5; i++) pages.push(i);
      pages.push('...');
      pages.push(total);
    } else if (current >= total - 3) {
      pages.push(1);
      pages.push('...');
      for (let i = total - 4; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      pages.push('...');
      for (let i = current - 1; i <= current + 1; i++) pages.push(i);
      pages.push('...');
      pages.push(total);
    }
  }

  return pages.filter(p => p !== '...' || pages.indexOf(p) === pages.lastIndexOf(p));
});

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

onMounted(() => {
  fetchThemes();
});
</script>

<style scoped>
.sortable {
  cursor: pointer;
  user-select: none;
}

.sortable:hover {
  background-color: rgba(0, 0, 0, 0.05);
}

.color-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-family: monospace;
  color: white;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
  border: 1px solid rgba(0, 0, 0, 0.2);
}

.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 12px 24px;
  border-radius: 4px;
  color: white;
  font-weight: 500;
  z-index: 9999;
  animation: slideIn 0.3s ease-out;
}

.toast-success {
  background-color: #28a745;
}

.toast-error {
  background-color: #dc3545;
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
