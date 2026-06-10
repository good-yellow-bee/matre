<template>
  <span v-if="!sensitive" class="break-all">{{ value }}</span>
  <span v-else class="inline-flex max-w-full items-center gap-1.5">
    <span v-if="revealed" class="break-all">{{ value }}</span>
    <span v-else class="tracking-widest text-ink-faint" title="Sensitive value hidden">••••••••</span>
    <button
      type="button"
      class="shrink-0 cursor-pointer rounded p-0.5 text-ink-faint hover:bg-panel-2 hover:text-ink"
      :title="revealed ? 'Hide value' : 'Reveal value'"
      @click="revealed = !revealed"
    >
      <EyeOff v-if="revealed" class="h-3.5 w-3.5" />
      <Eye v-else class="h-3.5 w-3.5" />
    </button>
  </span>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { isSensitiveName } from '../../../utils/sensitive';

const props = defineProps({
  name: { type: String, required: true },
  value: { type: String, default: '' },
});

const revealed = ref(false);
const sensitive = computed(() => isSensitiveName(props.name));
</script>
