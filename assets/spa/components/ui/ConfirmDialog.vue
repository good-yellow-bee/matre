<template>
  <Modal :open="open" :title="title" size="sm" @close="emit('cancel')">
    <p class="text-sm text-ink-mute">{{ message }}</p>
    <template #footer>
      <button class="btn-ghost btn-sm" @click="emit('cancel')">Cancel</button>
      <button :class="danger ? 'btn-danger btn-sm' : 'btn-primary btn-sm'" :disabled="busy" @click="emit('confirm')">
        <Loader2 v-if="busy" class="h-3.5 w-3.5 animate-spin" />
        {{ confirmLabel }}
      </button>
    </template>
  </Modal>
</template>

<script setup>
import { Loader2 } from 'lucide-vue-next';
import Modal from './Modal.vue';

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Are you sure?' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Confirm' },
  danger: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>
