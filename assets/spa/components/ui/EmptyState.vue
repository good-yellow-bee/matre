<template>
  <div class="flex flex-col items-center gap-2 text-center">
    <div class="grid h-12 w-12 place-items-center rounded-xl border border-edge bg-panel-2">
      <Inbox class="h-5 w-5 text-ink-faint" />
    </div>
    <div class="text-sm font-semibold text-ink">{{ title }}</div>
    <div v-if="message" class="max-w-sm text-xs text-ink-faint">{{ message }}</div>
    <slot />
  </div>
</template>

<script setup>
import { Inbox } from 'lucide-vue-next';

defineProps({
  title: { type: String, default: 'Nothing here yet' },
  message: { type: String, default: '' },
});
</script>
