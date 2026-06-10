<template>
  <span class="badge border" :class="config.classes">
    <span v-if="config.live" class="led led-pulse"></span>
    <span v-else-if="dot" class="led"></span>
    {{ label || status }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, required: true },
  label: { type: String, default: '' },
  dot: { type: Boolean, default: false },
});

const MAP = {
  passed: { classes: 'text-pass border-pass/25 bg-pass/10' },
  completed: { classes: 'text-pass border-pass/25 bg-pass/10' },
  failed: { classes: 'text-fail border-fail/25 bg-fail/10' },
  broken: { classes: 'text-broken border-broken/25 bg-broken/10' },
  skipped: { classes: 'text-skip border-skip/25 bg-skip/10' },
  cancelled: { classes: 'text-skip border-skip/25 bg-skip/10' },
  pending: { classes: 'text-pend border-pend/25 bg-pend/10' },
  running: { classes: 'text-run border-run/25 bg-run/10', live: true },
  preparing: { classes: 'text-run border-run/25 bg-run/10', live: true },
  cloning: { classes: 'text-run border-run/25 bg-run/10', live: true },
  reporting: { classes: 'text-run border-run/25 bg-run/10', live: true },
};

const config = computed(() => MAP[props.status?.toLowerCase()] || { classes: 'text-ink-mute border-edge bg-panel-2' });
</script>
