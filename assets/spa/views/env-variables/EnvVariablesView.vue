<template>
  <div>
    <PageHeader
      title="Global Env Variables"
      subtitle="Shared variables across all test environments; environment-specific values override these."
    >
      <template #actions>
        <button class="btn-ghost" @click="importOpen = true">
          <Upload class="h-4 w-4" />
          Import .env
        </button>
        <button class="btn-primary" :disabled="editing !== null" @click="startAdd">
          <Plus class="h-4 w-4" />
          Add Variable
        </button>
      </template>
    </PageHeader>

    <div class="rise mb-4 flex flex-wrap items-center gap-3" style="--i: 1">
      <div class="relative w-full max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" />
        <input v-model="search" type="text" class="input pl-9 pr-8" placeholder="Search variables...">
        <button
          v-if="search"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 cursor-pointer text-ink-faint hover:text-ink"
          @click="search = ''"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </div>
      <select v-model="envFilter" class="input w-52" @change="onFilterChange">
        <option value="all">All Environments</option>
        <option value="global">Global Only</option>
        <option v-for="env in environments" :key="env" :value="env">{{ env }}</option>
      </select>
    </div>

    <ErrorBanner v-if="loadError" class="rise mb-4" style="--i: 2" :message="loadError" @retry="fetchVariables" />

    <div class="rise" style="--i: 2">
      <DataTable
        :columns="columns"
        :rows="tableRows"
        :loading="loading && !rows.length"
        :sort="sort"
        :order="order"
        row-key="id"
        @sort="onSort"
      >
        <template #cell-name="{ row }">
          <div v-if="isEditing(row)" class="min-w-44">
            <input
              :value="draft.name"
              type="text"
              class="input font-mono !text-[13px]"
              placeholder="VARIABLE_NAME"
              @input="draft.name = $event.target.value.toUpperCase()"
              @keyup.enter="saveDraft"
              @keyup.esc="cancelEdit"
            >
            <p v-if="draftErrors.name" class="field-error">{{ draftErrors.name }}</p>
          </div>
          <span v-else class="mono-id text-accent">{{ row.name }}</span>
        </template>

        <template #cell-environments="{ row }">
          <div v-if="isEditing(row)" class="min-w-40">
            <EnvScopePicker v-model="draft.environments" :environments="environments" />
          </div>
          <div v-else class="flex max-w-44 flex-wrap gap-1">
            <span v-if="!row.environments?.length" class="badge border border-accent/25 bg-accent-soft text-accent">
              <Globe class="h-3 w-3" />
              Global
            </span>
            <template v-else>
              <span v-for="env in row.environments" :key="env" class="badge border border-edge-2 bg-panel-2 text-ink-mute">
                {{ env }}
              </span>
            </template>
          </div>
        </template>

        <template #cell-value="{ row }">
          <div v-if="isEditing(row)" class="min-w-48">
            <input
              v-model="draft.value"
              type="text"
              class="input font-mono !text-[13px]"
              placeholder="value"
              @keyup.enter="saveDraft"
              @keyup.esc="cancelEdit"
            >
            <p v-if="draftErrors.value" class="field-error">{{ draftErrors.value }}</p>
          </div>
          <div v-else class="flex items-center gap-1.5">
            <span
              class="block max-w-52 truncate font-mono text-[13px]"
              :class="isMasked(row) ? 'tracking-widest text-ink-faint' : 'text-ink'"
              :title="isMasked(row) ? undefined : row.value"
            >{{ isMasked(row) ? '••••••••' : row.value }}</span>
            <button
              v-if="isSensitiveName(row.name)"
              class="shrink-0 cursor-pointer rounded p-1 text-ink-faint hover:bg-panel-2 hover:text-ink"
              :title="isMasked(row) ? 'Reveal value' : 'Hide value'"
              @click="toggleReveal(row.id)"
            >
              <Eye v-if="isMasked(row)" class="h-3.5 w-3.5" />
              <EyeOff v-else class="h-3.5 w-3.5" />
            </button>
          </div>
        </template>

        <template #cell-usedInTests="{ row }">
          <input
            v-if="isEditing(row)"
            v-model="draft.usedInTests"
            type="text"
            class="input min-w-36 font-mono !text-[13px]"
            placeholder="MOEC1676,MOEC1677"
            @keyup.enter="saveDraft"
            @keyup.esc="cancelEdit"
          >
          <span v-else-if="row.usedInTests" class="block max-w-40 truncate font-mono text-xs text-ink-mute" :title="row.usedInTests">
            {{ row.usedInTests }}
          </span>
          <span v-else class="text-ink-faint">—</span>
        </template>

        <template #cell-description="{ row }">
          <input
            v-if="isEditing(row)"
            v-model="draft.description"
            type="text"
            class="input min-w-40 !text-[13px]"
            placeholder="Optional description"
            @keyup.enter="saveDraft"
            @keyup.esc="cancelEdit"
          >
          <span v-else-if="row.description" class="block max-w-48 truncate text-sm text-ink-mute" :title="row.description">
            {{ row.description }}
          </span>
          <span v-else class="text-ink-faint">—</span>
        </template>

        <template #cell-actions="{ row }">
          <div v-if="isEditing(row)" class="flex justify-end gap-1.5">
            <button class="btn-primary btn-sm" :disabled="saving" @click="saveDraft">
              <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin" />
              <Check v-else class="h-3.5 w-3.5" />
              Save
            </button>
            <button class="btn-ghost btn-sm" :disabled="saving" title="Cancel" @click="cancelEdit">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>
          <div v-else class="flex justify-end gap-1.5">
            <button class="btn-ghost btn-sm" :disabled="editing !== null" title="Edit variable" @click="startEdit(row)">
              <Pencil class="h-3.5 w-3.5" />
            </button>
            <button class="btn-danger btn-sm" :disabled="editing !== null" title="Delete variable" @click="askDelete(row)">
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </template>

        <template #empty>
          <EmptyState
            :title="isFiltered ? 'No variables match your filters' : 'No variables yet'"
            :message="isFiltered ? 'Try a different search or environment scope.' : 'Add a variable or import a .env file to get started.'"
          >
            <button v-if="!isFiltered" class="btn-primary btn-sm mt-2" @click="startAdd">
              <Plus class="h-3.5 w-3.5" />
              Add Variable
            </button>
          </EmptyState>
        </template>

        <template #footer>
          <div class="border-t border-edge px-4 py-3 font-mono text-xs text-ink-faint">
            {{ filtered.length }} variable(s)
            <span v-if="filtered.length !== rows.length">(filtered from {{ rows.length }})</span>
          </div>
        </template>
      </DataTable>
    </div>

    <ImportEnvModal :open="importOpen" @close="importOpen = false" @imported="onImported" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Check, Eye, EyeOff, Globe, Loader2, Pencil, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import ErrorBanner from '../../components/ui/ErrorBanner.vue';
import EnvScopePicker from './components/EnvScopePicker.vue';
import ImportEnvModal from './components/ImportEnvModal.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { confirm } from '../../composables/useConfirm';
import { isSensitiveName } from '../../utils/sensitive';

const NAME_PATTERN = /^[A-Z][A-Z0-9_]*$/;

const toasts = useToastStore();

const rows = ref([]);
const environments = ref([]);
const loading = ref(false);
const loadError = ref('');
const search = ref('');
const envFilter = ref('all');
const sort = ref('name');
const order = ref('asc');
const revealed = ref(new Set());

const editing = ref(null);
const draft = reactive({ name: '', value: '', environments: null, usedInTests: '', description: '' });
const draftErrors = ref({});
const saving = ref(false);

const importOpen = ref(false);

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'environments', label: 'Scope', sortable: true },
  { key: 'value', label: 'Value' },
  { key: 'usedInTests', label: 'Used in Tests', sortable: true },
  { key: 'description', label: 'Description' },
  { key: 'actions', label: '', headerClass: 'w-28', cellClass: 'text-right' },
];

const isFiltered = computed(() => search.value !== '' || envFilter.value !== 'all');

const filtered = computed(() => {
  let result = rows.value;
  if (search.value) {
    const query = search.value.toLowerCase();
    result = result.filter(
      (item) =>
        item.name.toLowerCase().includes(query)
        || (item.value || '').toLowerCase().includes(query)
        || (item.environments || []).some((env) => env.toLowerCase().includes(query))
        || (item.usedInTests || '').toLowerCase().includes(query)
        || (item.description || '').toLowerCase().includes(query),
    );
  }
  const field = sort.value;
  const direction = order.value === 'asc' ? 1 : -1;
  return [...result].sort((a, b) => {
    const left = field === 'environments' ? (a.environments?.[0] || '') : String(a[field] || '').toLowerCase();
    const right = field === 'environments' ? (b.environments?.[0] || '') : String(b[field] || '').toLowerCase();
    return left.localeCompare(right) * direction;
  });
});

const tableRows = computed(() => (editing.value === 'new' ? [{ id: '__new__' }, ...filtered.value] : filtered.value));

function isEditing(row) {
  return editing.value === 'new' ? row.id === '__new__' : editing.value === row.id;
}

function isMasked(row) {
  return isSensitiveName(row.name) && !revealed.value.has(row.id);
}

function toggleReveal(id) {
  const next = new Set(revealed.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  revealed.value = next;
}

async function fetchVariables() {
  loading.value = true;
  loadError.value = '';
  try {
    const params = { sort: 'name', order: 'asc' };
    if (envFilter.value !== 'all') params.environment = envFilter.value;
    const result = await api.get('/api/env-variables/list', { params });
    rows.value = result.data;
    environments.value = result.environments || [];
  } catch (error) {
    loadError.value = error.message;
  } finally {
    loading.value = false;
  }
}

function onFilterChange() {
  editing.value = null;
  fetchVariables();
}

function onSort(payload) {
  sort.value = payload.sort;
  order.value = payload.order;
}

function startAdd() {
  const scoped = envFilter.value !== 'all' && envFilter.value !== 'global';
  Object.assign(draft, { name: '', value: '', environments: scoped ? [envFilter.value] : null, usedInTests: '', description: '' });
  draftErrors.value = {};
  editing.value = 'new';
}

function startEdit(row) {
  Object.assign(draft, {
    name: row.name,
    value: row.value,
    environments: row.environments?.length ? [...row.environments] : null,
    usedInTests: row.usedInTests || '',
    description: row.description || '',
  });
  draftErrors.value = {};
  editing.value = row.id;
}

function cancelEdit() {
  editing.value = null;
  draftErrors.value = {};
}

async function saveDraft() {
  draftErrors.value = {};
  const name = draft.name.trim();
  if (!name) {
    draftErrors.value = { name: 'Variable name cannot be blank.' };
    return;
  }
  if (!NAME_PATTERN.test(name)) {
    draftErrors.value = { name: 'Name must be UPPERCASE with underscores (e.g., SELENIUM_HOST).' };
    return;
  }
  saving.value = true;
  const payload = {
    name,
    value: draft.value,
    environments: draft.environments?.length ? draft.environments : null,
    usedInTests: draft.usedInTests.trim() || null,
    description: draft.description.trim() || null,
  };
  try {
    const result = editing.value === 'new'
      ? await api.post('/api/env-variables', payload)
      : await api.put(`/api/env-variables/${editing.value}`, payload);
    toasts.success(result.message);
    editing.value = null;
    await fetchVariables();
  } catch (error) {
    if (error.status === 400 && error.payload?.errors && !Array.isArray(error.payload.errors)) {
      draftErrors.value = error.payload.errors;
    } else {
      toasts.error(error.message);
    }
  } finally {
    saving.value = false;
  }
}

function askDelete(row) {
  confirm({
    title: 'Delete variable',
    message: `Delete variable "${row.name}"? This cannot be undone.`,
    confirmLabel: 'Delete',
    danger: true,
    action: async () => {
      const result = await api.delete(`/api/env-variables/${row.id}`);
      toasts.success(result.message);
      await fetchVariables();
    },
  });
}

function onImported() {
  importOpen.value = false;
  fetchVariables();
}

onMounted(fetchVariables);
</script>
