<template>
  <span class="badge border" :class="config.classes">
    <span v-if="config.live" class="led led-pulse"></span>
    {{ status || '—' }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, default: null },
});

const MAP = {
  success: { classes: 'text-pass border-pass/25 bg-pass/10' },
  failed: { classes: 'text-fail border-fail/25 bg-fail/10' },
  running: { classes: 'text-run border-run/25 bg-run/10', live: true },
  locked: { classes: 'text-accent border-accent/25 bg-accent-soft' },
};

const config = computed(() => MAP[props.status?.toLowerCase()] || { classes: 'text-ink-mute border-edge bg-panel-2' });
</script>
