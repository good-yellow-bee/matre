<template>
  <div v-if="pages > 1 || total > 0" class="flex items-center justify-between border-t border-edge px-4 py-3">
    <span class="font-mono text-xs text-ink-faint">{{ total }} total</span>
    <div v-if="pages > 1" class="flex items-center gap-1">
      <button class="btn-ghost btn-sm" :disabled="page <= 1" @click="emit('update:page', page - 1)">
        <ChevronLeft class="h-3.5 w-3.5" />
      </button>
      <span class="px-2 font-mono text-xs text-ink-mute">{{ page }} / {{ pages }}</span>
      <button class="btn-ghost btn-sm" :disabled="page >= pages" @click="emit('update:page', page + 1)">
        <ChevronRight class="h-3.5 w-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineProps({
  page: { type: Number, default: 1 },
  pages: { type: Number, default: 1 },
  total: { type: Number, default: 0 },
});

const emit = defineEmits(['update:page']);
</script>
