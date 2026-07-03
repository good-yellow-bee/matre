<template>
  <div class="max-h-96 overflow-y-auto rounded-lg border border-edge">
    <table class="w-full">
      <thead class="sticky top-0 z-10 border-b border-edge bg-panel-2">
        <tr>
          <th class="th-base w-64 py-2">Name</th>
          <th class="th-base py-2">Value</th>
          <th class="th-base w-44 py-2">Used in Tests</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-edge">
        <tr v-for="variable in variables" :key="variable.id" class="hover:bg-panel-2/40">
          <td class="px-4 py-2 align-top font-mono text-xs font-semibold break-all text-accent">{{ variable.name }}</td>
          <td class="px-4 py-2 align-top text-xs text-ink-mute">
            <SecretValue :name="variable.name" :value="variable.value" />
          </td>
          <td class="px-4 py-2 align-top font-mono text-xs text-ink-faint">{{ variable.usedInTests || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import SecretValue from './SecretValue.vue';

defineProps({
  variables: { type: Array, default: () => [] },
});
</script>
