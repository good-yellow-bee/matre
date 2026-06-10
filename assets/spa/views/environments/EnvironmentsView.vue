<template>
  <div>
    <PageHeader title="Test Environments" subtitle="Target Magento instances available for test runs">
      <template #actions>
        <RouterLink class="btn-primary" :to="{ name: 'environment-new' }">
          <Plus class="h-4 w-4" />
          Add Environment
        </RouterLink>
      </template>
    </PageHeader>

    <div class="rise" style="--i: 1">
      <DataTable :columns="columns" :rows="environments" :loading="loading">
        <template #cell-name="{ row }">
          <RouterLink class="link font-semibold" :to="{ name: 'environment-detail', params: { id: row.id } }">
            {{ row.name }}
          </RouterLink>
        </template>

        <template #cell-code="{ value }">
          <span class="mono-id">{{ value }}</span>
        </template>

        <template #cell-region="{ value }">
          <span class="badge border border-edge-2 bg-panel-2 text-ink-mute">{{ value }}</span>
        </template>

        <template #cell-baseUrl="{ row }">
          <a
            :href="stripCredentials(row.baseUrl)"
            target="_blank"
            rel="noopener"
            class="link inline-flex max-w-72 items-center gap-1.5 font-mono text-xs"
            :title="stripCredentials(row.baseUrl)"
          >
            <span class="truncate">{{ stripCredentials(row.baseUrl) }}</span>
            <ExternalLink class="h-3 w-3 shrink-0" />
          </a>
        </template>

        <template #cell-isActive="{ value }">
          <ActiveBadge :active="value" />
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1.5">
            <RouterLink class="btn-ghost btn-sm" title="Edit" :to="{ name: 'environment-edit', params: { id: row.id } }">
              <Pencil class="h-3.5 w-3.5" />
            </RouterLink>
            <button class="btn-ghost btn-sm" :title="row.isActive ? 'Deactivate' : 'Activate'" @click="toggleTarget = row">
              <Pause v-if="row.isActive" class="h-3.5 w-3.5" />
              <Play v-else class="h-3.5 w-3.5" />
            </button>
            <button class="btn-danger btn-sm" title="Delete" @click="deleteTarget = row">
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </template>

        <template #empty>
          <EmptyState title="No environments configured" message="Add a target Magento instance to start running tests against it.">
            <RouterLink class="btn-primary btn-sm mt-1" :to="{ name: 'environment-new' }">
              <Plus class="h-3.5 w-3.5" />
              Add one
            </RouterLink>
          </EmptyState>
        </template>

        <template #footer>
          <Pagination :page="1" :pages="1" :total="environments.length" />
        </template>
      </DataTable>
    </div>

    <ConfirmDialog
      :open="!!toggleTarget"
      :title="toggleTarget?.isActive ? 'Deactivate environment?' : 'Activate environment?'"
      :message="toggleTarget?.isActive
        ? `Environment “${toggleTarget?.name}” will no longer be available for test runs.`
        : `Environment “${toggleTarget?.name}” will become available for test runs.`"
      :confirm-label="toggleTarget?.isActive ? 'Deactivate' : 'Activate'"
      :busy="toggleBusy"
      @confirm="confirmToggle"
      @cancel="toggleTarget = null"
    />

    <ConfirmDialog
      :open="!!deleteTarget"
      title="Delete environment?"
      :message="`Delete environment “${deleteTarget?.name}”? Deleting an environment permanently deletes ALL its test runs.`"
      confirm-label="Delete"
      danger
      :busy="deleteBusy"
      @confirm="confirmDelete"
      @cancel="deleteTarget = null"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { ExternalLink, Pause, Pencil, Play, Plus, Trash2 } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import Pagination from '../../components/ui/Pagination.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import ActiveBadge from './components/ActiveBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { stripCredentials } from './display.js';

const toasts = useToastStore();

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'code', label: 'Code' },
  { key: 'region', label: 'Region' },
  { key: 'baseUrl', label: 'Base URL' },
  { key: 'isActive', label: 'Status' },
  { key: 'actions', label: 'Actions', headerClass: 'text-right', cellClass: 'text-right' },
];

const environments = ref([]);
const loading = ref(true);
const toggleTarget = ref(null);
const toggleBusy = ref(false);
const deleteTarget = ref(null);
const deleteBusy = ref(false);

async function load() {
  loading.value = true;
  try {
    environments.value = await api.get('/api/test-environments/list');
  } catch (e) {
    toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

async function confirmToggle() {
  toggleBusy.value = true;
  try {
    const result = await api.post(`/api/test-environments/${toggleTarget.value.id}/toggle-active`);
    toggleTarget.value.isActive = result.isActive;
    toasts.success(result.message);
    toggleTarget.value = null;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    toggleBusy.value = false;
  }
}

async function confirmDelete() {
  deleteBusy.value = true;
  try {
    const result = await api.delete(`/api/test-environments/${deleteTarget.value.id}`);
    environments.value = environments.value.filter((env) => env.id !== deleteTarget.value.id);
    toasts.success(result.message);
    deleteTarget.value = null;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    deleteBusy.value = false;
  }
}

onMounted(load);
</script>
