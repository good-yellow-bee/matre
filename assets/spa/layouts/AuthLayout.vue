<template>
  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-surface px-4">
    <div class="bg-grid-dots absolute inset-0"></div>
    <div class="glow-band absolute inset-x-0 top-0 h-96"></div>
    <div class="rise relative w-full max-w-md">
      <div class="mb-8 flex items-center justify-center gap-3">
        <BrandMark class="h-10 w-10" />
        <div>
          <div class="font-mono text-lg font-bold tracking-tight text-ink">MATRE</div>
          <div class="text-[11px] font-medium uppercase tracking-[0.2em] text-ink-faint">Test Run Environment</div>
        </div>
      </div>
      <div class="card p-8">
        <slot />
      </div>
      <p class="mt-6 text-center text-xs text-ink-faint">
        <slot name="footer" />
      </p>
    </div>
  </div>
</template>

<script setup>
import BrandMark from '../components/shell/BrandMark.vue';
</script>
