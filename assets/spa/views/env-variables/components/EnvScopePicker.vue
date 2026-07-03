<template>
  <button ref="trigger" type="button" class="input flex cursor-pointer items-center justify-between gap-2 text-left" @click="toggle">
    <span class="flex min-w-0 items-center gap-1.5">
      <Globe v-if="isGlobal" class="h-3.5 w-3.5 shrink-0 text-accent" />
      <span class="truncate text-[13px]" :class="isGlobal ? 'text-accent' : 'font-mono'">{{ summary }}</span>
    </span>
    <ChevronDown class="h-3.5 w-3.5 shrink-0 text-ink-faint" />
  </button>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" @mousedown.self="open = false">
      <div ref="panel" class="card absolute max-h-72 w-60 overflow-y-auto p-1.5 shadow-xl" :style="panelStyle">
        <button
          type="button"
          class="flex w-full cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-sm hover:bg-panel-2"
          :class="isGlobal ? 'text-accent' : 'text-ink'"
          @click="setGlobal"
        >
          <Globe class="h-3.5 w-3.5" />
          Global (all environments)
          <Check v-if="isGlobal" class="ml-auto h-3.5 w-3.5" />
        </button>
        <div class="my-1 border-t border-edge"></div>
        <p v-if="!environments.length" class="px-2.5 py-1.5 text-xs text-ink-faint">No environment-scoped variables yet.</p>
        <label
          v-for="env in environments"
          :key="env"
          class="flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-1.5 hover:bg-panel-2"
        >
          <input
            type="checkbox"
            class="h-3.5 w-3.5 accent-(--m-accent)"
            :checked="selected.includes(env)"
            @change="toggleEnv(env)"
          >
          <span class="font-mono text-[13px] text-ink">{{ env }}</span>
        </label>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Check, ChevronDown, Globe } from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: Array, default: null },
  environments: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const trigger = ref(null);
const panel = ref(null);
const panelStyle = ref({});

const selected = computed(() => props.modelValue || []);
const isGlobal = computed(() => !selected.value.length);
const summary = computed(() => {
  if (isGlobal.value) return 'Global';
  return selected.value.length === 1 ? selected.value[0] : `${selected.value.length} envs`;
});

function toggle() {
  if (open.value) {
    open.value = false;
    return;
  }
  const rect = trigger.value.getBoundingClientRect();
  const width = 240;
  const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
  const style = { left: `${left}px` };
  if (window.innerHeight - rect.bottom < 320) style.bottom = `${window.innerHeight - rect.top + 4}px`;
  else style.top = `${rect.bottom + 4}px`;
  panelStyle.value = style;
  open.value = true;
}

function setGlobal() {
  emit('update:modelValue', null);
  open.value = false;
}

function toggleEnv(env) {
  const next = selected.value.includes(env) ? selected.value.filter((e) => e !== env) : [...selected.value, env];
  emit('update:modelValue', next.length ? next : null);
}

function onScroll(event) {
  if (panel.value && event.target instanceof Node && panel.value.contains(event.target)) return;
  open.value = false;
}

watch(open, (value) => {
  if (value) window.addEventListener('scroll', onScroll, true);
  else window.removeEventListener('scroll', onScroll, true);
});

onUnmounted(() => window.removeEventListener('scroll', onScroll, true));
</script>
