<template>
  <AuthLayout>
    <div class="flex items-center gap-3">
      <div class="grid h-10 w-10 place-items-center rounded-lg bg-accent-soft">
        <ShieldCheck class="h-5 w-5 text-accent" />
      </div>
      <div>
        <h1 class="text-xl font-bold text-ink">Two-factor authentication</h1>
        <p class="text-sm text-ink-mute">Enter the 6-digit code from your authenticator app</p>
      </div>
    </div>

    <div v-if="error" class="mt-4 flex items-start gap-2 rounded-lg border border-fail/30 bg-fail/10 p-3 text-sm text-fail">
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      {{ error }}
    </div>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
      <input
        ref="codeInput"
        v-model="code"
        class="input text-center font-mono text-2xl tracking-[0.5em]"
        type="text"
        inputmode="numeric"
        pattern="[0-9]*"
        maxlength="6"
        placeholder="••••••"
        autocomplete="one-time-code"
        aria-label="6-digit authentication code"
        required
      >
      <button class="btn-primary w-full" type="submit" :disabled="busy || code.length !== 6">
        <Loader2 v-if="busy" class="h-4 w-4 animate-spin" />
        Verify
      </button>
      <button class="btn-ghost w-full" type="button" @click="cancel">Cancel and sign out</button>
    </form>
  </AuthLayout>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { AlertCircle, Loader2, ShieldCheck } from 'lucide-vue-next';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const code = ref('');
const busy = ref(false);
const error = ref('');
const codeInput = ref(null);

onMounted(() => codeInput.value?.focus());

watch(code, (value) => {
  code.value = value.replace(/\D/g, '');
});

async function submit() {
  busy.value = true;
  error.value = '';
  try {
    await auth.verify2fa(code.value);
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.message;
    code.value = '';
    codeInput.value?.focus();
  } finally {
    busy.value = false;
  }
}

async function cancel() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>
