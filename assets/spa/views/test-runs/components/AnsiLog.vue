<template>
  <pre
    ref="el"
    class="ansi-log overflow-y-auto rounded-lg p-4 font-mono text-xs leading-relaxed"
    :style="{ maxHeight }"
    v-html="html"
  ></pre>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { ansiToSafeHtml } from '../utils/ansi';

const props = defineProps({
  text: { type: String, default: '' },
  maxHeight: { type: String, default: '400px' },
});

const el = ref(null);
const html = computed(() => ansiToSafeHtml(props.text));

// Auto-scroll to bottom on append unless the user scrolled up
watch(html, async () => {
  const node = el.value;
  if (!node) return;
  const wasAtBottom = node.scrollHeight - node.scrollTop - node.clientHeight < 48;
  await nextTick();
  if (wasAtBottom) node.scrollTop = node.scrollHeight;
});
</script>

<style scoped>
/* Always dark, in both themes */
.ansi-log {
  background: #0a0e16;
  color: #d3deee;
  white-space: pre-wrap;
  word-break: break-word;
  border: 1px solid #1c2638;
}

.ansi-log :deep(.ansi-bold) { font-weight: 700; }
.ansi-log :deep(.ansi-black) { color: #555; }
.ansi-log :deep(.ansi-red) { color: #f55; }
.ansi-log :deep(.ansi-green) { color: #5f5; }
.ansi-log :deep(.ansi-yellow) { color: #ff5; }
.ansi-log :deep(.ansi-blue) { color: #7b9aff; }
.ansi-log :deep(.ansi-magenta) { color: #f5f; }
.ansi-log :deep(.ansi-cyan) { color: #5ff; }
.ansi-log :deep(.ansi-white) { color: #fff; }
.ansi-log :deep(.ansi-bright-black) { color: #888; }
.ansi-log :deep(.ansi-bright-red) { color: #f88; }
.ansi-log :deep(.ansi-bright-green) { color: #8f8; }
.ansi-log :deep(.ansi-bright-yellow) { color: #ff8; }
.ansi-log :deep(.ansi-bright-blue) { color: #9ab4ff; }
.ansi-log :deep(.ansi-bright-magenta) { color: #f8f; }
.ansi-log :deep(.ansi-bright-cyan) { color: #8ff; }
.ansi-log :deep(.ansi-bright-white) { color: #fff; }
</style>
