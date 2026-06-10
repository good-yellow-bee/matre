<template>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="border-b border-edge bg-panel-2/60">
          <tr>
            <th v-for="column in columns" :key="column.key" class="th-base" :class="column.headerClass">
              <button
                v-if="column.sortable"
                class="inline-flex cursor-pointer items-center gap-1 uppercase tracking-widest hover:text-ink"
                @click="toggleSort(column.key)"
              >
                {{ column.label }}
                <ArrowUp v-if="sort === column.key && order === 'asc'" class="h-3 w-3" />
                <ArrowDown v-else-if="sort === column.key && order === 'desc'" class="h-3 w-3" />
                <ArrowUpDown v-else class="h-3 w-3 opacity-40" />
              </button>
              <template v-else>{{ column.label }}</template>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-edge">
          <template v-if="loading">
            <tr v-for="i in skeletonRows" :key="`skeleton-${i}`">
              <td v-for="column in columns" :key="column.key" class="td-base">
                <div class="skeleton h-4" :style="{ width: `${45 + ((i * 17 + column.key.length * 11) % 45)}%` }"></div>
              </td>
            </tr>
          </template>
          <template v-else-if="rows.length">
            <tr
              v-for="(row, index) in rows"
              :key="rowKey ? row[rowKey] : index"
              class="transition-colors hover:bg-panel-2/50"
              :class="{ 'cursor-pointer': clickable }"
              @click="clickable && emit('row-click', row)"
            >
              <td v-for="column in columns" :key="column.key" class="td-base" :class="column.cellClass">
                <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                  {{ row[column.key] }}
                </slot>
              </td>
            </tr>
          </template>
          <tr v-else>
            <td :colspan="columns.length" class="px-4 py-14">
              <slot name="empty">
                <EmptyState :title="emptyTitle" :message="emptyMessage" />
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <slot name="footer" />
  </div>
</template>

<script setup>
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import EmptyState from './EmptyState.vue';

const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  loading: { type: Boolean, default: false },
  skeletonRows: { type: Number, default: 6 },
  clickable: { type: Boolean, default: false },
  sort: { type: String, default: '' },
  order: { type: String, default: 'asc' },
  emptyTitle: { type: String, default: 'Nothing here yet' },
  emptyMessage: { type: String, default: '' },
});

const emit = defineEmits(['row-click', 'sort']);

function toggleSort(key) {
  const order = props.sort === key && props.order === 'asc' ? 'desc' : 'asc';
  emit('sort', { sort: key, order });
}
</script>
