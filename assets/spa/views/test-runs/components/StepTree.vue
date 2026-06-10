<template>
  <div class="font-mono text-[13px]">
    <div v-if="loading" class="space-y-2">
      <div v-for="i in 6" :key="i" class="skeleton h-5" :style="{ width: `${50 + ((i * 19) % 45)}%` }"></div>
    </div>

    <div v-else-if="error && !steps.length" class="flex items-start gap-2 rounded-lg border border-run/30 bg-run/10 p-3 text-sm text-run">
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      {{ error }}
    </div>

    <template v-else-if="steps.length">
      <div class="mb-3 flex flex-wrap items-center gap-3 border-b border-edge pb-2.5">
        <StatusBadge :status="testStatus" />
        <span v-if="totalDuration !== null && totalDuration !== undefined" class="inline-flex items-center gap-1 text-xs text-ink-mute">
          <Clock class="h-3.5 w-3.5" />
          {{ formatStepDuration(totalDuration) }}
        </span>
        <span class="text-xs text-ink-faint">{{ steps.length }} top-level steps</span>
      </div>

      <div>
        <StepNode v-for="(step, index) in steps" :key="index" :step="step" :depth="0" />
      </div>

      <div v-if="errorMessage" class="mt-3 rounded-lg border border-fail/30 bg-fail/10 p-3">
        <div class="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-fail">
          <XCircle class="h-3.5 w-3.5" />
          Error Details
        </div>
        <pre class="overflow-x-auto text-xs whitespace-pre-wrap break-words text-fail">{{ errorMessage }}</pre>
      </div>
    </template>

    <EmptyState v-else title="No step data available" message="Allure step details were not found for this result." />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { AlertTriangle, Clock, XCircle } from 'lucide-vue-next';
import { api } from '../../../api/client';
import StatusBadge from '../../../components/ui/StatusBadge.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import StepNode from './StepNode.vue';
import { formatStepDuration } from '../utils/format';

const props = defineProps({
  apiUrl: { type: String, required: true },
});

const emit = defineEmits(['loaded']);

const loading = ref(true);
const error = ref(null);
const testStatus = ref('');
const totalDuration = ref(null);
const steps = ref([]);
const errorMessage = ref(null);

onMounted(async () => {
  try {
    const data = await api.get(props.apiUrl);
    testStatus.value = data.status || '';
    totalDuration.value = data.duration;
    steps.value = data.steps || [];
    errorMessage.value = data.error || null;

    if (data.error && !data.steps?.length) {
      error.value = data.error;
    }

    emit('loaded', { testName: data.testName, status: data.status, duration: data.duration });
  } catch (e) {
    error.value = `Failed to load steps: ${e.message}`;
  } finally {
    loading.value = false;
  }
});
</script>
