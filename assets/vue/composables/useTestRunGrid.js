import { ref } from 'vue';

export function useTestRunGrid(apiUrl, csrfToken) {
  const runs = ref([]);
  const meta = ref({
    page: 1,
    limit: 20,
    total: 0,
    pages: 0,
  });
  const loading = ref(false);
  const error = ref(null);

  // Request deduplication state
  let currentAbortController = null;
  let lastRequestKey = null;
  const DEDUP_WINDOW_MS = 2000; // Ignore duplicate requests within 2s
  let lastRequestTime = 0;

  const fetchRuns = async (filters = {}, page = 1) => {
    // Build request key for deduplication
    const requestKey = JSON.stringify({ filters, page });
    const now = Date.now();

    // Skip if same request within dedup window
    if (requestKey === lastRequestKey && now - lastRequestTime < DEDUP_WINDOW_MS) {
      return;
    }

    // Abort previous in-flight request
    if (currentAbortController) {
      currentAbortController.abort();
    }

    currentAbortController = new AbortController();
    lastRequestKey = requestKey;
    lastRequestTime = now;

    loading.value = true;
    error.value = null;

    try {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: meta.value.limit.toString(),
      });

      if (filters.status) {
        params.append('status', filters.status);
      }
      if (filters.type) {
        params.append('type', filters.type);
      }
      if (filters.environment) {
        params.append('environment', filters.environment);
      }

      const response = await fetch(`${apiUrl}?${params}`, {
        headers: {
          'Accept': 'application/json',
        },
        signal: currentAbortController.signal,
      });

      if (!response.ok) {
        throw new Error('Failed to fetch test runs');
      }

      const data = await response.json();
      runs.value = data.data;
      meta.value = data.meta;
    } catch (err) {
      // Ignore abort errors (expected when cancelling)
      if (err.name === 'AbortError') {
        return;
      }
      error.value = err.message;
    } finally {
      loading.value = false;
      currentAbortController = null;
    }
  };

  const cancelRun = async (runId) => {
    const response = await fetch(`${apiUrl}/${runId}/cancel`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.error || 'Failed to cancel run');
    }

    return response.json();
  };

  const retryRun = async (runId) => {
    const response = await fetch(`${apiUrl}/${runId}/retry`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.error || 'Failed to retry run');
    }

    const data = await response.json();
    return data.run;
  };

  const goToPage = (page) => {
    if (page >= 1 && page <= meta.value.pages) {
      fetchRuns({}, page);
    }
  };

  return {
    runs,
    meta,
    loading,
    error,
    fetchRuns,
    cancelRun,
    retryRun,
    goToPage,
  };
}
