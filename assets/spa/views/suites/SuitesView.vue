<template>
  <div>
    <PageHeader title="Test Suites" subtitle="Reusable test collections with scheduling">
      <template #actions>
        <RouterLink :to="{ name: 'suite-new' }" class="btn-primary">
          <Plus class="h-4 w-4" />
          Add Test Suite
        </RouterLink>
      </template>
    </PageHeader>

    <div class="rise" style="--i: 1">
      <DataTable :columns="columns" :rows="suites" :loading="loading">
        <template #cell-name="{ row }">
          <RouterLink :to="{ name: 'suite-detail', params: { id: row.id } }" class="link font-semibold">
            {{ row.name }}
          </RouterLink>
        </template>

        <template #cell-type="{ row }">
          <SuiteTypeBadge :type="row.type" :label="row.typeLabel" />
        </template>

        <template #cell-testPattern="{ row }">
          <span class="mono-id block max-w-56 truncate" :title="row.testPattern">{{ row.testPattern }}</span>
        </template>

        <template #cell-schedule="{ row }">
          <span v-if="row.cronExpression" class="mono-id">{{ row.cronExpression }}</span>
          <span v-else class="text-xs text-ink-faint">Manual</span>
        </template>

        <template #cell-environments="{ row }">
          <div v-if="row.environments.length" class="flex max-w-52 flex-wrap gap-1">
            <span
              v-for="env in row.environments"
              :key="env.id"
              class="badge border border-edge bg-panel-2 text-ink-mute"
              :title="env.name"
            >{{ env.code }}</span>
          </div>
          <span v-else class="text-xs text-ink-faint">None</span>
        </template>

        <template #cell-status="{ row }">
          <span
            class="badge border"
            :class="row.isActive ? 'border-pass/25 bg-pass/10 text-pass' : 'border-edge bg-panel-2 text-ink-mute'"
          >
            {{ row.isActive ? 'Active' : 'Inactive' }}
          </span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1">
            <button class="btn-ghost btn-sm" title="Run now" @click="runNow(row)">
              <Play class="h-3.5 w-3.5 text-pass" />
            </button>
            <button class="btn-ghost btn-sm" title="Duplicate" @click="askConfirm('duplicate', row)">
              <Copy class="h-3.5 w-3.5" />
            </button>
            <RouterLink :to="{ name: 'suite-edit', params: { id: row.id } }" class="btn-ghost btn-sm" title="Edit">
              <Pencil class="h-3.5 w-3.5" />
            </RouterLink>
            <button
              class="btn-ghost btn-sm"
              :title="row.isActive ? 'Deactivate' : 'Activate'"
              @click="askConfirm('toggle', row)"
            >
              <Power class="h-3.5 w-3.5" :class="row.isActive ? 'text-skip' : 'text-pass'" />
            </button>
            <button class="btn-ghost btn-sm" title="Delete" @click="askConfirm('delete', row)">
              <Trash2 class="h-3.5 w-3.5 text-fail" />
            </button>
          </div>
        </template>

        <template #empty>
          <EmptyState
            title="No test suites configured"
            message="Create a reusable test collection to schedule and run it across environments."
          >
            <RouterLink :to="{ name: 'suite-new' }" class="btn-primary btn-sm mt-2">
              <Plus class="h-3.5 w-3.5" />
              Add Test Suite
            </RouterLink>
          </EmptyState>
        </template>

        <template #footer>
          <Pagination :page="1" :pages="1" :total="suites.length" />
        </template>
      </DataTable>
    </div>

    <ConfirmDialog
      :open="confirmState !== null"
      :title="confirmConfig.title"
      :message="confirmConfig.message"
      :confirm-label="confirmConfig.confirmLabel"
      :danger="confirmConfig.danger"
      :busy="confirmBusy"
      @confirm="runConfirmed"
      @cancel="confirmState = null"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Copy, Pencil, Play, Plus, Power, Trash2 } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import Pagination from '../../components/ui/Pagination.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import SuiteTypeBadge from './components/SuiteTypeBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const router = useRouter();
const toasts = useToastStore();

const suites = ref([]);
const loading = ref(true);
const confirmState = ref(null);
const confirmBusy = ref(false);

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'type', label: 'Type' },
  { key: 'testPattern', label: 'Test Pattern' },
  { key: 'schedule', label: 'Schedule' },
  { key: 'environments', label: 'Environments' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: '', headerClass: 'text-right', cellClass: 'text-right' },
];

const confirmConfig = computed(() => {
  if (!confirmState.value) return {};
  const { action, suite } = confirmState.value;
  if (action === 'delete') {
    return {
      title: 'Delete test suite',
      message: `Delete suite "${suite.name}"? This cannot be undone.`,
      confirmLabel: 'Delete',
      danger: true,
    };
  }
  if (action === 'duplicate') {
    return {
      title: 'Duplicate test suite',
      message: `Create a copy of "${suite.name}"?`,
      confirmLabel: 'Duplicate',
      danger: false,
    };
  }
  return suite.isActive
    ? {
        title: 'Deactivate test suite',
        message: `Deactivate "${suite.name}"? Scheduled runs will stop until it is reactivated.`,
        confirmLabel: 'Deactivate',
        danger: false,
      }
    : {
        title: 'Activate test suite',
        message: `Activate "${suite.name}"?`,
        confirmLabel: 'Activate',
        danger: false,
      };
});

async function load() {
  loading.value = true;
  try {
    suites.value = await api.get('/api/test-suites/list');
  } catch (e) {
    toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

function runNow(suite) {
  router.push({ name: 'test-run-new', query: { suiteId: suite.id } });
}

function askConfirm(action, suite) {
  confirmState.value = { action, suite };
}

async function runConfirmed() {
  const { action, suite } = confirmState.value;
  confirmBusy.value = true;
  try {
    let result;
    if (action === 'delete') {
      result = await api.delete(`/api/test-suites/${suite.id}`);
    } else if (action === 'duplicate') {
      result = await api.post(`/api/test-suites/${suite.id}/duplicate`);
    } else {
      result = await api.post(`/api/test-suites/${suite.id}/toggle-active`);
    }
    toasts.success(result.message);
    confirmState.value = null;
    await load();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    confirmBusy.value = false;
  }
}

onMounted(load);
</script>
