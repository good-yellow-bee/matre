<template>
  <div class="category-grid">
    <!-- Search Bar -->
    <div class="mb-3">
      <input
        type="text"
        class="form-control"
        placeholder="Search categories..."
        :value="searchQuery"
        @input="handleSearch"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading && categories.length === 0" class="text-center py-5">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>

    <!-- Grid Table -->
    <div v-if="!loading || categories.length > 0" class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th style="width: 40px"></th>
            <th @click="sort('name')" class="sortable">
              Name
              <span v-if="sortField === 'name'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="sort('slug')" class="sortable">
              Slug
              <span v-if="sortField === 'slug'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th @click="sort('isActive')" class="sortable" style="width: 100px">
              Active
              <span v-if="sortField === 'isActive'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th style="width: 80px">Pages</th>
            <th @click="sort('displayOrder')" class="sortable" style="width: 80px">
              Order
              <span v-if="sortField === 'displayOrder'">{{ sortOrder === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th style="width: 150px">Actions</th>
          </tr>
        </thead>
        <draggable
          v-model="categories"
          tag="tbody"
          item-key="id"
          handle=".drag-handle"
          @end="handleDragEnd"
        >
          <template #item="{ element: category }">
            <tr>
              <td class="text-center">
                <span class="drag-handle" style="cursor: move">⋮⋮</span>
              </td>
              <td>{{ category.name }}</td>
              <td><code>{{ category.slug }}</code></td>
              <td>
                <button
                  @click="handleToggleActive(category)"
                  :class="['btn', 'btn-sm', category.isActive ? 'btn-success' : 'btn-secondary']"
                >
                  {{ category.isActive ? 'Yes' : 'No' }}
                </button>
              </td>
              <td>{{ category.pageCount }}</td>
              <td>{{ category.displayOrder }}</td>
              <td>
                <a
                  :href="`/admin/categories/${category.id}/edit`"
                  class="btn btn-sm btn-warning me-1"
                >
                  Edit
                </a>
                <button
                  @click="handleDelete(category)"
                  :class="[
                    'btn',
                    'btn-sm',
                    deleteConfirm === category.id ? 'btn-danger' : 'btn-outline-danger'
                  ]"
                >
                  {{ deleteConfirm === category.id ? 'Confirm?' : 'Delete' }}
                </button>
              </td>
            </tr>
          </template>
        </draggable>
      </table>
    </div>

    <!-- Pagination -->
    <nav v-if="totalPages > 1" aria-label="Category pagination">
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
import draggable from 'vuedraggable';
import { useCategoryGrid } from '../composables/useCategoryGrid.js';

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
  categories,
  loading,
  error,
  searchQuery,
  sortField,
  sortOrder,
  currentPage,
  totalPages,
  fetchCategories,
  search,
  sort,
  goToPage,
  toggleActive,
  reorder,
  deleteCategory,
} = useCategoryGrid(props.apiUrl, props.csrfToken);

const deleteConfirm = ref(null);
const toast = ref({ show: false, message: '', type: 'success' });
let deleteTimeout = null;

const handleSearch = (event) => {
  search(event.target.value);
};

const handleToggleActive = async (category) => {
  const result = await toggleActive(category);
  showToast(result.message, result.success ? 'success' : 'error');
};

const handleDelete = async (category) => {
  if (deleteConfirm.value === category.id) {
    clearTimeout(deleteTimeout);
    deleteConfirm.value = null;

    const result = await deleteCategory(category.id, props.csrfToken);
    showToast(result.message, result.success ? 'success' : 'error');
  } else {
    deleteConfirm.value = category.id;
    deleteTimeout = setTimeout(() => {
      deleteConfirm.value = null;
    }, 3000);
  }
};

const handleDragEnd = async () => {
  const items = categories.value.map((cat, index) => ({
    id: cat.id,
    displayOrder: index,
  }));

  // Update local display order
  categories.value.forEach((cat, index) => {
    cat.displayOrder = index;
  });

  const result = await reorder(items);
  if (!result.success) {
    showToast(result.message, 'error');
    await fetchCategories(); // Revert on failure
  } else {
    showToast(result.message, 'success');
  }
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
  fetchCategories();
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

.drag-handle {
  opacity: 0.5;
  display: inline-block;
  font-weight: bold;
}

.drag-handle:hover {
  opacity: 1;
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
