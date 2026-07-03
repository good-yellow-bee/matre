<template>
  <div v-if="!isHidden">
    <div
      class="flex items-center gap-2 rounded py-1 pr-2 transition-colors hover:bg-panel-2/60"
      :class="rowClass"
      :style="{ paddingLeft: `${depth * 18 + 6}px` }"
    >
      <button
        v-if="hasChildren"
        type="button"
        class="grid h-4 w-4 shrink-0 cursor-pointer place-items-center text-ink-faint hover:text-accent"
        :aria-expanded="expanded"
        @click="expanded = !expanded"
      >
        <ChevronDown v-if="expanded" class="h-3.5 w-3.5" />
        <ChevronRight v-else class="h-3.5 w-3.5" />
      </button>
      <span v-else class="h-4 w-4 shrink-0"></span>

      <component :is="icon" class="h-3.5 w-3.5 shrink-0" :class="iconClass" />

      <span class="min-w-0 flex-1 break-words" :class="nameClass" :title="step.name">{{ step.name }}</span>

      <span v-if="hasChildren" class="rounded border border-edge bg-panel-2 px-1.5 font-mono text-[10px] text-ink-faint">
        {{ visibleChildrenCount }}
      </span>

      <span
        v-if="step.duration !== null && step.duration !== undefined"
        class="w-14 shrink-0 text-right font-mono text-[11px] text-ink-faint"
      >
        {{ formatStepDuration(step.duration) }}
      </span>
    </div>

    <div v-if="hasChildren && expanded" class="ml-[13px] border-l border-dashed border-edge-2">
      <StepNode v-for="(child, index) in step.children" :key="index" :step="child" :depth="depth + 1" />
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { AlertTriangle, CheckCircle2, ChevronDown, ChevronRight, FileText, MinusCircle, XCircle } from 'lucide-vue-next';
import { formatStepDuration } from '../../../utils/format';

const props = defineProps({
  step: { type: Object, required: true },
  depth: { type: Number, default: 0 },
});

const expanded = ref(false);

const name = computed(() => props.step.name?.toLowerCase() || '');

const stepType = computed(() => {
  const n = name.value;
  if (/^(csv step|\d+\.|step \d+|#|\/\/|comment)/i.test(n)) return 'comment';
  if (/action\s*group|actiongroup/i.test(n) || (n.startsWith('[') && n.includes(']') && /action\s*group/i.test(n))) {
    return 'actionGroup';
  }
  return 'action';
});

// Hide noise steps (browser logs, JS errors, attachment refs)
function isNoise(stepName) {
  const n = (stepName || '').toLowerCase();
  if (/^browser\s*(log|error|warning|console)/i.test(n)) return true;
  if (/^(console|javascript)\s*(error|warning|log)/i.test(n)) return true;
  if (/^attachment:/i.test(n)) return true;
  return false;
}

const isHidden = computed(() => isNoise(props.step.name));

const hasChildren = computed(() => (props.step.children || []).some((child) => !isNoise(child.name)));

const visibleChildrenCount = computed(() => (props.step.children || []).filter((child) => !isNoise(child.name)).length);

const icon = computed(() => {
  const status = props.step.status?.toLowerCase();
  if (status === 'failed') return XCircle;
  if (status === 'broken') return AlertTriangle;
  if (status === 'skipped') return MinusCircle;
  if (stepType.value === 'comment') return FileText;
  return CheckCircle2;
});

const iconClass = computed(() => {
  const status = props.step.status?.toLowerCase();
  if (status === 'failed') return 'text-fail';
  if (status === 'broken') return 'text-broken';
  if (status === 'skipped') return 'text-skip';
  if (stepType.value === 'comment') return 'text-pass/70';
  return 'text-pass';
});

const rowClass = computed(() => {
  const status = props.step.status?.toLowerCase();
  if (status === 'failed') return 'bg-fail/10';
  if (status === 'broken') return 'bg-broken/10';
  return '';
});

const nameClass = computed(() => {
  if (stepType.value === 'comment') return 'font-semibold text-ink';
  if (stepType.value === 'actionGroup') return 'font-medium text-ink';
  return 'text-ink-mute';
});
</script>
