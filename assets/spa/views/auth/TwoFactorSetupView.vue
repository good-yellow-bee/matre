<template>
  <AuthLayout>
    <div class="flex items-center gap-3">
      <div class="grid h-10 w-10 place-items-center rounded-lg bg-run/10">
        <ShieldAlert class="h-5 w-5 text-run" />
      </div>
      <div>
        <h1 class="text-xl font-bold text-ink">Set up two-factor auth</h1>
        <p class="text-sm text-ink-mute">
          {{ auth.requires2faSetup ? 'Your administrator requires 2FA for all accounts' : 'Protect your account with TOTP codes' }}
        </p>
      </div>
    </div>

    <div v-if="error" class="mt-4 flex items-start gap-2 rounded-lg border border-fail/30 bg-fail/10 p-3 text-sm text-fail">
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      {{ error }}
    </div>

    <div v-if="loading" class="mt-6 space-y-3">
      <div class="skeleton mx-auto h-44 w-44"></div>
      <div class="skeleton h-4 w-3/4"></div>
    </div>

    <template v-else-if="setup">
      <div class="mt-6 flex flex-col items-center gap-3">
        <div class="rounded-xl border border-edge bg-white p-3">
          <img :src="setup.qrCode" alt="TOTP QR code" class="h-44 w-44">
        </div>
        <div class="text-center">
          <div class="text-xs text-ink-faint">Can't scan? Enter this secret manually:</div>
          <code class="mt-1 inline-block select-all rounded-md border border-edge bg-panel-2 px-2 py-1 font-mono text-xs text-accent">{{ setup.secret }}</code>
        </div>
      </div>

      <form class="mt-6 space-y-4" @submit.prevent="verify">
        <div>
          <label class="label" for="verify-code">Verification code</label>
          <input
            id="verify-code"
            v-model="code"
            class="input text-center font-mono text-2xl tracking-[0.5em]"
            type="text"
            inputmode="numeric"
            maxlength="6"
            placeholder="••••••"
            autocomplete="one-time-code"
            required
          >
        </div>
        <button class="btn-primary w-full" type="submit" :disabled="busy || code.length !== 6">
          <Loader2 v-if="busy" class="h-4 w-4 animate-spin" />
          Enable two-factor authentication
        </button>
        <button v-if="!auth.requires2faSetup" class="btn-ghost w-full" type="button" @click="router.push({ name: 'dashboard' })">
          Not now
        </button>
        <button v-else class="btn-ghost w-full" type="button" @click="signOut">Sign out</button>
      </form>
    </template>
  </AuthLayout>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { AlertCircle, Loader2, ShieldAlert } from 'lucide-vue-next';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { api } from '../../api/client';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toasts';

const auth = useAuthStore();
const router = useRouter();
const toasts = useToastStore();

const loading = ref(true);
const setup = ref(null);
const code = ref('');
const busy = ref(false);
const error = ref('');

watch(code, (value) => {
  code.value = value.replace(/\D/g, '');
});

onMounted(async () => {
  try {
    const result = await api.post('/api/2fa-setup');
    if (result.enabled) {
      router.replace({ name: 'dashboard' });
      return;
    }
    setup.value = result;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
});

async function verify() {
  busy.value = true;
  error.value = '';
  try {
    await api.post('/api/2fa-setup/verify', { code: code.value });
    await auth.bootstrap(true);
    toasts.success('Two-factor authentication enabled');
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.message;
    code.value = '';
  } finally {
    busy.value = false;
  }
}

async function signOut() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>
