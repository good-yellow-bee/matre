import { ref, computed } from 'vue';

export function useEnvVariableGrid(apiUrl, csrfToken) {
  const variables = ref([]);
  const environments = ref([]);
  const selectedEnvironment = ref('all');
  const loading = ref(false);
  const saving = ref(false);
  const error = ref(null);
  const searchQuery = ref('');
  const sortField = ref('name');
  const sortOrder = ref('asc');
  const hasChanges = ref(false);

  // Filtered and sorted variables
  const filteredVariables = computed(() => {
    let result = [...variables.value];

    // Filter by environment
    if (selectedEnvironment.value !== 'all') {
      if (selectedEnvironment.value === 'global') {
        result = result.filter(v => !v.environments || v.environments.length === 0);
      } else {
        result = result.filter(v =>
          v.environments && v.environments.includes(selectedEnvironment.value)
        );
      }
    }

    // Filter by search query
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase();
      result = result.filter(v =>
        v.name.toLowerCase().includes(query) ||
        (v.value && v.value.toLowerCase().includes(query)) ||
        (v.environments && v.environments.some(e => e.toLowerCase().includes(query))) ||
        (v.usedInTests && v.usedInTests.toLowerCase().includes(query)) ||
        (v.description && v.description.toLowerCase().includes(query))
      );
    }

    // Sort
    result.sort((a, b) => {
      let aVal, bVal;
      if (sortField.value === 'environments') {
        // Sort by first environment or empty string
        aVal = (a.environments && a.environments[0]) || '';
        bVal = (b.environments && b.environments[0]) || '';
      } else {
        aVal = (a[sortField.value] || '').toString().toLowerCase();
        bVal = (b[sortField.value] || '').toString().toLowerCase();
      }
      const cmp = aVal.localeCompare(bVal);
      return sortOrder.value === 'asc' ? cmp : -cmp;
    });

    return result;
  });

  const fetchVariables = async () => {
    loading.value = true;
    error.value = null;

    try {
      const params = new URLSearchParams({
        sort: sortField.value,
        order: sortOrder.value,
      });

      const response = await fetch(`${apiUrl}/list?${params}`);
      if (!response.ok) throw new Error('Failed to fetch variables');

      const data = await response.json();
      variables.value = data.data.map(v => ({ ...v, _dirty: false }));
      environments.value = data.environments || [];
      hasChanges.value = false;
    } catch (err) {
      error.value = err.message;
      console.error('Error fetching variables:', err);
    } finally {
      loading.value = false;
    }
  };

  const sort = (field) => {
    if (sortField.value === field) {
      sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
      sortField.value = field;
      sortOrder.value = 'asc';
    }
  };

  const addVariable = (environment = null) => {
    variables.value.unshift({
      id: null,
      name: '',
      value: '',
      environments: environment ? [environment] : null,
      usedInTests: '',
      description: '',
      _dirty: true,
      _isNew: true,
    });
    hasChanges.value = true;
  };

  const updateVariable = (index, field, value) => {
    const realIndex = variables.value.findIndex(v => v === filteredVariables.value[index]);
    if (realIndex !== -1) {
      variables.value[realIndex][field] = value;
      variables.value[realIndex]._dirty = true;
      hasChanges.value = true;
    }
  };

  const removeVariable = (index) => {
    const variable = filteredVariables.value[index];
    const realIndex = variables.value.indexOf(variable);
    if (realIndex !== -1) {
      if (variable._isNew) {
        // Just remove from array if it's a new unsaved variable
        variables.value.splice(realIndex, 1);
      } else {
        // Mark for deletion
        variables.value[realIndex]._deleted = true;
      }
      hasChanges.value = true;
    }
  };

  const saveAll = async () => {
    saving.value = true;
    error.value = null;

    try {
      // Delete marked variables
      const toDelete = variables.value.filter(v => v._deleted && v.id);
      for (const v of toDelete) {
        await fetch(`${apiUrl}/${v.id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-Token': csrfToken,
          },
        });
      }

      // Save new and modified variables
      const toSave = variables.value.filter(v => v._dirty && !v._deleted);
      if (toSave.length > 0) {
        const response = await fetch(`${apiUrl}/bulk`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
          },
          body: JSON.stringify({
            variables: toSave.map(v => ({
              id: v.id,
              name: v.name,
              value: v.value,
              environments: v.environments && v.environments.length > 0 ? v.environments : null,
              usedInTests: v.usedInTests || null,
              description: v.description || null,
            })),
          }),
        });

        if (!response.ok) {
          const data = await response.json();
          throw new Error(data.errors?.join('\n') || 'Failed to save variables');
        }
      }

      // Refresh data
      await fetchVariables();
      return { success: true, message: 'Variables saved successfully' };
    } catch (err) {
      error.value = err.message;
      return { success: false, message: err.message };
    } finally {
      saving.value = false;
    }
  };

  const importFromEnv = async (content) => {
    try {
      const response = await fetch(`${apiUrl}/import`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({ content }),
      });

      if (!response.ok) throw new Error('Failed to parse .env content');

      const data = await response.json();

      // Add parsed variables to the list
      for (const v of data.variables) {
        // Check if variable already exists
        const existing = variables.value.find(e => e.name === v.name);
        if (existing) {
          existing.value = v.value;
          existing._dirty = true;
        } else {
          variables.value.push({
            id: null,
            name: v.name,
            value: v.value,
            environments: null, // Global by default
            usedInTests: '',
            description: '',
            _dirty: true,
            _isNew: true,
          });
        }
      }

      hasChanges.value = true;
      return { success: true, count: data.count };
    } catch (err) {
      return { success: false, message: err.message };
    }
  };

  const discardChanges = () => {
    fetchVariables();
  };

  // Toggle environment in environments array
  const toggleEnvironment = (index, env) => {
    const variable = filteredVariables.value[index];
    if (!variable) return;

    const realIndex = variables.value.indexOf(variable);
    if (realIndex === -1) return;

    let envs = variable.environments ? [...variable.environments] : [];

    if (envs.includes(env)) {
      envs = envs.filter(e => e !== env);
    } else {
      envs.push(env);
    }

    variables.value[realIndex].environments = envs.length > 0 ? envs : null;
    variables.value[realIndex]._dirty = true;
    hasChanges.value = true;
  };

  // Set as global (clear all environments)
  const setGlobal = (index) => {
    const variable = filteredVariables.value[index];
    if (!variable) return;

    const realIndex = variables.value.indexOf(variable);
    if (realIndex === -1) return;

    variables.value[realIndex].environments = null;
    variables.value[realIndex]._dirty = true;
    hasChanges.value = true;
  };

  return {
    variables,
    filteredVariables,
    environments,
    selectedEnvironment,
    loading,
    saving,
    error,
    searchQuery,
    sortField,
    sortOrder,
    hasChanges,
    fetchVariables,
    sort,
    addVariable,
    updateVariable,
    removeVariable,
    saveAll,
    importFromEnv,
    discardChanges,
    toggleEnvironment,
    setGlobal,
  };
}
