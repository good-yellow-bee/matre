<template>
  <Teleport to="body">
    <div role="status" aria-live="polite" class="pointer-events-none fixed bottom-5 right-5 z-[100] flex w-80 flex-col gap-2">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts.items"
          :key="toast.id"
          class="pointer-events-auto flex items-start gap-2.5 rounded-lg border p-3 text-sm shadow-lg backdrop-blur"
          :class="styles[toast.type] || styles.info"
        >
          <CheckCircle2 v-if="toast.type === 'success'" class="mt-0.5 h-4 w-4 shrink-0" />
          <AlertCircle v-else-if="toast.type === 'error'" class="mt-0.5 h-4 w-4 shrink-0" />
          <Info v-else class="mt-0.5 h-4 w-4 shrink-0" />
          <span class="flex-1">{{ toast.message }}</span>
          <button aria-label="Dismiss" class="cursor-pointer opacity-60 hover:opacity-100" @click="toasts.dismiss(toast.id)">
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { AlertCircle, CheckCircle2, Info, X } from 'lucide-vue-next';
import { useToastStore } from '../../stores/toasts';

const toasts = useToastStore();

const styles = {
  success: 'border-pass/30 bg-panel/95 text-pass',
  error: 'border-fail/30 bg-panel/95 text-fail',
  info: 'border-accent/30 bg-panel/95 text-ink',
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
