import { ref, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';

export function useThemeGrid(apiUrl, csrfToken) {
  const themes = ref([]);
  const loading = ref(false);
  const error = ref(null);
  const searchQuery = ref('');
  const sortField = ref('name');
  const sortOrder = ref('asc');
  const currentPage = ref(1);
  const perPage = ref(20);
  const total = ref(0);

  const totalPages = computed(() => Math.ceil(total.value / perPage.value));

  const fetchThemes = async () => {
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
      if (!response.ok) throw new Error('Failed to fetch themes');

      const data = await response.json();
      themes.value = data.data;
      total.value = data.total;
      currentPage.value = data.page;
    } catch (err) {
      error.value = err.message;
      console.error('Error fetching themes:', err);
    } finally {
      loading.value = false;
    }
  };

  const debouncedFetch = useDebounceFn(fetchThemes, 300);

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
    fetchThemes();
  };

  const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page;
      fetchThemes();
    }
  };

  const toggleActive = async (theme) => {
    const originalStatus = theme.isActive;
    theme.isActive = !theme.isActive; // Optimistic update

    try {
      const response = await fetch(`/admin/themes/${theme.id}/toggle-active`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `_token=${encodeURIComponent(csrfToken)}`,
      });

      if (!response.ok) {
        throw new Error('Failed to toggle theme status');
      }

      return { success: true, message: `Theme ${theme.isActive ? 'activated' : 'deactivated'}` };
    } catch (err) {
      theme.isActive = originalStatus; // Revert on failure
      return { success: false, message: err.message };
    }
  };

  const toggleDefault = async (theme) => {
    if (theme.isDefault) {
      return { success: false, message: 'Cannot unset default theme' };
    }

    const originalDefaults = themes.value.map(t => ({ id: t.id, isDefault: t.isDefault }));
    
    // Optimistic update
    themes.value.forEach(t => {
      t.isDefault = t.id === theme.id;
    });

    try {
      const response = await fetch(`/admin/themes/${theme.id}/toggle-default`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `_token=${encodeURIComponent(csrfToken)}`,
      });

      if (!response.ok) {
        throw new Error('Failed to set theme as default');
      }

      return { success: true, message: `Theme "${theme.name}" set as default` };
    } catch (err) {
      // Revert on failure
      originalDefaults.forEach(orig => {
        const theme = themes.value.find(t => t.id === orig.id);
        if (theme) theme.isDefault = orig.isDefault;
      });
      return { success: false, message: err.message };
    }
  };

  const deleteTheme = async (id, csrfToken) => {
    try {
      const response = await fetch(`/admin/themes/${id}/delete`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `_token=${encodeURIComponent(csrfToken)}`,
      });

      if (!response.ok) throw new Error('Failed to delete theme');

      await fetchThemes(); // Refresh list
      return { success: true, message: 'Theme deleted' };
    } catch (err) {
      return { success: false, message: err.message };
    }
  };

  return {
    themes,
    loading,
    error,
    searchQuery,
    sortField,
    sortOrder,
    currentPage,
    perPage,
    total,
    totalPages,
    fetchThemes,
    search,
    sort,
    goToPage,
    toggleActive,
    toggleDefault,
    deleteTheme,
  };
}
