<template>
  <div class="relative min-h-screen overflow-hidden bg-surface">
    <div class="bg-grid-dots absolute inset-0"></div>
    <div class="glow-band absolute inset-x-0 top-0 h-[420px]"></div>

    <header class="relative mx-auto flex max-w-5xl items-center justify-between px-6 py-5">
      <div class="flex items-center gap-2.5">
        <BrandMark class="h-8 w-8" />
        <span class="font-mono text-sm font-bold tracking-tight text-ink">MATRE</span>
      </div>
      <router-link :to="{ name: auth.isAuthenticated ? 'dashboard' : 'login' }" class="btn-ghost btn-sm">
        {{ auth.isAuthenticated ? 'Open console' : 'Sign in' }}
        <ArrowRight class="h-3.5 w-3.5" />
      </router-link>
    </header>

    <main class="relative mx-auto max-w-5xl px-6 pb-24 pt-16 text-center">
      <p class="rise font-mono text-xs font-semibold uppercase tracking-[0.3em] text-accent" style="--i: 0">
        Magento Automated Test Run Environment
      </p>
      <h1 class="rise mx-auto mt-4 max-w-2xl text-4xl font-bold tracking-tight text-ink sm:text-5xl" style="--i: 1">
        Mission control for your Magento test fleet
      </h1>
      <p class="rise mx-auto mt-4 max-w-xl text-base text-ink-mute" style="--i: 2">
        Execute and orchestrate MFTF and Playwright suites across every environment —
        scheduled, observed live, reported in Allure.
      </p>
      <div class="rise mt-8" style="--i: 3">
        <router-link :to="{ name: auth.isAuthenticated ? 'dashboard' : 'login' }" class="btn-primary">
          Get started
          <ArrowRight class="h-4 w-4" />
        </router-link>
      </div>

      <div class="mt-20 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div
          v-for="(feature, index) in features"
          :key="feature.title"
          class="rise card p-5 text-left"
          :style="{ '--i': 4 + index }"
        >
          <component :is="feature.icon" class="h-5 w-5 text-accent" />
          <div class="mt-3 text-sm font-bold text-ink">{{ feature.title }}</div>
          <div class="mt-1 text-xs leading-relaxed text-ink-faint">{{ feature.text }}</div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ArrowRight, Bug, CalendarClock, ChartNoAxesCombined, Globe, MonitorPlay } from 'lucide-vue-next';
import BrandMark from '../components/shell/BrandMark.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const features = [
  { icon: Bug, title: 'MFTF Tests', text: 'Native Magento functional test execution via Docker.' },
  { icon: MonitorPlay, title: 'Playwright', text: 'Modern browser automation for storefront flows.' },
  { icon: Globe, title: 'Multi-Environment', text: 'Stage, preprod and production targets in one fleet.' },
  { icon: CalendarClock, title: 'Scheduling', text: 'Cron-driven suites with retries and notifications.' },
  { icon: ChartNoAxesCombined, title: 'Reporting', text: 'Allure history, live output and screenshots.' },
];
</script>
