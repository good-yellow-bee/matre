<template>
  <div>
    <PageHeader :subtitle="`${today} · mission overview`">
      <template #title>Welcome back, {{ username }}</template>
    </PageHeader>

    <!-- Stat cards -->
    <section class="rise" style="--i: 1">
      <div v-if="statsError" class="flex items-center gap-3 rounded-xl border border-fail/30 bg-fail/10 p-4 text-sm text-fail">
        <TriangleAlert class="h-4 w-4 shrink-0" />
        {{ statsError }}
        <button class="ml-auto cursor-pointer font-semibold hover:underline" @click="loadStats">Retry</button>
      </div>
      <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-7">
        <template v-if="statsLoading">
          <div v-for="i in 7" :key="`stat-skel-${i}`" class="card p-4">
            <div class="skeleton h-3 w-16"></div>
            <div class="skeleton mt-3 h-7 w-12"></div>
            <div class="skeleton mt-2 h-3 w-20"></div>
          </div>
        </template>
        <template v-else>
          <div
            v-for="(card, i) in statCards"
            :key="card.key"
            class="card rise p-4"
            :style="{ '--i': i }"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="truncate text-[10px] font-bold uppercase tracking-widest text-ink-faint">{{ card.label }}</span>
              <component :is="card.icon" class="h-4 w-4 shrink-0" :class="card.tone" />
            </div>
            <div class="mt-2 flex items-center gap-2.5 font-mono text-2xl font-bold tabular-nums" :class="card.live ? 'text-run' : 'text-ink'">
              <span v-if="card.live" class="led led-pulse text-run"></span>
              {{ card.value }}
            </div>
            <div class="mt-1 truncate text-[11px] text-ink-faint">{{ card.sub }}</div>
          </div>
        </template>
      </div>
    </section>

    <!-- Environment health -->
    <section class="rise mt-8" style="--i: 2">
      <div class="mb-3 flex items-center gap-2">
        <Gauge class="h-4 w-4 text-accent" />
        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-ink-mute">Environment Health</h2>
      </div>

      <div v-if="envError" class="flex items-center gap-3 rounded-xl border border-fail/30 bg-fail/10 p-4 text-sm text-fail">
        <TriangleAlert class="h-4 w-4 shrink-0" />
        {{ envError }}
        <button class="ml-auto cursor-pointer font-semibold hover:underline" @click="loadEnvironments">Retry</button>
      </div>

      <div v-else-if="envLoading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="i in 3" :key="`env-skel-${i}`" class="card p-4">
          <div class="flex items-center justify-between">
            <div class="skeleton h-4 w-28"></div>
            <div class="skeleton h-5 w-20"></div>
          </div>
          <div class="skeleton mt-4 h-8 w-16"></div>
          <div class="skeleton mt-3 h-3 w-36"></div>
          <div class="skeleton mt-3 h-3 w-24"></div>
        </div>
      </div>

      <div v-else-if="!environments.length" class="card p-10">
        <EmptyState title="No active environments" message="Add a test environment to start tracking pass rates and run health.">
          <RouterLink :to="{ name: 'environment-new' }" class="btn-primary btn-sm mt-2">
            <Plus class="h-3.5 w-3.5" />
            Add Environment
          </RouterLink>
        </EmptyState>
      </div>

      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <component
          :is="env.lastRun ? RouterLink : 'div'"
          v-for="(env, i) in environments"
          :key="env.id"
          v-bind="env.lastRun ? { to: { name: 'test-run-detail', params: { id: env.lastRun.id } } } : {}"
          class="card rise group block p-4 transition-all"
          :class="env.lastRun ? 'cursor-pointer hover:border-accent/50 hover:shadow-md' : 'opacity-60'"
          :style="{ '--i': i }"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="truncate text-sm font-semibold text-ink">{{ env.name }}</div>
              <div class="mt-0.5 truncate font-mono text-[11px] text-ink-faint">{{ env.code }} · {{ env.region }}</div>
            </div>
            <StatusBadge v-if="env.lastRun" :status="env.lastRun.status" />
          </div>

          <template v-if="env.lastRun">
            <div class="mt-3 flex items-baseline gap-2">
              <span class="font-mono text-2xl font-bold tabular-nums" :class="passRateColor(env.lastRun.results.passRate)">
                {{ env.lastRun.results.passRate }}%
              </span>
              <span
                v-if="env.lastRun.passRateDelta !== null"
                class="inline-flex items-center gap-0.5 font-mono text-xs font-semibold tabular-nums"
                :class="deltaTone(env.lastRun.passRateDelta)"
              >
                <ArrowUp v-if="env.lastRun.passRateDelta > 0" class="h-3.5 w-3.5" />
                <ArrowDown v-else-if="env.lastRun.passRateDelta < 0" class="h-3.5 w-3.5" />
                <Minus v-else class="h-3.5 w-3.5" />
                {{ Math.abs(env.lastRun.passRateDelta) }}
              </span>
            </div>

            <div class="mt-1.5 flex flex-wrap gap-x-2.5 gap-y-0.5 font-mono text-[11px]">
              <span v-if="env.lastRun.results.passed" class="text-pass">{{ env.lastRun.results.passed }} passed</span>
              <span v-if="env.lastRun.results.failed" class="text-fail">{{ env.lastRun.results.failed }} failed</span>
              <span v-if="env.lastRun.results.broken" class="text-broken">{{ env.lastRun.results.broken }} broken</span>
              <span v-if="env.lastRun.results.skipped" class="text-skip">{{ env.lastRun.results.skipped }} skipped</span>
            </div>

            <div class="mt-2.5 flex items-center gap-1.5 text-[11px] text-ink-faint">
              <Clock class="h-3 w-3" />
              {{ timeAgo(env.lastRun.completedAt) }}
              <ArrowUpRight class="ml-auto h-3.5 w-3.5 text-accent opacity-0 transition-opacity group-hover:opacity-100" />
            </div>
          </template>
          <div v-else class="mt-3 py-1.5 text-sm text-ink-faint">No completed runs</div>
        </component>
      </div>
    </section>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Quick actions -->
      <section class="card rise p-5 lg:col-span-2" style="--i: 3">
        <div class="mb-4 flex items-center gap-2">
          <Zap class="h-4 w-4 text-accent" />
          <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-ink-mute">Quick Actions</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
          <component
            :is="action.href ? 'a' : RouterLink"
            v-for="action in quickActions"
            :key="action.label"
            v-bind="action.href ? { href: action.href, target: '_blank', rel: 'noopener' } : { to: action.to }"
            class="group flex flex-col items-center gap-2 rounded-lg border border-edge bg-panel-2/40 p-3 text-center transition-colors hover:border-accent/40 hover:bg-accent-soft"
          >
            <div class="grid h-10 w-10 place-items-center rounded-lg transition-transform group-hover:scale-105" :class="action.bg">
              <component :is="action.icon" class="h-[18px] w-[18px]" :class="action.tone" />
            </div>
            <span class="text-xs font-medium leading-tight text-ink-mute transition-colors group-hover:text-ink">{{ action.label }}</span>
          </component>
        </div>
      </section>

      <!-- System info -->
      <section class="card rise p-5" style="--i: 4">
        <div class="mb-2 flex items-center gap-2">
          <Cpu class="h-4 w-4 text-accent" />
          <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-ink-mute">System Info</h2>
        </div>
        <div v-if="statsLoading" class="space-y-4 py-2">
          <div v-for="i in 3" :key="`sys-skel-${i}`" class="flex items-center gap-3">
            <div class="skeleton h-8 w-8"></div>
            <div class="flex-1">
              <div class="skeleton h-3 w-16"></div>
              <div class="skeleton mt-1.5 h-4 w-24"></div>
            </div>
          </div>
        </div>
        <div v-else-if="stats" class="divide-y divide-edge">
          <div class="flex items-center gap-3 py-2.5">
            <div class="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-edge bg-panel-2 font-mono text-[10px] font-bold text-ink-mute">SF</div>
            <div class="min-w-0 flex-1">
              <div class="text-[11px] text-ink-faint">Symfony</div>
              <div class="truncate font-mono text-sm font-medium text-ink">{{ stats.system.symfonyVersion }}</div>
            </div>
          </div>
          <div class="flex items-center gap-3 py-2.5">
            <div class="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-edge bg-panel-2 font-mono text-[10px] font-bold text-ink-mute">PHP</div>
            <div class="min-w-0 flex-1">
              <div class="text-[11px] text-ink-faint">PHP Version</div>
              <div class="truncate font-mono text-sm font-medium text-ink">{{ stats.system.phpVersion }}</div>
            </div>
          </div>
          <div class="flex items-center gap-3 py-2.5">
            <div class="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-edge bg-panel-2 font-mono text-[10px] font-bold text-ink-mute">ENV</div>
            <div class="min-w-0 flex-1">
              <div class="text-[11px] text-ink-faint">Environment</div>
              <div class="mt-0.5">
                <span class="badge border" :class="stats.system.debug ? 'border-run/25 bg-run/10 text-run' : 'border-pass/25 bg-pass/10 text-pass'">
                  {{ stats.system.environment }}{{ stats.system.debug ? ' · debug' : '' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import {
  Activity,
  ArrowDown,
  ArrowUp,
  ArrowUpRight,
  ChartColumn,
  CircleCheck,
  CircleX,
  Clock,
  Cpu,
  Gauge,
  History,
  Layers,
  ListChecks,
  Minus,
  Play,
  Plus,
  Radio,
  Server,
  TriangleAlert,
  Users,
  Zap,
} from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { api } from '../../api/client';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const username = computed(() => auth.user?.username ?? '');
const today = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

const stats = ref(null);
const statsLoading = ref(true);
const statsError = ref('');

const environments = ref([]);
const envLoading = ref(true);
const envError = ref('');

const statCards = computed(() => {
  if (!stats.value) return [];
  const { testRuns, activity, environments: envs, suites, users } = stats.value;
  return [
    { key: 'runs', label: 'Test Runs', value: testRuns.total, sub: `last ${testRuns.period}`, icon: Activity, tone: 'text-accent' },
    { key: 'passed', label: 'Passed', value: testRuns.completed, sub: 'completed runs', icon: CircleCheck, tone: 'text-pass' },
    { key: 'failed', label: 'Failed', value: testRuns.failed, sub: 'failed runs', icon: CircleX, tone: 'text-fail' },
    { key: 'running', label: 'Running Now', value: activity.runningNow, sub: activity.runningNow > 0 ? 'live execution' : 'all quiet', icon: Radio, tone: 'text-run', live: activity.runningNow > 0 },
    { key: 'environments', label: 'Environments', value: `${envs.active}/${envs.total}`, sub: 'active / total', icon: Server, tone: 'text-broken' },
    { key: 'suites', label: 'Suites', value: `${suites.scheduled}/${suites.active}`, sub: 'scheduled / active', icon: Layers, tone: 'text-accent-2' },
    { key: 'users', label: 'Users', value: users.active, sub: `of ${users.total} total`, icon: Users, tone: 'text-skip' },
  ];
});

const quickActions = computed(() => [
  { label: 'New Test Run', to: { name: 'test-run-new' }, icon: Play, bg: 'bg-accent-soft', tone: 'text-accent' },
  { label: 'Add Environment', to: { name: 'environment-new' }, icon: Server, bg: 'bg-broken/10', tone: 'text-broken' },
  { label: 'New Test Suite', to: { name: 'suite-new' }, icon: Layers, bg: 'bg-pass/10', tone: 'text-pass' },
  { label: 'View All Runs', to: { name: 'test-runs' }, icon: ListChecks, bg: 'bg-skip/10', tone: 'text-skip' },
  { label: 'Test History', to: { name: 'test-history' }, icon: History, bg: 'bg-accent-2/10', tone: 'text-accent-2' },
  { label: 'Allure Results', href: auth.urls.allure, icon: ChartColumn, bg: 'bg-run/10', tone: 'text-run' },
]);

async function loadStats() {
  statsLoading.value = true;
  statsError.value = '';
  try {
    stats.value = await api.get('/api/dashboard/stats');
  } catch (e) {
    statsError.value = e.message;
  } finally {
    statsLoading.value = false;
  }
}

async function loadEnvironments() {
  envLoading.value = true;
  envError.value = '';
  try {
    const data = await api.get('/api/dashboard/environment-stats');
    environments.value = data.environments;
  } catch (e) {
    envError.value = e.message;
  } finally {
    envLoading.value = false;
  }
}

function passRateColor(rate) {
  if (rate >= 90) return 'text-pass';
  if (rate >= 70) return 'text-run';
  return 'text-fail';
}

function deltaTone(delta) {
  if (delta > 0) return 'text-pass';
  if (delta < 0) return 'text-fail';
  return 'text-ink-faint';
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr.includes('T') ? dateStr : dateStr.replace(' ', 'T'));
  const diffMins = Math.floor((Date.now() - date.getTime()) / 60000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays === 1) return 'yesterday';
  return `${diffDays}d ago`;
}

onMounted(() => {
  loadStats();
  loadEnvironments();
});
</script>
