<template>
  <Teleport to="body">
    <Transition name="toast">
      <div
        v-if="toast.show"
        :class="['toast-notification', `toast-${toast.type}`, { 'toast-shake': toast.type === 'error' }]"
      >
        <i :class="['bi', toastIcon]"></i>
        <span>{{ toast.message }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { useToast } from '../composables/useToast';

const { toast, toastIcon } = useToast();
</script>

<style scoped>
.toast-notification {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 12px 20px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
  z-index: 10000;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.toast-success {
  background: #10b981;
  color: white;
}

.toast-error {
  background: #ef4444;
  color: white;
}

.toast-warning {
  background: #f59e0b;
  color: white;
}

.toast-info {
  background: #3b82f6;
  color: white;
}

.toast-shake {
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
  20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.toast-enter-active {
  animation: slide-in 0.3s ease-out;
}

.toast-leave-active {
  animation: slide-out 0.3s ease-in;
}

@keyframes slide-in {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@keyframes slide-out {
  from {
    transform: translateX(0);
    opacity: 1;
  }
  to {
    transform: translateX(100%);
    opacity: 0;
  }
}
</style>
