<template>
  <span
    class="badge border"
    :class="isMftf ? 'border-accent/30 bg-accent-soft text-accent' : 'border-broken/30 bg-broken/10 text-broken'"
  >
    {{ label || type }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  type: { type: String, required: true },
  label: { type: String, default: '' },
});

const isMftf = computed(() => props.type.startsWith('mftf'));
</script>
