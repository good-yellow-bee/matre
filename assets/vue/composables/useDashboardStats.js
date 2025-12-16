import { ref } from 'vue';

export function useDashboardStats(apiUrl) {
  const stats = ref(null);
  const loading = ref(false);
  const error = ref(null);

  // Request deduplication
  let currentAbortController = null;
  const DEDUP_WINDOW_MS = 5000; // 5s cache for stats
  let lastFetchTime = 0;

  const fetchStats = async (force = false) => {
    const now = Date.now();

    // Skip if fetched recently (unless forced)
    if (!force && stats.value && now - lastFetchTime < DEDUP_WINDOW_MS) {
      return;
    }

    // Abort previous in-flight request
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
      stats.value = data;
      lastFetchTime = Date.now();
    } catch (err) {
      if (err.name === 'AbortError') {
        return;
      }
      error.value = 'Failed to load statistics. Please try again later.';
    } finally {
      loading.value = false;
      currentAbortController = null;
    }
  };

  const refresh = () => {
    fetchStats(true); // Force refresh bypasses cache
  };

  return {
    stats,
    loading,
    error,
    fetchStats,
    refresh,
  };
}
