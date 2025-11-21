import { ref, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';

export function useCategoryGrid(apiUrl, csrfToken) {
  const categories = ref([]);
  const loading = ref(false);
  const error = ref(null);
  const searchQuery = ref('');
  const sortField = ref('displayOrder');
  const sortOrder = ref('asc');
  const currentPage = ref(1);
  const perPage = ref(20);
  const total = ref(0);

  const totalPages = computed(() => Math.ceil(total.value / perPage.value));

  const fetchCategories = async () => {
    loading.value = true;
    error.value = null;

    try {
      const params = new URLSearchParams({
        search: searchQuery.value,
        sort: sortField.value,
        order: sortOrder.value,
        page: currentPage.value,
        perPage: perPage.value,
      });

      const response = await fetch(`${apiUrl}?${params}`);
      if (!response.ok) throw new Error('Failed to fetch categories');

      const data = await response.json();
      categories.value = data.data;
      total.value = data.total;
      currentPage.value = data.page;
    } catch (err) {
      error.value = err.message;
      console.error('Error fetching categories:', err);
    } finally {
      loading.value = false;
    }
  };

  const debouncedFetch = useDebounceFn(fetchCategories, 300);

  const search = (query) => {
    searchQuery.value = query;
    currentPage.value = 1;
    debouncedFetch();
  };

  const sort = (field) => {
    if (sortField.value === field) {
      sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
      sortField.value = field;
      sortOrder.value = 'asc';
    }
    currentPage.value = 1;
    fetchCategories();
  };

  const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page;
      fetchCategories();
    }
  };

  const toggleActive = async (category) => {
    const originalStatus = category.isActive;
    category.isActive = !category.isActive; // Optimistic update

    try {
      const response = await fetch(`/admin/categories/${category.id}/toggle-active`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `_token=${encodeURIComponent(csrfToken)}`,
      });

      if (!response.ok) {
        throw new Error('Failed to toggle category status');
      }

      return { success: true, message: `Category ${category.isActive ? 'activated' : 'deactivated'}` };
    } catch (err) {
      category.isActive = originalStatus; // Revert on failure
      return { success: false, message: err.message };
    }
  };

  const reorder = async (items) => {
    try {
      const response = await fetch('/api/categories/reorder', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ items }),
      });

      if (!response.ok) throw new Error('Failed to reorder categories');

      const data = await response.json();
      return { success: true, message: data.message };
    } catch (err) {
      return { success: false, message: err.message };
    }
  };

  const deleteCategory = async (id, csrfToken) => {
    try {
      const response = await fetch(`/admin/categories/${id}/delete`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `_token=${encodeURIComponent(csrfToken)}`,
      });

      if (!response.ok) throw new Error('Failed to delete category');

      await fetchCategories(); // Refresh list
      return { success: true, message: 'Category deleted' };
    } catch (err) {
      return { success: false, message: err.message };
    }
  };

  return {
    categories,
    loading,
    error,
    searchQuery,
    sortField,
    sortOrder,
    currentPage,
    perPage,
    total,
    totalPages,
    fetchCategories,
    search,
    sort,
    goToPage,
    toggleActive,
    reorder,
    deleteCategory,
  };
}
