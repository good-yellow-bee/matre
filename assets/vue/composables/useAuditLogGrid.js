import { ref, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';

export function useAuditLogGrid(apiUrl, filtersUrl) {
  const logs = ref([]);
  const loading = ref(false);
  const error = ref(null);
  const filterError = ref(null);
  const searchQuery = ref('');
  const sortField = ref('createdAt');
  const sortOrder = ref('desc');
  const currentPage = ref(1);
  const perPage = ref(20);
  const total = ref(0);

  // Filter state
  const filters = ref({
    entityType: '',
    action: '',
    userId: '',
    dateFrom: '',
    dateTo: '',
  });

  // Filter options
  const filterOptions = ref({
    entityTypes: [],
    actions: [],
    users: [],
  });

  const totalPages = computed(() => Math.ceil(total.value / perPage.value));

  const fetchLogs = async () => {
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

      // Add filters
      if (filters.value.entityType) params.set('entityType', filters.value.entityType);
      if (filters.value.action) params.set('action', filters.value.action);
      if (filters.value.userId) params.set('userId', filters.value.userId);
      if (filters.value.dateFrom) params.set('dateFrom', filters.value.dateFrom);
      if (filters.value.dateTo) params.set('dateTo', filters.value.dateTo);

      const response = await fetch(`${apiUrl}?${params}`);
      if (!response.ok) throw new Error('Failed to fetch audit logs');

      const data = await response.json();
      logs.value = data.data;
      total.value = data.total;
      currentPage.value = data.page;
    } catch (err) {
      error.value = err.message;
      console.error('Error fetching audit logs:', err);
    } finally {
      loading.value = false;
    }
  };

  const fetchFilterOptions = async () => {
    filterError.value = null;
    try {
      const response = await fetch(filtersUrl);
      if (!response.ok) throw new Error('Failed to fetch filter options');

      const data = await response.json();
      filterOptions.value = data;
    } catch (err) {
      filterError.value = 'Unable to load filter options';
      console.error('Error fetching filter options:', err);
    }
  };

  const debouncedFetch = useDebounceFn(fetchLogs, 300);

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
      sortOrder.value = field === 'createdAt' ? 'desc' : 'asc';
    }
    currentPage.value = 1;
    fetchLogs();
  };

  const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page;
      fetchLogs();
    }
  };

  const applyFilters = () => {
    currentPage.value = 1;
    fetchLogs();
  };

  const resetFilters = () => {
    filters.value = {
      entityType: '',
      action: '',
      userId: '',
      dateFrom: '',
      dateTo: '',
    };
    searchQuery.value = '';
    currentPage.value = 1;
    fetchLogs();
  };

  return {
    logs,
    loading,
    error,
    filterError,
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
    sort,
    goToPage,
    applyFilters,
    resetFilters,
  };
}
