<template>
  <div>
    <!-- Loading State -->
    <div v-if="loading && !environments.length" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="i in 4" :key="i"
        class="bg-white dark:bg-charcoal-800 rounded-xl border border-slate-200 dark:border-charcoal-700 p-4 shadow-sm dark:shadow-none animate-pulse">
        <div class="flex items-center justify-between mb-3">
          <div class="h-4 w-24 bg-slate-200 dark:bg-charcoal-600 rounded"></div>
          <div class="h-5 w-12 bg-slate-200 dark:bg-charcoal-600 rounded-full"></div>
        </div>
        <div class="h-8 w-16 bg-slate-200 dark:bg-charcoal-600 rounded mb-2"></div>
        <div class="h-3 w-32 bg-slate-200 dark:bg-charcoal-600 rounded mb-3"></div>
        <div class="h-3 w-20 bg-slate-200 dark:bg-charcoal-600 rounded"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error"
      class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-xl p-4 flex items-center gap-3">
      <i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400"></i>
      <span class="text-sm text-red-700 dark:text-red-300">{{ error }}</span>
      <button @click="refresh"
        class="ml-auto text-sm font-medium text-red-600 dark:text-red-400 hover:underline">
        Retry
      </button>
    </div>

    <!-- Environment Cards -->
    <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <component
        v-for="env in environments" :key="env.id"
        :is="env.lastRun ? 'a' : 'div'"
        :href="env.lastRun ? testRunBaseUrl + env.lastRun.id : undefined"
        class="bg-white dark:bg-charcoal-800 rounded-xl border border-slate-200 dark:border-charcoal-700 p-4 shadow-sm dark:shadow-none no-underline transition-all"
        :class="env.lastRun ? 'hover:border-blue-300 dark:hover:border-blue-500/50 hover:shadow-md cursor-pointer' : 'opacity-60'"
      >
        <!-- Header: name + badge -->
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ env.name }}</span>
          <span class="shrink-0 ml-2 px-2 py-0.5 text-[10px] font-medium rounded-full bg-slate-100 dark:bg-charcoal-700 text-slate-600 dark:text-charcoal-300">
            {{ env.code }} / {{ env.region }}
          </span>
        </div>

        <template v-if="env.lastRun">
          <!-- Pass Rate -->
          <div class="flex items-baseline gap-2 mb-1">
            <span class="text-2xl font-bold" :class="passRateColor(env.lastRun.results.passRate)">
              {{ env.lastRun.results.passRate }}%
            </span>
            <!-- Delta -->
            <span v-if="env.lastRun.passRateDelta !== null"
              class="text-xs font-medium flex items-center gap-0.5"
              :class="env.lastRun.passRateDelta > 0 ? 'text-emerald-600 dark:text-emerald-400' : env.lastRun.passRateDelta < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-charcoal-500'">
              <i class="bi" :class="env.lastRun.passRateDelta > 0 ? 'bi-arrow-up-short' : env.lastRun.passRateDelta < 0 ? 'bi-arrow-down-short' : 'bi-dash'"></i>
              {{ Math.abs(env.lastRun.passRateDelta) }}
            </span>
          </div>

          <!-- Result counts -->
          <div class="flex flex-wrap gap-x-2 gap-y-0.5 text-xs mb-2">
            <span v-if="env.lastRun.results.passed" class="text-emerald-600 dark:text-emerald-400">{{ env.lastRun.results.passed }} passed</span>
            <span v-if="env.lastRun.results.failed" class="text-red-600 dark:text-red-400">{{ env.lastRun.results.failed }} failed</span>
            <span v-if="env.lastRun.results.broken" class="text-amber-600 dark:text-amber-400">{{ env.lastRun.results.broken }} broken</span>
            <span v-if="env.lastRun.results.skipped" class="text-slate-500 dark:text-charcoal-400">{{ env.lastRun.results.skipped }} skipped</span>
          </div>

          <!-- Timestamp -->
          <div class="text-[11px] text-slate-400 dark:text-charcoal-500">
            {{ timeAgo(env.lastRun.completedAt) }}
          </div>
        </template>

        <!-- No runs -->
        <div v-else class="text-sm text-slate-400 dark:text-charcoal-500 py-2">
          No completed runs
        </div>
      </component>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useEnvironmentStats } from '../composables/useEnvironmentStats.js';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
  testRunBaseUrl: {
    type: String,
    required: true,
  },
});

const { environments, loading, error, fetchStats, refresh } = useEnvironmentStats(props.apiUrl);

const passRateColor = (rate) => {
  if (rate >= 90) return 'text-emerald-600 dark:text-emerald-400';
  if (rate >= 70) return 'text-amber-600 dark:text-amber-400';
  return 'text-red-600 dark:text-red-400';
};

const timeAgo = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays === 1) return 'yesterday';
  return `${diffDays}d ago`;
};

onMounted(() => {
  fetchStats();
});
</script>
