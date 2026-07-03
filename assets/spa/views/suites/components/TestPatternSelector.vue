<template>
  <div>
    <!-- Playwright: free-text pattern input (discovery not available) -->
    <div v-if="isPlaywright" class="flex gap-2">
      <input
        class="input font-mono"
        :class="{ 'border-fail': error }"
        :value="modelValue"
        type="text"
        :placeholder="playwrightPlaceholder"
        @input="emit('update:modelValue', $event.target.value)"
      >
      <button type="button" class="btn-ghost shrink-0" disabled title="Playwright discovery not available">
        <RefreshCw class="h-4 w-4" />
      </button>
    </div>

    <!-- MFTF: searchable combobox backed by test discovery -->
    <div v-else class="flex gap-2">
      <div class="relative min-w-0 flex-1">
        <input
          ref="searchInput"
          v-model="searchQuery"
          class="input font-mono"
          :class="{ 'border-fail': error }"
          type="text"
          :placeholder="inputPlaceholder"
          :disabled="loading"
          @focus="openDropdown"
          @blur="handleBlur"
          @keydown="handleKeydown"
        >
        <button
          v-if="modelValue && !isOpen"
          type="button"
          class="absolute top-1/2 left-2 flex max-w-[calc(100%-1rem)] -translate-y-1/2 cursor-pointer items-center gap-1.5 rounded-md bg-accent px-2.5 py-1 font-mono text-xs font-semibold text-accent-ink hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
          title="Clear selection"
          @click="clearAndFocus"
        >
          <span class="truncate">{{ modelValue }}</span>
          <X class="h-3 w-3 shrink-0 opacity-70" />
        </button>
        <div
          v-if="isOpen && !loading"
          class="absolute top-full right-0 left-0 z-20 mt-1 max-h-60 overflow-y-auto rounded-lg border border-edge bg-panel py-1 shadow-lg"
        >
          <button
            v-for="(option, index) in options"
            :key="option.free ? '__free__' : option.value"
            type="button"
            class="block w-full cursor-pointer px-3 py-1.5 text-left font-mono text-[13px] transition-colors"
            :class="index === highlightedIndex ? 'bg-accent-soft text-accent' : 'text-ink hover:bg-panel-2'"
            @mousedown.prevent="selectOption(option)"
            @mouseenter="highlightedIndex = index"
          >
            <template v-if="option.free">Use "{{ option.value }}" as pattern</template>
            <template v-else>{{ option.label }}</template>
          </button>
          <div v-if="options.length === 0" class="px-3 py-1.5 text-center text-xs italic text-ink-faint">
            No matches found
          </div>
        </div>
      </div>
      <button
        type="button"
        class="btn-ghost shrink-0"
        :disabled="refreshing || !type"
        title="Refresh test list from repository"
        @click="refresh"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
      </button>
    </div>

    <p v-if="error" class="field-error">{{ error }}</p>
    <p v-if="message" class="mt-1 text-xs text-ink-faint">{{ message }}</p>
    <p v-if="!isPlaywright && cached && lastUpdated" class="mt-1 text-xs text-ink-faint">
      Updated: {{ formatDateTime(lastUpdated) }}
    </p>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { RefreshCw, X } from 'lucide-vue-next';
import { api } from '../../../api/client';
import { formatDateTime } from '../../../utils/format';

const props = defineProps({
  modelValue: { type: String, default: '' },
  type: { type: String, default: '' },
  error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const items = ref([]);
const loading = ref(false);
const refreshing = ref(false);
const cached = ref(false);
const message = ref('');
const lastUpdated = ref(null);
const searchQuery = ref('');
const isOpen = ref(false);
const highlightedIndex = ref(0);
const searchInput = ref(null);

const isPlaywright = computed(() => props.type.startsWith('playwright_'));

const playwrightPlaceholder = computed(() =>
  props.type === 'playwright_group' ? 'Enter tag pattern (e.g., @checkout)' : 'Enter test name');

const inputPlaceholder = computed(() => {
  if (loading.value) return 'Loading…';
  if (!cached.value) return 'Click refresh to load';
  if (props.modelValue && !isOpen.value) return '';
  return `Search ${items.value.length} items…`;
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value;
  const query = searchQuery.value.toLowerCase();
  return items.value.filter((item) => item.label.toLowerCase().includes(query));
});

const options = computed(() => {
  const list = [...filteredItems.value];
  const query = searchQuery.value.trim();
  if (query && !items.value.some((item) => item.value === query)) {
    list.push({ value: query, label: query, free: true });
  }
  return list;
});

function openDropdown() {
  if (items.value.length > 0) {
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

function selectOption(option) {
  emit('update:modelValue', option.value);
  closeDropdown();
}

function clearAndFocus() {
  emit('update:modelValue', '');
  nextTick(() => searchInput.value?.focus());
}

function handleKeydown(event) {
  if (!isOpen.value) {
    if (event.key === 'ArrowDown' || event.key === 'Enter') {
      event.preventDefault();
      openDropdown();
    }
    return;
  }

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      highlightedIndex.value = Math.min(highlightedIndex.value + 1, options.value.length - 1);
      break;
    case 'ArrowUp':
      event.preventDefault();
      highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
      break;
    case 'Enter':
      event.preventDefault();
      if (options.value[highlightedIndex.value]) selectOption(options.value[highlightedIndex.value]);
      break;
    case 'Escape':
      closeDropdown();
      break;
  }
}

let loadToken = 0;

async function loadItems({ refreshed = false } = {}) {
  // Invalidate any in-flight discovery request so out-of-order responses are discarded
  const token = ++loadToken;

  if (!props.type || isPlaywright.value) {
    items.value = [];
    cached.value = false;
    message.value = '';
    lastUpdated.value = null;
    return;
  }

  loading.value = true;
  try {
    const data = await api.get('/api/test-discovery', { params: { type: props.type } });
    if (token !== loadToken) return;
    items.value = (data.items || []).map((item) => ({
      value: item?.value ?? item,
      label: item?.label ?? item?.value ?? item,
    }));
    cached.value = Boolean(data.cached);
    lastUpdated.value = data.lastUpdated || null;
    message.value = refreshed ? `Loaded ${items.value.length} items` : (data.message || '');
  } catch (e) {
    if (token !== loadToken) return;
    message.value = e.message || 'Error loading test list';
  } finally {
    if (token === loadToken) loading.value = false;
  }
}

async function refresh() {
  if (!props.type || isPlaywright.value) return;

  refreshing.value = true;
  message.value = 'Refreshing…';
  try {
    await api.post('/api/test-discovery/refresh');
    await loadItems({ refreshed: true });
  } catch (e) {
    message.value = e.message || 'Error refreshing test list';
  } finally {
    refreshing.value = false;
  }
}

watch(searchQuery, (value) => {
  highlightedIndex.value = 0;
  if (value) isOpen.value = true;
});

watch(() => props.type, () => {
  closeDropdown();
  loadItems();
}, { immediate: true });
</script>
