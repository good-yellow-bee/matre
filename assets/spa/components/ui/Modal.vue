<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
        @mousedown.self="emit('close')"
      >
        <div class="card flex max-h-[88vh] w-full flex-col overflow-hidden" :class="sizes[size]">
          <div class="flex shrink-0 items-center justify-between border-b border-edge px-5 py-3.5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-ink">
              <slot name="title">{{ title }}</slot>
            </h2>
            <button class="cursor-pointer rounded-md p-1 text-ink-faint hover:bg-panel-2 hover:text-ink" @click="emit('close')">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="min-h-0 flex-1 overflow-y-auto p-5">
            <slot />
          </div>
          <div v-if="$slots.footer" class="flex shrink-0 items-center justify-end gap-2 border-t border-edge px-5 py-3.5">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  size: { type: String, default: 'md' },
});

const emit = defineEmits(['close']);

const sizes = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
  full: 'max-w-6xl',
};

function onKeydown(event) {
  if (event.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.18s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
