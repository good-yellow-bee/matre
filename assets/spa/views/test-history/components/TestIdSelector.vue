<template>
  <div ref="container" class="relative">
    <input
      ref="input"
      v-model="searchQuery"
      type="text"
      class="input"
      :class="{ 'text-transparent': model && !isOpen }"
      :placeholder="placeholder"
      :disabled="loading"
      aria-label="Search test IDs"
      autocomplete="off"
      spellcheck="false"
      @focus="openDropdown"
      @blur="handleBlur"
      @keydown="handleKeydown"
    >

    <button
      v-if="model && !isOpen"
      type="button"
      class="absolute top-1/2 left-2 flex max-w-[calc(100%-1rem)] -translate-y-1/2 cursor-pointer items-center gap-1.5 rounded-md bg-accent px-2.5 py-1 font-mono text-xs font-semibold text-accent-ink hover:brightness-110"
      @click="clearAndFocus"
    >
      <span class="truncate">{{ model }}</span>
      <X class="h-3 w-3 shrink-0 opacity-70" />
    </button>

    <div
      v-if="isOpen && !loading"
      class="absolute top-full right-0 left-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-lg border border-edge bg-panel py-1 shadow-lg"
    >
      <button
        v-for="(item, index) in filteredItems"
        :key="item"
        type="button"
        class="block w-full cursor-pointer px-3 py-1.5 text-left font-mono text-sm transition-colors"
        :class="index === highlightedIndex ? 'bg-accent-soft text-accent' : 'text-ink hover:bg-panel-2'"
        @mousedown.prevent="selectItem(item)"
        @mouseenter="highlightedIndex = index"
      >
        {{ item }}
      </button>
      <div v-if="filteredItems.length === 0 && searchQuery" class="px-3 py-2 text-center text-xs italic text-ink-faint">
        No matches found
      </div>
      <div v-if="filteredItems.length === 0 && !searchQuery && items.length === 0" class="px-3 py-2 text-center text-xs italic text-ink-faint">
        No test results available
      </div>
    </div>

    <p v-if="loading" class="mt-1 text-xs text-ink-faint">Loading test IDs…</p>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });

const searchQuery = ref('');
const isOpen = ref(false);
const highlightedIndex = ref(0);
const input = ref(null);
const container = ref(null);

const placeholder = computed(() => {
  if (props.loading) return 'Loading…';
  if (props.items.length === 0) return 'No tests available';
  if (model.value && !isOpen.value) return '';
  return `Search ${props.items.length} tests…`;
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return props.items.slice(0, 100);
  const query = searchQuery.value.toLowerCase();
  return props.items.filter((item) => item.toLowerCase().includes(query)).slice(0, 100);
});

function openDropdown() {
  if (props.items.length > 0) {
    isOpen.value = true;
    searchQuery.value = '';
    highlightedIndex.value = 0;
  }
}

function closeDropdown() {
  isOpen.value = false;
  searchQuery.value = '';
}

function handleBlur() {
  setTimeout(() => closeDropdown(), 200);
}

function selectItem(item) {
  model.value = item;
  closeDropdown();
}

function clearAndFocus() {
  model.value = '';
  nextTick(() => input.value?.focus());
}

function handleKeydown(event) {
  if (!isOpen.value) {
    if (event.key === 'ArrowDown' || event.key === 'Enter') {
      if (event.key === 'Enter' && model.value) return; // allow form submit
      openDropdown();
      event.preventDefault();
    }
    return;
  }

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredItems.value.length - 1);
      scrollToHighlighted();
      break;
    case 'ArrowUp':
      event.preventDefault();
      highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
      scrollToHighlighted();
      break;
    case 'Enter':
      event.preventDefault();
      if (filteredItems.value[highlightedIndex.value]) {
        selectItem(filteredItems.value[highlightedIndex.value]);
      }
      break;
    case 'Escape':
      closeDropdown();
      break;
  }
}

function scrollToHighlighted() {
  nextTick(() => {
    container.value?.querySelector('.bg-accent-soft')?.scrollIntoView({ block: 'nearest' });
  });
}

watch(searchQuery, () => {
  highlightedIndex.value = 0;
});
</script>
