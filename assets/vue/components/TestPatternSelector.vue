<template>
  <div class="test-pattern-selector">
    <div class="input-group">
      <!-- Text input for Playwright (fallback) -->
      <input
        v-if="isPlaywrightType"
        type="text"
        class="form-control"
        :value="selectedValue"
        @input="handleTextInput"
        :placeholder="placeholder"
      />

      <!-- Searchable dropdown for MFTF -->
      <div v-else class="searchable-select" :class="{ 'is-open': isOpen }">
        <div class="search-input-wrapper">
          <input
            ref="searchInput"
            type="text"
            class="form-control"
            v-model="searchQuery"
            @focus="openDropdown"
            @blur="handleBlur"
            @keydown="handleKeydown"
            :placeholder="inputPlaceholder"
            :disabled="loading"
          />
          <span v-if="selectedValue && !isOpen" class="selected-badge" @click="clearSelection">
            {{ selectedValue }}
            <span class="clear-btn">&times;</span>
          </span>
        </div>

        <div v-show="isOpen && !loading" class="dropdown-list" ref="dropdownList">
          <div
            v-for="(item, index) in filteredItems"
            :key="item.value"
            class="dropdown-item"
            :class="{ 'is-highlighted': index === highlightedIndex }"
            @mousedown.prevent="selectItem(item)"
            @mouseenter="highlightedIndex = index"
          >
            {{ item.label }}
          </div>
          <div v-if="filteredItems.length === 0" class="dropdown-empty">
            No matches found
          </div>
        </div>
      </div>

      <button
        type="button"
        class="btn btn-outline-secondary"
        @click="refresh"
        :disabled="refreshing || isPlaywrightType"
        :title="refreshTitle"
      >
        <span v-if="refreshing" class="spinner-border spinner-border-sm"></span>
        <span v-else>&#8635;</span>
      </button>
    </div>

    <div v-if="message" class="form-text text-muted small">
      {{ message }}
    </div>
    <div v-if="lastUpdated && !isPlaywrightType && cached" class="form-text text-muted small">
      Updated: {{ formatDate(lastUpdated) }}
    </div>
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
const dropdownList = ref(null);

const isPlaywrightType = computed(() => currentType.value.startsWith('playwright_'));

const placeholder = computed(() => {
  if (isPlaywrightType.value) {
    return currentType.value === 'playwright_group'
      ? 'Enter tag pattern (e.g., @checkout)'
      : 'Enter test name';
  }
  return 'Search...';
});

const inputPlaceholder = computed(() => {
  if (loading.value) return 'Loading...';
  if (!cached.value) return 'Click refresh to load';
  if (selectedValue.value && !isOpen.value) return '';
  return `Search ${items.value.length} items...`;
});

const refreshTitle = computed(() => {
  if (isPlaywrightType.value) return 'Playwright discovery not available';
  return 'Refresh test list from repository';
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value;
  const query = searchQuery.value.toLowerCase();
  return items.value.filter(item =>
    item.label.toLowerCase().includes(query)
  );
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
  // Delay to allow click on dropdown item
  setTimeout(() => {
    closeDropdown();
  }, 150);
};

const selectItem = (item) => {
  selectedValue.value = item.value;
  syncToHiddenField(item.value);
  closeDropdown();
};

const clearSelection = () => {
  selectedValue.value = '';
  syncToHiddenField('');
  nextTick(() => {
    searchInput.value?.focus();
  });
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
      highlightedIndex.value = Math.min(
        highlightedIndex.value + 1,
        filteredItems.value.length - 1
      );
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
    const list = dropdownList.value;
    const highlighted = list?.querySelector('.is-highlighted');
    if (highlighted && list) {
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
    console.error('Failed to fetch test discovery:', err);
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
    console.error('Failed to refresh cache:', err);
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

// Reset highlight when search changes
watch(searchQuery, () => {
  highlightedIndex.value = 0;
});
</script>

<style scoped>
.test-pattern-selector {
  width: 100%;
}

.input-group {
  display: flex;
  width: 100%;
}

.searchable-select {
  flex: 1;
  position: relative;
}

.search-input-wrapper {
  position: relative;
}

.search-input-wrapper input {
  width: 100%;
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.selected-badge {
  position: absolute;
  left: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: #e9ecef;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  max-width: calc(100% - 20px);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.selected-badge:hover {
  background: #dee2e6;
}

.clear-btn {
  font-size: 1rem;
  line-height: 1;
  opacity: 0.7;
}

.clear-btn:hover {
  opacity: 1;
}

.dropdown-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  max-height: 250px;
  overflow-y: auto;
  background: white;
  border: 1px solid #ced4da;
  border-top: none;
  border-radius: 0 0 4px 4px;
  z-index: 1000;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
  padding: 8px 12px;
  cursor: pointer;
  font-size: 0.875rem;
}

.dropdown-item:hover,
.dropdown-item.is-highlighted {
  background: #f8f9fa;
}

.dropdown-empty {
  padding: 12px;
  text-align: center;
  color: #6c757d;
  font-size: 0.875rem;
}

.form-text {
  margin-top: 0.25rem;
}

.spinner-border-sm {
  width: 1rem;
  height: 1rem;
}

.btn-outline-secondary {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}
</style>
