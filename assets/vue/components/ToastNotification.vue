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

<!-- Toast styles are in admin.css -->
