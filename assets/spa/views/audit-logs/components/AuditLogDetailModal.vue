<template>
  <Modal :open="open" title="Audit Log Detail" size="xl" @close="emit('close')">
    <div v-if="loading" class="space-y-4">
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div v-for="i in 4" :key="i" class="skeleton h-10"></div>
      </div>
      <div class="skeleton h-8 w-1/2"></div>
      <div class="grid gap-4 md:grid-cols-2">
        <div class="skeleton h-48"></div>
        <div class="skeleton h-48"></div>
      </div>
    </div>

    <template v-else-if="log">
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div>
          <div class="label">Timestamp</div>
          <div class="font-mono text-[13px] text-ink">{{ timestamp }}</div>
        </div>
        <div>
          <div class="label">User</div>
          <div class="text-sm text-ink">
            <template v-if="log.user">{{ log.user.username }}</template>
            <span v-else class="italic text-ink-faint">System</span>
          </div>
        </div>
        <div>
          <div class="label">IP Address</div>
          <div class="font-mono text-[13px] text-ink">{{ log.ipAddress || '—' }}</div>
        </div>
        <div>
          <div class="label">Action</div>
          <span class="badge border" :class="actionClass">{{ log.action }}</span>
        </div>
      </div>

      <div class="mt-4">
        <div class="label">Entity</div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="badge border border-edge-2 bg-panel-2 text-ink-mute">{{ log.entityType }}</span>
          <span v-if="log.entityLabel" class="text-sm font-medium text-ink">{{ log.entityLabel }}</span>
          <span class="mono-id text-ink-faint">#{{ log.entityId }}</span>
        </div>
      </div>

      <div v-if="log.changedFields?.length" class="mt-4">
        <div class="label">Changed Fields</div>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="field in log.changedFields" :key="field" class="badge border border-run/25 bg-run/10 text-run">
            {{ field }}
          </span>
        </div>
      </div>

      <div v-if="log.oldData || log.newData" class="mt-5 grid items-start gap-4 md:grid-cols-2">
        <div v-if="log.oldData" class="overflow-hidden rounded-lg border border-fail/25" :class="!log.newData && 'md:col-span-2'">
          <div class="flex items-center gap-1.5 border-b border-fail/25 bg-fail/10 px-3 py-2 text-[11px] font-bold uppercase tracking-widest text-fail">
            <CircleMinus class="h-3.5 w-3.5" />
            {{ log.action === 'delete' ? 'Deleted Data' : 'Old Values' }}
          </div>
          <pre class="max-h-80 overflow-auto bg-panel-2 p-3 font-mono text-xs leading-relaxed"><span
            v-for="(line, index) in oldLines"
            :key="index"
            class="block"
            :class="line.changed ? 'bg-run/15 text-ink' : 'text-ink-mute'"
          >{{ line.text }}</span></pre>
        </div>
        <div v-if="log.newData" class="overflow-hidden rounded-lg border border-pass/25" :class="!log.oldData && 'md:col-span-2'">
          <div class="flex items-center gap-1.5 border-b border-pass/25 bg-pass/10 px-3 py-2 text-[11px] font-bold uppercase tracking-widest text-pass">
            <CirclePlus class="h-3.5 w-3.5" />
            {{ log.action === 'create' ? 'Created Data' : 'New Values' }}
          </div>
          <pre class="max-h-80 overflow-auto bg-panel-2 p-3 font-mono text-xs leading-relaxed"><span
            v-for="(line, index) in newLines"
            :key="index"
            class="block"
            :class="line.changed ? 'bg-run/15 text-ink' : 'text-ink-mute'"
          >{{ line.text }}</span></pre>
        </div>
      </div>
      <p v-else class="mt-5 text-sm text-ink-faint">No data snapshot recorded for this entry.</p>
    </template>
  </Modal>
</template>

<script setup>
import { computed } from 'vue';
import { CircleMinus, CirclePlus } from 'lucide-vue-next';
import Modal from '../../../components/ui/Modal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  log: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const ACTION_CLASSES = {
  create: 'border-pass/25 bg-pass/10 text-pass',
  update: 'border-run/25 bg-run/10 text-run',
  delete: 'border-fail/25 bg-fail/10 text-fail',
};

const actionClass = computed(() => ACTION_CLASSES[props.log?.action] || 'border-edge bg-panel-2 text-ink-mute');

const timestamp = computed(() => {
  if (!props.log) return '';
  const date = new Date(props.log.createdAt);
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
});

function jsonLines(data) {
  if (!data) return [];
  const changed = new Set(props.log?.changedFields || []);
  return JSON.stringify(data, null, 2)
    .split('\n')
    .map((text) => {
      const match = text.match(/^\s*"([^"]+)":/);
      return { text, changed: match ? changed.has(match[1]) : false };
    });
}

const oldLines = computed(() => jsonLines(props.log?.oldData));
const newLines = computed(() => jsonLines(props.log?.newData));
</script>
