<template>
  <div class="flex items-start gap-3">
    <button
      type="button"
      role="switch"
      :aria-checked="modelValue"
      :disabled="disabled"
      class="relative mt-0.5 h-6 w-11 shrink-0 cursor-pointer rounded-full border transition-colors duration-200
        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent
        disabled:cursor-not-allowed disabled:opacity-50"
      :class="modelValue ? 'border-accent bg-accent' : 'border-edge-2 bg-panel-2'"
      @click="toggle"
    >
      <span
        class="absolute top-1/2 left-1 h-4 w-4 -translate-y-1/2 rounded-full shadow-sm transition-transform duration-200"
        :class="modelValue ? 'translate-x-5 bg-accent-ink' : 'translate-x-0 bg-ink-faint'"
      ></span>
    </button>
    <div v-if="label || help" class="min-w-0 cursor-pointer select-none" @click="toggle">
      <div class="text-sm font-semibold text-ink">{{ label }}</div>
      <div v-if="help" class="mt-0.5 text-xs text-ink-mute">{{ help }}</div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  help: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function toggle() {
  if (!props.disabled) emit('update:modelValue', !props.modelValue);
}
</script>
