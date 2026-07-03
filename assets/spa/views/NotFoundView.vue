<template>
  <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-surface px-4">
    <div class="bg-grid-dots absolute inset-0"></div>
    <div class="relative text-center">
      <div class="font-mono text-7xl font-bold text-accent">404</div>
      <div class="mt-2 font-mono text-sm uppercase tracking-[0.3em] text-ink-faint">route not found</div>
      <p class="mt-4 text-sm text-ink-mute">This path doesn't match any known screen.</p>
      <router-link :to="{ name: 'dashboard' }" class="btn-primary mt-6 inline-flex">
        <ArrowLeft class="h-4 w-4" />
        Back to dashboard
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ArrowLeft } from 'lucide-vue-next';
</script>
