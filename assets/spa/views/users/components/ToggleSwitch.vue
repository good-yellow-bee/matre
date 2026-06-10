<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border border-edge transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
    :class="modelValue ? 'bg-accent' : 'bg-panel-2'"
    @click="emit('update:modelValue', !modelValue)"
  >
    <span
      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-150"
      :class="modelValue ? 'translate-x-[24px]' : 'translate-x-[3px]'"
    ></span>
  </button>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>
