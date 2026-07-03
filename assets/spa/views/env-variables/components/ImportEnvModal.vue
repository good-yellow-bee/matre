<template>
  <Modal :open="open" :title="step === 'paste' ? 'Import from .env' : 'Review import'" size="lg" @close="emit('close')">
    <template v-if="step === 'paste'">
      <p class="mb-3 text-sm text-ink-mute">
        Paste your .env file content below. Variables are parsed and previewed before anything is saved.
        Existing variables with the same name get the new value and keep their scope and description.
      </p>
      <textarea
        v-model="content"
        rows="12"
        class="input min-h-56 font-mono !text-[13px]"
        placeholder="SELENIUM_HOST=selenium-hub&#10;ALLURE_URL=http://allure:5050&#10;MAGENTO_VERSION=2.4.6"
      ></textarea>
    </template>

    <template v-else>
      <p class="mb-3 text-sm text-ink-mute">
        <span class="font-semibold text-ink">{{ preview.length }}</span> variable(s) parsed —
        <span class="text-pass">{{ counts.created }} new</span>,
        <span class="text-run">{{ counts.updated }} updated</span>.
      </p>
      <div class="overflow-hidden rounded-lg border border-edge">
        <table class="w-full">
          <thead class="border-b border-edge bg-panel-2/60">
            <tr>
              <th class="th-base">Name</th>
              <th class="th-base">Value</th>
              <th class="th-base">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-edge">
            <tr v-for="item in preview" :key="item.name">
              <td class="td-base"><span class="mono-id text-accent">{{ item.name }}</span></td>
              <td class="td-base">
                <span class="block max-w-56 truncate font-mono text-xs text-ink-mute" :title="item.value">{{ item.value }}</span>
              </td>
              <td class="td-base">
                <span
                  class="badge border"
                  :class="item.status === 'new' ? 'border-pass/25 bg-pass/10 text-pass' : 'border-run/25 bg-run/10 text-run'"
                >
                  {{ item.status === 'new' ? 'New' : 'Update' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template #footer>
      <template v-if="step === 'paste'">
        <button class="btn-ghost btn-sm" @click="emit('close')">Cancel</button>
        <button class="btn-primary btn-sm" :disabled="!content.trim() || parsing" @click="parse">
          <Loader2 v-if="parsing" class="h-3.5 w-3.5 animate-spin" />
          <Upload v-else class="h-3.5 w-3.5" />
          Parse
        </button>
      </template>
      <template v-else>
        <button class="btn-ghost btn-sm" :disabled="importing" @click="step = 'paste'">Back</button>
        <button class="btn-primary btn-sm" :disabled="importing" @click="doImport">
          <Loader2 v-if="importing" class="h-3.5 w-3.5 animate-spin" />
          <Check v-else class="h-3.5 w-3.5" />
          Import {{ preview.length }} variable(s)
        </button>
      </template>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Check, Loader2, Upload } from 'lucide-vue-next';
import Modal from '../../../components/ui/Modal.vue';
import { api } from '../../../api/client';
import { useToastStore } from '../../../stores/toasts';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'imported']);

const toasts = useToastStore();

const step = ref('paste');
const content = ref('');
const preview = ref([]);
const parsing = ref(false);
const importing = ref(false);

const counts = computed(() => ({
  created: preview.value.filter((item) => item.status === 'new').length,
  updated: preview.value.filter((item) => item.status === 'update').length,
}));

watch(
  () => props.open,
  (value) => {
    if (value) {
      step.value = 'paste';
      content.value = '';
      preview.value = [];
    }
  },
);

async function fetchAllVariables() {
  try {
    const result = await api.get('/api/env-variables/list');
    return result.data;
  } catch (error) {
    throw new Error(`Could not load existing variables — import blocked to avoid resetting variable scopes (${error.message})`);
  }
}

async function parse() {
  parsing.value = true;
  try {
    const allVariables = await fetchAllVariables();
    const result = await api.post('/api/env-variables/import', { content: content.value });
    const byName = new Map();
    for (const variable of result.variables) byName.set(variable.name, variable.value);
    preview.value = Array.from(byName, ([name, value]) => {
      const existing = allVariables.find((item) => item.name === name);
      if (existing) {
        return {
          name,
          value,
          status: 'update',
          payload: {
            id: existing.id,
            name,
            value,
            environments: existing.environments?.length ? existing.environments : null,
            usedInTests: existing.usedInTests || null,
            description: existing.description || null,
          },
        };
      }
      return {
        name,
        value,
        status: 'new',
        payload: { id: null, name, value, environments: null, usedInTests: null, description: null },
      };
    });
    if (!preview.value.length) {
      toasts.info('No variables found in the pasted content');
      return;
    }
    step.value = 'preview';
  } catch (error) {
    toasts.error(error.message);
  } finally {
    parsing.value = false;
  }
}

async function doImport() {
  importing.value = true;
  try {
    const result = await api.post('/api/env-variables/bulk', { variables: preview.value.map((item) => item.payload) });
    toasts.success(result.message);
    emit('imported');
  } catch (error) {
    const errors = error.payload?.errors;
    toasts.error(Array.isArray(errors) ? errors.join(' · ') : error.message);
  } finally {
    importing.value = false;
  }
}
</script>
