<template>
  <div>
    <PageHeader :title="environment?.name || 'Environment'" :subtitle="environment?.description || 'Test environment configuration'" />

    <div v-if="loading" class="rise grid gap-6 lg:grid-cols-3" style="--i: 1">
      <div class="card space-y-3 p-5 lg:col-span-2">
        <div v-for="i in 6" :key="i" class="skeleton h-4" :style="{ width: `${85 - i * 9}%` }"></div>
      </div>
      <div class="card space-y-3 p-5">
        <div v-for="i in 4" :key="i" class="skeleton h-8"></div>
      </div>
    </div>

    <ErrorBanner v-else-if="loadError" class="rise" style="--i: 1" :message="loadError" @retry="init" />

    <div v-else-if="environment" class="grid items-start gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <section class="card rise" style="--i: 1">
          <div class="flex items-center justify-between border-b border-edge px-5 py-3.5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Environment Configuration</h2>
            <ActiveBadge :active="environment.isActive" />
          </div>
          <dl class="divide-y divide-edge">
            <div class="grid grid-cols-[160px_1fr] gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Code</dt>
              <dd class="mono-id">{{ environment.code }}</dd>
            </div>
            <div class="grid grid-cols-[160px_1fr] gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Region</dt>
              <dd><span class="badge border border-edge-2 bg-panel-2 text-ink-mute">{{ environment.region }}</span></dd>
            </div>
            <div class="grid grid-cols-[160px_1fr] gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Base URL</dt>
              <dd>
                <a :href="environment.displayUrl" target="_blank" rel="noopener" class="link inline-flex items-center gap-1.5 break-all font-mono text-xs">
                  {{ environment.displayUrl }}
                  <ExternalLink class="h-3 w-3 shrink-0" />
                </a>
              </dd>
            </div>
            <div class="grid grid-cols-[160px_1fr] gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Backend Name</dt>
              <dd class="mono-id">{{ environment.backendName }}</dd>
            </div>
            <div class="grid grid-cols-[160px_1fr] gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Created</dt>
              <dd class="font-mono text-xs text-ink-mute">{{ formatDateTime(environment.createdAt) }}</dd>
            </div>
            <div v-if="environment.updatedAt" class="grid grid-cols-[160px_1fr] gap-4 px-5 py-3">
              <dt class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Updated</dt>
              <dd class="font-mono text-xs text-ink-mute">{{ formatDateTime(environment.updatedAt) }}</dd>
            </div>
          </dl>
        </section>

        <section class="card rise" style="--i: 2">
          <div class="border-b border-edge px-5 py-3.5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Environment Variables</h2>
          </div>
          <div class="space-y-6 p-5">
            <div v-if="varsLoading" class="space-y-2">
              <div v-for="i in 4" :key="i" class="skeleton h-4" :style="{ width: `${90 - i * 12}%` }"></div>
            </div>
            <template v-else>
              <div v-if="globalVariables.length">
                <h3 class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink-mute">
                  <Globe class="h-3.5 w-3.5" />
                  Inherited Global Variables
                  <span class="badge border border-edge-2 bg-panel-2 text-ink-mute">{{ globalVariables.length }}</span>
                </h3>
                <VariablesTable :variables="globalVariables" />
              </div>

              <div>
                <h3 class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink-mute">
                  <Braces class="h-3.5 w-3.5" />
                  Environment-Specific Variables
                  <span class="badge border border-accent/25 bg-accent-soft text-accent">{{ envVariables.length }}</span>
                </h3>
                <VariablesTable v-if="envVariables.length" :variables="envVariables" />
                <p v-else class="rounded-lg border border-dashed border-edge-2 px-4 py-6 text-center text-xs text-ink-faint">
                  No environment-specific variables. Add them on the edit page to override global values.
                </p>
              </div>
            </template>
          </div>
        </section>
      </div>

      <aside class="card rise" style="--i: 3">
        <div class="border-b border-edge px-5 py-3.5">
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Actions</h2>
        </div>
        <div class="space-y-2 p-5">
          <button class="btn-primary w-full" @click="startTestRun">
            <Play class="h-4 w-4" />
            Start Test Run
          </button>
          <RouterLink class="btn-ghost w-full" :to="{ name: 'environment-edit', params: { id: environment.id } }">
            <Pencil class="h-4 w-4" />
            Edit
          </RouterLink>
          <button class="btn-ghost w-full" @click="askToggle">
            <Pause v-if="environment.isActive" class="h-4 w-4" />
            <Play v-else class="h-4 w-4" />
            {{ environment.isActive ? 'Deactivate' : 'Activate' }}
          </button>
          <RouterLink class="btn-ghost w-full" :to="{ name: 'environments' }">
            <ArrowLeft class="h-4 w-4" />
            Back to List
          </RouterLink>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Braces, ExternalLink, Globe, Pause, Pencil, Play } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import ErrorBanner from '../../components/ui/ErrorBanner.vue';
import ActiveBadge from './components/ActiveBadge.vue';
import VariablesTable from './components/VariablesTable.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { confirm } from '../../composables/useConfirm';
import { formatDateTime } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const environment = ref(null);
const loading = ref(true);
const loadError = ref('');
const globalVariables = ref([]);
const envVariables = ref([]);
const varsLoading = ref(true);

async function load() {
  loading.value = true;
  loadError.value = '';
  try {
    environment.value = await api.get(`/api/test-environments/${route.params.id}`);
  } catch (e) {
    if (e.status === 404) {
      toasts.error(e.message);
      router.replace({ name: 'environments' });
    } else {
      loadError.value = e.message;
    }
  } finally {
    loading.value = false;
  }
}

async function loadVariables() {
  varsLoading.value = true;
  try {
    const data = await api.get(`/api/test-environments/${route.params.id}/env-variables`);
    globalVariables.value = data.global || [];
    envVariables.value = data.environment || [];
  } catch (e) {
    toasts.error(e.message);
  } finally {
    varsLoading.value = false;
  }
}

function startTestRun() {
  router.push({ name: 'test-run-new', query: { environmentId: environment.value.id } });
}

function askToggle() {
  const { isActive, name } = environment.value;
  confirm({
    title: isActive ? 'Deactivate environment?' : 'Activate environment?',
    message: isActive
      ? `Environment “${name}” will no longer be available for test runs.`
      : `Environment “${name}” will become available for test runs.`,
    confirmLabel: isActive ? 'Deactivate' : 'Activate',
    action: async () => {
      const result = await api.post(`/api/test-environments/${environment.value.id}/toggle-active`);
      environment.value.isActive = result.isActive;
      toasts.success(result.message);
    },
  });
}

function init() {
  load();
  loadVariables();
}

onMounted(init);
</script>
