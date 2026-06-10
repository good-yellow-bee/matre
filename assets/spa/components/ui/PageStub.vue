<template>
  <div>
    <PageHeader :title="title" />
    <div class="card grid place-items-center gap-3 p-16 text-center">
      <Construction class="h-8 w-8 text-ink-faint" />
      <div class="text-sm font-semibold text-ink">{{ title }} is being rebuilt</div>
      <div class="text-xs text-ink-faint">This screen lands in an upcoming build wave.</div>
    </div>
  </div>
</template>

<script setup>
import { Construction } from 'lucide-vue-next';
import PageHeader from './PageHeader.vue';

defineProps({ title: { type: String, required: true } });
</script>
