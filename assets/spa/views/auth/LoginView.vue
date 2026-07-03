<template>
  <AuthLayout>
    <h1 class="text-xl font-bold text-ink">Welcome back</h1>
    <p class="mt-1 text-sm text-ink-mute">Sign in to your test automation console</p>

    <div v-if="error" class="mt-4 flex items-start gap-2 rounded-lg border border-fail/30 bg-fail/10 p-3 text-sm text-fail">
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
      {{ error }}
    </div>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
      <div>
        <label class="label" for="username">Username</label>
        <input
          id="username"
          v-model="username"
          class="input"
          type="text"
          autocomplete="username"
          required
          autofocus
        >
      </div>
      <div>
        <label class="label" for="password">Password</label>
        <input
          id="password"
          v-model="password"
          class="input"
          type="password"
          autocomplete="current-password"
          required
        >
      </div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-mute">
        <input v-model="rememberMe" type="checkbox" class="h-4 w-4 rounded border-edge accent-(--m-accent)">
        Remember me for a week
      </label>
      <button class="btn-primary w-full" type="submit" :disabled="busy">
        <Loader2 v-if="busy" class="h-4 w-4 animate-spin" />
        <LogIn v-else class="h-4 w-4" />
        Sign in
      </button>
    </form>

    <template #footer>{{ year }} · MATRE — Magento Automated Test Run Environment</template>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertCircle, Loader2, LogIn } from 'lucide-vue-next';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const username = ref('');
const password = ref('');
const rememberMe = ref(false);
const busy = ref(false);
const error = ref('');
const year = new Date().getFullYear();

async function submit() {
  busy.value = true;
  error.value = '';
  try {
    const result = await auth.login(username.value, password.value, rememberMe.value);
    if (result.twoFactorRequired) {
      router.push({ name: 'two-factor' });
      return;
    }
    router.push(typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'dashboard' });
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>
