<template>
  <div class="flex items-center gap-3 rounded-xl border border-fail/30 bg-fail/10 p-4 text-sm text-fail">
    <AlertCircle class="h-4 w-4 shrink-0" />
    <slot>{{ message }}</slot>
    <button v-if="showRetry" class="btn-ghost btn-sm ml-auto" @click="emit('retry')">Retry</button>
  </div>
</template>

<script setup>
import { AlertCircle } from 'lucide-vue-next';

defineProps({
  message: { type: String, default: '' },
  showRetry: { type: Boolean, default: true },
});

const emit = defineEmits(['retry']);
</script>
