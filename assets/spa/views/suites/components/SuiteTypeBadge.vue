<template>
  <span
    class="badge border"
    :class="isMftf ? 'border-accent/30 bg-accent-soft text-accent' : 'border-broken/30 bg-broken/10 text-broken'"
  >
    {{ displayLabel }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  type: { type: String, required: true },
  label: { type: String, default: '' },
});

const LABELS = {
  mftf_group: 'MFTF Group',
  mftf_test: 'MFTF Test',
  playwright_group: 'Playwright Group',
  playwright_test: 'Playwright Test',
};

const isMftf = computed(() => props.type.startsWith('mftf'));
const displayLabel = computed(() => props.label || LABELS[props.type] || props.type);
</script>
