<template>
  <div v-if="password" class="mt-2">
    <div class="h-1.5 overflow-hidden rounded-full bg-panel-2">
      <div
        class="h-full rounded-full transition-all duration-300"
        :class="info.barClass"
        :style="{ width: `${(info.strength / 7) * 100}%` }"
      ></div>
    </div>
    <div class="mt-1.5 flex items-center gap-2">
      <span class="badge border" :class="info.badgeClass">{{ info.label }}</span>
      <span class="text-xs text-ink-faint">{{ message }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  password: { type: String, default: '' },
});

const info = computed(() => {
  if (!props.password) return { strength: 0, label: '', barClass: '', badgeClass: '' };

  let strength = 0;
  if (props.password.length >= 6) strength += 1;
  if (props.password.length >= 10) strength += 1;
  if (props.password.length >= 14) strength += 1;
  if (/[a-z]/.test(props.password)) strength += 1;
  if (/[A-Z]/.test(props.password)) strength += 1;
  if (/[0-9]/.test(props.password)) strength += 1;
  if (/[^a-zA-Z0-9]/.test(props.password)) strength += 1;

  if (strength <= 2) return { strength, label: 'Weak', barClass: 'bg-fail', badgeClass: 'border-fail/25 bg-fail/10 text-fail' };
  if (strength <= 4) return { strength, label: 'Fair', barClass: 'bg-run', badgeClass: 'border-run/25 bg-run/10 text-run' };
  if (strength <= 5) return { strength, label: 'Good', barClass: 'bg-accent', badgeClass: 'border-accent/25 bg-accent-soft text-accent' };
  return { strength, label: 'Strong', barClass: 'bg-pass', badgeClass: 'border-pass/25 bg-pass/10 text-pass' };
});

const message = computed(() => {
  const missing = [];
  if (props.password.length < 10) missing.push('longer length');
  if (!/[a-z]/.test(props.password)) missing.push('lowercase');
  if (!/[A-Z]/.test(props.password)) missing.push('uppercase');
  if (!/[0-9]/.test(props.password)) missing.push('numbers');
  if (!/[^a-zA-Z0-9]/.test(props.password)) missing.push('symbols');

  if (missing.length === 0) return 'Great password!';
  if (missing.length === 1) return `Add ${missing[0]} for stronger password`;
  return `Consider adding ${missing.slice(0, 2).join(' and ')}`;
});
</script>
