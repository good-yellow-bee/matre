<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="src"
        class="fixed inset-0 z-[70] flex cursor-zoom-out flex-col items-center justify-center gap-3 bg-black/90 p-6"
        @click="emit('close')"
      >
        <img :src="src" :alt="alt" class="max-h-[90vh] max-w-full rounded object-contain">
        <span v-if="alt" class="max-w-full truncate font-mono text-xs text-white/70">{{ alt }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

defineProps({
  src: { type: String, default: '' },
  alt: { type: String, default: '' },
});

const emit = defineEmits(['close']);

function onKeydown(event) {
  if (event.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>
