<template>
  <div class="test-id-selector">
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

      <!-- Hidden input for form submission -->
      <input type="hidden" :name="fieldName" :value="selectedValue" :required="required" />

      <div v-if="selectedValue && !isOpen" class="selected-value" @click="clearAndFocus">
        <span class="value-text">{{ selectedValue }}</span>
        <span class="clear-icon">&times;</span>
      </div>

      <div v-if="isOpen && !loading" class="dropdown-list">
        <div
          v-for="(item, index) in filteredItems"
          :key="item"
          class="dropdown-option"
          :class="{ active: index === highlightedIndex }"
          @mousedown.prevent="selectItem(item)"
          @mouseenter="highlightedIndex = index"
        >
          {{ item }}
        </div>
        <div v-if="filteredItems.length === 0 && searchQuery" class="dropdown-option disabled">
          No matches found
        </div>
        <div v-if="filteredItems.length === 0 && !searchQuery && items.length === 0" class="dropdown-option disabled">
          No test results available
        </div>
      </div>
    </div>

    <small v-if="loading" class="text-muted d-block mt-1">Loading test IDs...</small>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  fieldName: { type: String, default: 'testId' },
  initialValue: { type: String, default: '' },
  required: { type: Boolean, default: false },
});

const items = ref([]);
const loading = ref(false);
const selectedValue = ref(props.initialValue);
const searchQuery = ref('');
const isOpen = ref(false);
const highlightedIndex = ref(0);
const searchInput = ref(null);
const selectContainer = ref(null);

const inputPlaceholder = computed(() => {
  if (loading.value) return 'Loading...';
  if (items.value.length === 0) return 'No tests available';
  if (selectedValue.value && !isOpen.value) return '';
  return `Search ${items.value.length} tests...`;
});

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value.slice(0, 100);
  const query = searchQuery.value.toLowerCase();
  return items.value.filter(item => item.toLowerCase().includes(query)).slice(0, 100);
});

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
  selectedValue.value = item;
  closeDropdown();
};

const clearAndFocus = () => {
  selectedValue.value = '';
  nextTick(() => searchInput.value?.focus());
};

const handleKeydown = (event) => {
  if (!isOpen.value) {
    if (event.key === 'ArrowDown' || event.key === 'Enter') {
      if (event.key === 'Enter' && selectedValue.value) {
        return; // Allow form submit
      }
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

const fetchItems = async () => {
  loading.value = true;

  try {
    const response = await fetch(props.apiUrl);
    const data = await response.json();
    items.value = data.data || [];
  } catch (err) {
    console.error('Error loading test IDs:', err);
    items.value = [];
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchItems();
});

watch(searchQuery, () => {
  highlightedIndex.value = 0;
});
</script>

<style>
.test-id-selector {
  width: 100%;
}

.test-id-selector .searchable-select {
  flex: 1;
  position: relative;
}

.test-id-selector .search-input {
  width: 100%;
}

.test-id-selector .selected-value {
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
  max-width: calc(100% - 20px);
  z-index: 1;
  box-shadow: 0 1px 3px rgba(79, 70, 229, 0.3);
}

.test-id-selector .selected-value:hover {
  background: #4338ca;
}

.test-id-selector .value-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.test-id-selector .clear-icon {
  font-size: 16px;
  line-height: 1;
  opacity: 0.6;
}

.test-id-selector .clear-icon:hover {
  opacity: 1;
}

.test-id-selector .dropdown-list {
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

.test-id-selector .dropdown-option {
  padding: 8px 12px;
  cursor: pointer !important;
  font-size: 14px;
  color: #374151;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.test-id-selector .dropdown-option:hover,
.test-id-selector .dropdown-option.active {
  background: #e0e7ff;
  color: #1e40af;
}

.test-id-selector .dropdown-option.disabled {
  color: #9ca3af;
  cursor: default !important;
  text-align: center;
  font-style: italic;
}
</style>
