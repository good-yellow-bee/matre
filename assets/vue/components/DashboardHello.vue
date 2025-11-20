<template>
  <div class="card" aria-live="polite">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="badge bg-primary text-uppercase fw-semibold">Vue</span>
      <span class="text-muted small">Live widget</span>
    </div>
    <div class="card-body">
      <p class="mb-1 fw-semibold">Hi, {{ username }}!</p>
      <p class="text-muted mb-3">
        This widget is rendered by Vue via Vite. Use it as a pattern for adding more interactive islands.
      </p>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" @click="refresh">
          Refresh status
        </button>
        <span class="text-muted small align-self-center">Last updated {{ lastUpdatedLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  username: {
    type: String,
    default: 'Admin',
  },
});

const lastUpdatedLabel = ref('just now');

const refresh = () => {
  const now = new Date();
  const formatted = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  lastUpdatedLabel.value = `at ${formatted}`;
};
</script>
