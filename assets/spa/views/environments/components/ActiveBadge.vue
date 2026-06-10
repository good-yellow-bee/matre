<template>
  <span class="badge border" :class="active ? 'border-pass/25 bg-pass/10 text-pass' : 'border-skip/25 bg-skip/10 text-skip'">
    <span class="led"></span>
    {{ active ? 'Active' : 'Inactive' }}
  </span>
</template>

<script setup>
defineProps({
  active: { type: Boolean, default: false },
});
</script>
