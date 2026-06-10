import { onMounted, onUnmounted, ref } from 'vue';
import { api } from '../api/client';

const runningCount = ref(0);
let timer = null;
let subscribers = 0;

async function poll() {
  try {
    const stats = await api.get('/api/dashboard/stats');
    runningCount.value = stats?.activity?.runningNow ?? 0;
  } catch {
    // Topbar indicator only — stays at last known value on transient errors.
  }
}

export function useRunningActivity() {
  onMounted(() => {
    subscribers += 1;
    if (subscribers === 1) {
      poll();
      timer = setInterval(poll, 30000);
    }
  });

  onUnmounted(() => {
    subscribers -= 1;
    if (subscribers === 0 && timer) {
      clearInterval(timer);
      timer = null;
    }
  });

  return { runningCount };
}
