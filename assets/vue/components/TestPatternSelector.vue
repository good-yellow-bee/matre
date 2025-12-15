<template>
  <div class="test-pattern-selector">
    <!-- Text input for Playwright (fallback) -->
    <div v-if="isPlaywrightType" class="input-wrapper">
      <input
        type="text"
        class="form-control"
        :value="selectedValue"
        @input="handleTextInput"
        :placeholder="placeholder"
      />
      <button
        type="button"
        class="btn btn-outline-secondary refresh-btn"
        disabled
        title="Playwright discovery not available"
      >
        &#8635;
      </button>
    </div>

    <!-- Searchable dropdown for MFTF -->
    <div v-else class="input-wrapper">
      <div class="searchable-select" ref="selectContainer">
        <input
          ref="searchInput"
          type="text"
          class="form-control search-input"
          v-model="searchQuery"
          @focus="openDropdown"
          @blur="handleBlur"
          @keydown="handleKeydown"
          :placeholder="inputPlaceholder"
          :disabled="loading"
        />

        <div v-if="selectedValue && !isOpen" class="selected-value" @click="clearAndFocus">
          <span class="value-text">{{ selectedValue }}</span>
          <span class="clear-icon">&times;</span>
        </div>

        <div v-if="isOpen && !loading" class="dropdown-list">
          <div
            v-for="(item, index) in filteredItems"
            :key="item.value"
            class="dropdown-option"
            :class="{ active: index === highlightedIndex }"
            @mousedown.prevent="selectItem(item)"
            @mouseenter="highlightedIndex = index"
          >
            {{ item.label }}
          </div>
          <div v-if="filteredItems.length === 0" class="dropdown-option disabled">
            No matches found
          </div>
        </div>
      </div>

      <button
        type="button"
        class="btn btn-outline-secondary refresh-btn"
        @click="refresh"
        :disabled="refreshing"
        title="Refresh test list from repository"
      >
        <span v-if="refreshing" class="spinner-border spinner-border-sm"></span>
        <span v-else>&#8635;</span>
      </button>
    </div>

    <small v-if="message" class="text-muted d-block mt-1">{{ message }}</small>
    <small v-if="lastUpdated && !isPlaywrightType && cached" class="text-muted d-block mt-1">
      Updated: {{ formatDate(lastUpdated) }}
    </small>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
  typeFieldId: { type: String, required: true },
  patternFieldId: { type: String, required: true },
  apiUrl: { type: String, required: true },
  csrfToken: { type: String, default: '' },
  initialValue: { type: String, default: '' },
});

const items = ref([]);
const loading = ref(false);
const refreshing = ref(false);
const cached = ref(false);
const message = ref('');
const lastUpdated = ref(null);
const currentType = ref('');
const selectedValue = ref(props.initialValue);
const searchQuery = ref('');
const isOpen = ref(false);
const highlightedIndex = ref(0);
const searchInput = ref(null);
const selectContainer = ref(null);

const isPlaywrightType = computed(() => currentType.value.startsWith('playwright_'));

const placeholder = computed(() => {
  return currentType.value === 'playwright_group'
    ? 'Enter tag pattern (e.g., @checkout)'
    : 'Enter test name';
});

const inputPlaceholder = computed(() => {
  if (loading.value) return 'Loading...';
  if (!cached.value) return 'Click refresh to load';
  if (selectedValue.value && !isOpen.value) return '';
  return `Search ${items.value.length} items...`;
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value;
  const query = searchQuery.value.toLowerCase();
  return items.value.filter(item => item.label.toLowerCase().includes(query));
});

const getPatternField = () => document.getElementById(props.patternFieldId);

const syncToHiddenField = (value) => {
  const field = getPatternField();
  if (field) {
    field.value = value;
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }
};

const handleTextInput = (event) => {
  selectedValue.value = event.target.value;
  syncToHiddenField(event.target.value);
};

const openDropdown = () => {
  if (items.value.length > 0) {
    isOpen.value = true;
    searchQuery.value = '';
    highlightedIndex.value = 0;
  }
};

const closeDropdown = () => {
  isOpen.value = false;
  searchQuery.value = '';
};

const handleBlur = () => {
  setTimeout(() => closeDropdown(), 200);
};

const selectItem = (item) => {
  selectedValue.value = item.value;
  syncToHiddenField(item.value);
  closeDropdown();
};

const clearAndFocus = () => {
  selectedValue.value = '';
  syncToHiddenField('');
  nextTick(() => searchInput.value?.focus());
};

const handleKeydown = (event) => {
  if (!isOpen.value) {
    if (event.key === 'ArrowDown' || event.key === 'Enter') {
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
};

const scrollToHighlighted = () => {
  nextTick(() => {
    const container = selectContainer.value;
    const highlighted = container?.querySelector('.dropdown-option.active');
    if (highlighted) {
      highlighted.scrollIntoView({ block: 'nearest' });
    }
  });
};

const fetchItems = async (type) => {
  if (!type || isPlaywrightType.value) {
    items.value = [];
    return;
  }

  loading.value = true;
  message.value = '';
  items.value = [];

  try {
    const response = await fetch(`${props.apiUrl}?type=${encodeURIComponent(type)}`);
    const data = await response.json();

    if (data.success) {
      items.value = data.items || [];
      cached.value = data.cached ?? false;
      message.value = data.message || '';
      lastUpdated.value = data.lastUpdated || null;
    } else {
      message.value = data.error || 'Failed to load';
    }
  } catch (err) {
    message.value = 'Error loading tests';
  } finally {
    loading.value = false;
  }
};

const refresh = async () => {
  if (isPlaywrightType.value) return;

  refreshing.value = true;
  message.value = 'Refreshing...';

  try {
    const response = await fetch(`${props.apiUrl}/refresh`, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': props.csrfToken,
        'Content-Type': 'application/json',
      },
    });
    const data = await response.json();

    if (data.success) {
      message.value = 'Refreshed!';
      lastUpdated.value = data.lastUpdated || null;
      await fetchItems(currentType.value);
    } else {
      message.value = data.error || 'Refresh failed';
    }
  } catch (err) {
    message.value = 'Error refreshing';
  } finally {
    refreshing.value = false;
  }
};

const formatDate = (isoString) => {
  if (!isoString) return '';
  return new Date(isoString).toLocaleString();
};

const watchTypeField = () => {
  const typeField = document.getElementById(props.typeFieldId);
  if (!typeField) return;

  currentType.value = typeField.value;

  typeField.addEventListener('change', (event) => {
    currentType.value = event.target.value;
    selectedValue.value = '';
    syncToHiddenField('');
    fetchItems(event.target.value);
  });
};

onMounted(() => {
  watchTypeField();
  if (currentType.value) {
    fetchItems(currentType.value);
  }
});

watch(searchQuery, () => {
  highlightedIndex.value = 0;
});
</script>

<style>
.test-pattern-selector {
  width: 100%;
}

.input-wrapper {
  display: flex;
  gap: 0;
}

.searchable-select {
  flex: 1;
  position: relative;
}

.search-input {
  width: 100%;
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.selected-value {
  position: absolute;
  top: 50%;
  left: 10px;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  gap: 8px;
  background: #4f46e5;
  color: #fff;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  max-width: calc(100% - 60px);
  z-index: 1;
  box-shadow: 0 1px 3px rgba(79, 70, 229, 0.3);
}

.selected-value:hover {
  background: #4338ca;
}

.value-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.clear-icon {
  font-size: 16px;
  line-height: 1;
  opacity: 0.6;
}

.clear-icon:hover {
  opacity: 1;
}

.dropdown-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 240px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #dee2e6;
  border-top: none;
  border-radius: 0 0 6px 6px;
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
  z-index: 1050;
  padding: 4px 0;
}

.dropdown-option {
  padding: 8px 12px;
  cursor: pointer !important;
  font-size: 14px;
  color: #374151;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.dropdown-option:hover,
.dropdown-option.active {
  background: #e0e7ff;
  color: #1e40af;
}

.dropdown-option.disabled {
  color: #9ca3af;
  cursor: default !important;
  text-align: center;
  font-style: italic;
}

.refresh-btn {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
  border-left: none;
  min-width: 42px;
}

.spinner-border-sm {
  width: 14px;
  height: 14px;
}
</style>
