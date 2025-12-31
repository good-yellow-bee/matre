<template>
  <div v-if="!isHidden" class="step-node" :class="{ 'has-children': hasChildren }">
    <!-- Step row -->
    <div
      class="step-row d-flex align-items-center gap-2 py-1 pe-2 rounded"
      :class="[stepRowClass, stepTypeClass]"
      :style="{ paddingLeft: `${depth * 20 + 8}px` }"
    >
      <!-- Expand/collapse toggle -->
      <button
        v-if="hasChildren"
        type="button"
        class="btn btn-link btn-sm p-0 text-muted expand-toggle"
        @click="expanded = !expanded"
        :aria-expanded="expanded"
      >
        <i :class="['bi', expanded ? 'bi-chevron-down' : 'bi-chevron-right']"></i>
      </button>
      <span v-else class="expand-placeholder"></span>

      <!-- Type-aware icon -->
      <span class="step-icon" :style="{ color: stepIconColor }" :title="stepType">
        <i :class="stepIcon"></i>
      </span>

      <!-- Step name -->
      <span class="step-name flex-grow-1" :class="stepNameClass" :title="step.name">
        {{ step.name }}
      </span>

      <!-- Children count badge -->
      <span v-if="hasChildren" class="badge bg-secondary-subtle text-secondary small">
        {{ visibleChildrenCount }}
      </span>

      <!-- Duration -->
      <span v-if="step.duration !== null" class="step-duration text-muted small">
        {{ formatDuration(step.duration) }}
      </span>
    </div>

    <!-- Children (recursive) -->
    <div v-if="hasChildren && expanded" class="step-children">
      <StepNode
        v-for="(child, index) in step.children"
        :key="index"
        :step="child"
        :depth="depth + 1"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  step: {
    type: Object,
    required: true,
  },
  depth: {
    type: Number,
    default: 0,
  },
});

const expanded = ref(false);

const name = computed(() => props.step.name?.toLowerCase() || '');

// Detect step type
const stepType = computed(() => {
  const n = name.value;

  // Comments/annotations (csv step, numbered steps)
  if (/^(csv step|\d+\.|step \d+|#|\/\/|comment)/i.test(n)) {
    return 'comment';
  }

  // Action groups
  if (/action\s*group|actiongroup/i.test(n) ||
      (n.startsWith('[') && n.includes(']') && /action\s*group/i.test(n))) {
    return 'actionGroup';
  }

  // Regular action steps
  return 'action';
});

// Hide certain steps (browser logs, JS errors that are noise)
const isHidden = computed(() => {
  const n = name.value;
  // Hide browser console logs/errors (these are attachments, not real steps)
  if (/^browser\s*(log|error|warning|console)/i.test(n)) return true;
  if (/^(console|javascript)\s*(error|warning|log)/i.test(n)) return true;
  // Hide attachment references
  if (/^attachment:/i.test(n)) return true;
  return false;
});

const hasChildren = computed(() => {
  const children = props.step.children || [];
  // Only count non-hidden children
  return children.some(c => !isChildHidden(c));
});

const visibleChildrenCount = computed(() => {
  const children = props.step.children || [];
  return children.filter(c => !isChildHidden(c)).length;
});

const isChildHidden = (child) => {
  const n = (child.name || '').toLowerCase();
  if (/^browser\s*(log|error|warning|console)/i.test(n)) return true;
  if (/^(console|javascript)\s*(error|warning|log)/i.test(n)) return true;
  if (/^attachment:/i.test(n)) return true;
  return false;
};

// Icons based on step type and status
const stepIcon = computed(() => {
  const status = props.step.status?.toLowerCase();
  const type = stepType.value;

  // Failed/broken always show status icon
  if (status === 'failed') return 'bi-x-circle-fill';
  if (status === 'broken') return 'bi-exclamation-triangle-fill';
  if (status === 'skipped') return 'bi-dash-circle-fill';

  // Comments are labels/annotations, not executions - use text icon
  if (type === 'comment') return 'bi-card-text';

  // Action groups and individual steps that passed - green checkmark
  return 'bi-check-circle-fill';
});

const stepIconColor = computed(() => {
  const status = props.step.status?.toLowerCase();
  const type = stepType.value;

  if (status === 'failed') return '#dc3545';    // Bootstrap danger
  if (status === 'broken') return '#ffc107';    // Bootstrap warning
  if (status === 'skipped') return '#6c757d';   // Bootstrap secondary

  // Comments: light green (section header, not an execution)
  if (type === 'comment') return '#5cb85c';

  // Action groups and individual steps: green (executed successfully)
  return '#198754';  // Bootstrap success
});

const stepTypeClass = computed(() => {
  return `step-type-${stepType.value}`;
});

const stepNameClass = computed(() => {
  const type = stepType.value;
  if (type === 'comment') return 'step-name-comment';
  if (type === 'actionGroup') return 'step-name-group';
  return 'step-name-action';
});

const stepRowClass = computed(() => {
  const status = props.step.status?.toLowerCase();
  if (status === 'failed') return 'bg-danger-subtle';
  if (status === 'broken') return 'bg-warning-subtle';
  return '';
});

const formatDuration = (seconds) => {
  if (seconds === null || seconds === undefined) return '';
  if (seconds < 0.001) return '<1ms';
  if (seconds < 1) return `${Math.round(seconds * 1000)}ms`;
  if (seconds < 60) return `${seconds.toFixed(2)}s`;
  const mins = Math.floor(seconds / 60);
  const secs = Math.round(seconds % 60);
  return `${mins}m ${secs}s`;
};
</script>

<style scoped>
.step-node {
  font-size: 0.82rem;
}

.step-row {
  min-height: 28px;
  transition: background-color 0.15s;
}

.step-row:hover {
  background-color: rgba(0, 0, 0, 0.04);
}

.expand-toggle {
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  flex-shrink: 0;
}

.expand-toggle:hover {
  color: var(--bs-primary) !important;
}

.expand-placeholder {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.step-icon {
  font-size: 0.7rem;
  width: 16px;
  text-align: center;
  flex-shrink: 0;
}

.step-name {
  word-break: break-word;
}

/* Comment steps - bold, like section headers */
.step-name-comment {
  font-weight: 600;
  color: var(--bs-body-color);
}

/* Action group steps - semi-bold */
.step-name-group {
  font-weight: 500;
  color: var(--bs-body-color);
}

/* Regular action steps - normal weight, slightly muted */
.step-name-action {
  color: var(--bs-secondary-color);
}

.step-duration {
  font-size: 0.75rem;
  min-width: 55px;
  text-align: right;
  flex-shrink: 0;
}

.step-children {
  border-left: 1px dashed var(--bs-border-color);
  margin-left: 17px;
}

/* Type-specific row styling */
.step-type-comment {
  background-color: rgba(var(--bs-success-rgb), 0.03);
}

.step-type-actionGroup {
  background-color: rgba(var(--bs-success-rgb), 0.02);
}

</style>
