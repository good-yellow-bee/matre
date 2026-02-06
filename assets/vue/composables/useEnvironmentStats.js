import { ref } from 'vue';

export function useEnvironmentStats(apiUrl) {
  const environments = ref([]);
  const loading = ref(false);
  const error = ref(null);

  let currentAbortController = null;
  const DEDUP_WINDOW_MS = 5000;
  let lastFetchTime = 0;

  const fetchStats = async (force = false) => {
    const now = Date.now();

    if (!force && environments.value.length && now - lastFetchTime < DEDUP_WINDOW_MS) {
      return;
    }

    if (currentAbortController) {
      currentAbortController.abort();
    }

    currentAbortController = new AbortController();
    loading.value = true;
    error.value = null;

    try {
      const response = await fetch(apiUrl, {
        signal: currentAbortController.signal,
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      environments.value = data.environments;
      lastFetchTime = Date.now();
    } catch (err) {
      if (err.name === 'AbortError') {
        return;
      }
      console.error('Error fetching environment stats:', err);
      error.value = 'Failed to load environment statistics.';
    } finally {
      loading.value = false;
      currentAbortController = null;
    }
  };

  const refresh = () => {
    fetchStats(true);
  };

  return {
    environments,
    loading,
    error,
    fetchStats,
    refresh,
  };
}
