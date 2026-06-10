<template>
  <div>
    <div v-if="loading" class="space-y-5">
      <div class="skeleton h-8 w-64"></div>
      <div class="grid gap-5 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2"><div class="skeleton h-40 w-full"></div></div>
        <div class="card p-5"><div class="skeleton h-40 w-full"></div></div>
      </div>
    </div>

    <template v-else-if="job">
      <PageHeader :title="job.name" :subtitle="job.description || 'No description'">
        <template #actions>
          <RouterLink class="btn-ghost" :to="{ name: 'cron-jobs' }">
            <ArrowLeft class="h-4 w-4" />
            Back to List
          </RouterLink>
          <button class="btn-primary" :disabled="running" @click="confirmRunOpen = true">
            <Loader2 v-if="running" class="h-4 w-4 animate-spin" />
            <Play v-else class="h-4 w-4" />
            Run Now
          </button>
        </template>
      </PageHeader>

      <div class="grid items-start gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
          <!-- Job Configuration -->
          <section class="card rise p-5" style="--i: 1">
            <header class="mb-4 flex items-center justify-between border-b border-edge pb-3">
              <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
                  <CalendarClock class="h-4 w-4" />
                </span>
                <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Job Configuration</h2>
              </div>
              <span class="badge border" :class="job.isActive ? 'text-pass border-pass/25 bg-pass/10' : 'text-skip border-skip/25 bg-skip/10'">
                {{ job.isActive ? 'Active' : 'Inactive' }}
              </span>
            </header>
            <dl class="space-y-3 text-sm">
              <div class="flex flex-wrap items-baseline gap-x-6">
                <dt class="w-28 shrink-0 text-xs font-semibold uppercase tracking-wider text-ink-faint">Command</dt>
                <dd><code class="rounded-md border border-edge bg-panel-2 px-2 py-0.5 font-mono text-[13px] text-accent">{{ job.command }}</code></dd>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-6">
                <dt class="w-28 shrink-0 text-xs font-semibold uppercase tracking-wider text-ink-faint">Schedule</dt>
                <dd class="flex flex-wrap items-baseline gap-2">
                  <span class="kbd">{{ job.cronExpression }}</span>
                  <span v-if="nextRun" class="text-xs text-ink-mute">next run {{ nextRun }}</span>
                </dd>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-6">
                <dt class="w-28 shrink-0 text-xs font-semibold uppercase tracking-wider text-ink-faint">Created</dt>
                <dd class="font-mono text-[13px] text-ink-mute">{{ formatDate(job.createdAt) }}</dd>
              </div>
              <div v-if="job.updatedAt" class="flex flex-wrap items-baseline gap-x-6">
                <dt class="w-28 shrink-0 text-xs font-semibold uppercase tracking-wider text-ink-faint">Updated</dt>
                <dd class="font-mono text-[13px] text-ink-mute">{{ formatDate(job.updatedAt) }}</dd>
              </div>
            </dl>
          </section>

          <!-- Last Output -->
          <section v-if="job.lastOutput" class="card rise overflow-hidden" style="--i: 2">
            <header class="flex items-center gap-2.5 border-b border-edge px-5 py-3.5">
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
                <Terminal class="h-4 w-4" />
              </span>
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Last Output</h2>
            </header>
            <pre class="max-h-96 overflow-y-auto bg-zinc-950 p-4 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words text-zinc-200">{{ job.lastOutput }}</pre>
          </section>
        </div>

        <div class="space-y-5">
          <!-- Last Execution -->
          <section class="card rise p-5" style="--i: 3">
            <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
                <History class="h-4 w-4" />
              </span>
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Last Execution</h2>
            </header>
            <template v-if="job.lastRunAt">
              <div class="mb-3">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Time</div>
                <div class="mt-1 font-mono text-[13px] text-ink">{{ formatDate(job.lastRunAt) }}</div>
              </div>
              <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-faint">Status</div>
                <div class="mt-1.5"><CronStatusBadge :status="job.lastStatus" /></div>
              </div>
            </template>
            <p v-else class="text-sm text-ink-faint">Never executed</p>
          </section>

          <!-- Actions -->
          <section class="card rise p-5" style="--i: 4">
            <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
              <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
                <Zap class="h-4 w-4" />
              </span>
              <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Actions</h2>
            </header>
            <div class="flex flex-col gap-2">
              <button class="btn-primary w-full" :disabled="running" @click="confirmRunOpen = true">
                <Play class="h-4 w-4" />
                Run Now
              </button>
              <RouterLink class="btn-ghost w-full" :to="{ name: 'cron-job-edit', params: { id: job.id } }">
                <Pencil class="h-4 w-4" />
                Edit
              </RouterLink>
              <button class="btn-ghost w-full" :disabled="toggling" @click="toggleActive">
                <Power class="h-4 w-4" />
                {{ job.isActive ? 'Deactivate' : 'Activate' }}
              </button>
              <RouterLink class="btn-ghost w-full" :to="{ name: 'cron-jobs' }">
                <ArrowLeft class="h-4 w-4" />
                Back to List
              </RouterLink>
            </div>
          </section>
        </div>
      </div>

      <ConfirmDialog
        :open="confirmRunOpen"
        title="Run Cron Job"
        :message="`Run “${job.name}” now? The command will be dispatched to the queue immediately.`"
        confirm-label="Run Now"
        :busy="running"
        @confirm="runNow"
        @cancel="confirmRunOpen = false"
      />
    </template>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, CalendarClock, History, Loader2, Pencil, Play, Power, Terminal, Zap } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import CronStatusBadge from './components/CronStatusBadge.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const route = useRoute();
const router = useRouter();
const toasts = useToastStore();

const job = ref(null);
const nextRun = ref('');
const loading = ref(true);
const running = ref(false);
const toggling = ref(false);
const confirmRunOpen = ref(false);
let refreshTimeout = null;

function formatDate(iso) {
  return new Date(iso).toLocaleString(undefined, {
    year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit',
  });
}

async function fetchJob() {
  try {
    job.value = await api.get(`/api/cron-jobs/${route.params.id}`);
    fetchNextRun();
  } catch (e) {
    toasts.error(e.status === 404 ? 'Cron job not found' : e.message);
    router.push({ name: 'cron-jobs' });
  } finally {
    loading.value = false;
  }
}

async function fetchNextRun() {
  try {
    const data = await api.post('/api/cron-jobs/validate-cron', { expression: job.value.cronExpression });
    nextRun.value = data.valid ? data.nextRun : '';
  } catch {
    nextRun.value = '';
  }
}

async function runNow() {
  running.value = true;
  try {
    const data = await api.post(`/api/cron-jobs/${job.value.id}/run`);
    toasts.success(data.message);
    confirmRunOpen.value = false;
    refreshTimeout = setTimeout(fetchJob, 1500);
  } catch (e) {
    toasts.error(e.message);
  } finally {
    running.value = false;
  }
}

async function toggleActive() {
  toggling.value = true;
  try {
    const data = await api.post(`/api/cron-jobs/${job.value.id}/toggle-active`);
    job.value.isActive = data.isActive;
    toasts.success(data.message);
  } catch (e) {
    toasts.error(e.message);
  } finally {
    toggling.value = false;
  }
}

onMounted(fetchJob);
onUnmounted(() => clearTimeout(refreshTimeout));
</script>
