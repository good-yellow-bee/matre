import { ref, computed } from 'vue';

export function useTestRunForm(suitesUrl) {
  const suites = ref([]);
  const environments = ref([]);
  const selectedSuiteId = ref('');
  const selectedEnvironmentId = ref('');

  const loadingSuites = ref(false);
  const loadingEnvironments = ref(false);
  const submitting = ref(false);
  const error = ref(null);

  const canSubmit = computed(() => {
    return selectedSuiteId.value && selectedEnvironmentId.value && !submitting.value;
  });

  const environmentState = computed(() => {
    if (!selectedSuiteId.value) {
      return { disabled: true, hint: 'Select a suite first' };
    }
    if (loadingEnvironments.value) {
      return { disabled: true, hint: 'Loading...' };
    }
    if (environments.value.length === 0) {
      return { disabled: true, hint: 'No environments assigned to this suite', error: true };
    }
    return { disabled: false, hint: null };
  });

  const fetchSuites = async () => {
    loadingSuites.value = true;
    error.value = null;

    try {
      const response = await fetch(suitesUrl, {
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Failed to fetch suites');
      suites.value = await response.json();
    } catch (err) {
      error.value = 'Failed to load test suites';
      console.error('Error fetching suites:', err);
    } finally {
      loadingSuites.value = false;
    }
  };

  const fetchEnvironments = async (suiteId) => {
    if (!suiteId) {
      environments.value = [];
      selectedEnvironmentId.value = '';
      return;
    }

    loadingEnvironments.value = true;
    error.value = null;

    try {
      const response = await fetch(`${suitesUrl}/${suiteId}/environments`, {
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Failed to fetch environments');
      environments.value = await response.json();

      // Auto-select if single environment
      if (environments.value.length === 1) {
        selectedEnvironmentId.value = environments.value[0].id;
      } else {
        selectedEnvironmentId.value = '';
      }
    } catch (err) {
      error.value = 'Failed to load environments';
      console.error('Error fetching environments:', err);
    } finally {
      loadingEnvironments.value = false;
    }
  };

  const onSuiteChange = (suiteId) => {
    selectedSuiteId.value = suiteId;
    fetchEnvironments(suiteId);
  };

  return {
    suites,
    environments,
    selectedSuiteId,
    selectedEnvironmentId,
    loadingSuites,
    loadingEnvironments,
    submitting,
    error,
    canSubmit,
    environmentState,
    fetchSuites,
    fetchEnvironments,
    onSuiteChange,
  };
}
