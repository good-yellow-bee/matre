<template>
  <div>
    <PageHeader title="Settings" subtitle="Global configuration for the MATRE console" />

    <div v-if="loading" class="max-w-3xl space-y-5">
      <div v-for="i in 3" :key="i" class="card p-5">
        <div class="skeleton mb-4 h-4 w-40"></div>
        <div class="skeleton mb-2 h-9 w-full"></div>
        <div class="skeleton h-9 w-2/3"></div>
      </div>
    </div>

    <form v-else class="max-w-3xl space-y-5" novalidate @submit.prevent="save">
      <!-- General -->
      <section class="card rise p-5" style="--i: 0">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <SlidersHorizontal class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">General</h2>
        </header>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="site-name">Site Name</label>
            <input id="site-name" v-model="form.siteName" class="input" type="text" maxlength="255" placeholder="My Website">
            <p v-if="errors.siteName" class="field-error">{{ errors.siteName }}</p>
            <p v-else class="mt-1 text-xs text-ink-faint">The name of your website (used in page titles)</p>
          </div>
          <div>
            <label class="label" for="admin-panel-title">Admin Panel Title</label>
            <input id="admin-panel-title" v-model="form.adminPanelTitle" class="input" type="text" maxlength="255" placeholder="My Admin Panel">
            <p v-if="errors.adminPanelTitle" class="field-error">{{ errors.adminPanelTitle }}</p>
            <p v-else class="mt-1 text-xs text-ink-faint">Title displayed in the admin sidebar</p>
          </div>
        </div>
        <div class="mt-4 sm:w-1/2 sm:pr-2">
          <label class="label" for="default-locale">Default Language</label>
          <select id="default-locale" v-model="form.defaultLocale" class="input">
            <option v-for="locale in locales" :key="locale.value" :value="locale.value">{{ locale.label }}</option>
          </select>
          <p v-if="errors.defaultLocale" class="field-error">{{ errors.defaultLocale }}</p>
          <p v-else class="mt-1 text-xs text-ink-faint">Default language for the site</p>
        </div>
      </section>

      <!-- SEO & Meta -->
      <section class="card rise p-5" style="--i: 1">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <Search class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">SEO &amp; Meta</h2>
        </header>
        <div class="space-y-4">
          <div>
            <label class="label" for="seo-description">SEO Description</label>
            <textarea
              id="seo-description"
              v-model="form.seoDescription"
              class="input resize-y"
              rows="3"
              maxlength="500"
              placeholder="Description for search engines"
            ></textarea>
            <p v-if="errors.seoDescription" class="field-error">{{ errors.seoDescription }}</p>
            <p v-else class="mt-1 text-xs text-ink-faint">Meta description for SEO (max 500 characters)</p>
          </div>
          <div>
            <label class="label" for="seo-keywords">SEO Keywords</label>
            <textarea
              id="seo-keywords"
              v-model="form.seoKeywords"
              class="input resize-y"
              rows="2"
              maxlength="255"
              placeholder="keyword1, keyword2, keyword3"
            ></textarea>
            <p v-if="errors.seoKeywords" class="field-error">{{ errors.seoKeywords }}</p>
            <p v-else class="mt-1 text-xs text-ink-faint">Comma-separated keywords for SEO</p>
          </div>
        </div>
      </section>

      <!-- Mode -->
      <section class="card rise p-5" style="--i: 2">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <Terminal class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Mode</h2>
        </header>
        <Toggle
          v-model="form.headlessMode"
          label="Headless Mode"
          help="Enable to disable frontend URLs (API and admin only)"
        />
        <p v-if="errors.headlessMode" class="field-error">{{ errors.headlessMode }}</p>
      </section>

      <!-- Test Execution -->
      <section class="card rise p-5" style="--i: 3">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <FlaskConical class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Test Execution</h2>
        </header>
        <Toggle
          v-model="form.autoReportForIndividualRuns"
          label="Auto-generate Allure report for individual test runs"
          help="When disabled, Allure reports are only auto-generated for suite runs. Use app:report:generate to create reports manually."
        />
        <div class="mt-4 sm:w-1/2 sm:pr-2">
          <label class="label" for="max-retry-count">Auto-Retry Failed Tests</label>
          <select id="max-retry-count" v-model.number="form.maxRetryCount" class="input">
            <option :value="0">Disabled</option>
            <option v-for="n in 5" :key="n" :value="n">{{ n }} {{ n === 1 ? 'retry' : 'retries' }}</option>
          </select>
          <p v-if="errors.maxRetryCount" class="field-error">{{ errors.maxRetryCount }}</p>
        </div>
        <div class="mt-4 flex items-start gap-2 rounded-lg border border-accent/25 bg-accent-soft p-3 text-xs text-ink-mute">
          <Info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-accent" />
          <span>
            When enabled, tests that fail due to infrastructure errors (WebDriver timeouts, element interactability,
            stale elements) will be automatically retried. Genuine assertion failures are never retried.
          </span>
        </div>
      </section>

      <!-- Security -->
      <section class="card rise p-5" style="--i: 4">
        <header class="mb-4 flex items-center gap-2.5 border-b border-edge pb-3">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <Shield class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Security</h2>
        </header>
        <Toggle
          v-model="form.enforce2fa"
          label="Enforce Two-Factor Authentication"
          help="Require all users to configure 2FA before using the application"
        />
        <p v-if="errors.enforce2fa" class="field-error">{{ errors.enforce2fa }}</p>
        <div class="mt-4 flex items-start gap-2 rounded-lg border border-accent/25 bg-accent-soft p-3 text-xs text-ink-mute">
          <Info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-accent" />
          <span>When enabled, users without 2FA configured will be redirected to set it up on their next login.</span>
        </div>
      </section>

      <div class="rise flex flex-wrap items-center justify-between gap-3" style="--i: 5">
        <p class="font-mono text-xs text-ink-faint">
          {{ updatedAt ? `Settings last updated: ${formatDate(updatedAt)}` : 'Settings never updated' }}
        </p>
        <button class="btn-primary" type="submit" :disabled="saving">
          <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
          <Save v-else class="h-4 w-4" />
          Save Settings
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { FlaskConical, Info, Loader2, Save, Search, Shield, SlidersHorizontal, Terminal } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import Toggle from './components/Toggle.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { useAuthStore } from '../../stores/auth';

const toasts = useToastStore();
const auth = useAuthStore();

const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const updatedAt = ref(null);

const form = ref({
  siteName: '',
  adminPanelTitle: '',
  seoDescription: '',
  seoKeywords: '',
  defaultLocale: 'en',
  headlessMode: false,
  autoReportForIndividualRuns: false,
  enforce2fa: false,
  maxRetryCount: 0,
});

const locales = [
  { value: 'en', label: 'English' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'es', label: 'Spanish' },
  { value: 'it', label: 'Italian' },
];

function formatDate(iso) {
  return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

async function load() {
  const data = await api.get('/api/settings');
  updatedAt.value = data.updatedAt;
  form.value = {
    siteName: data.siteName ?? '',
    adminPanelTitle: data.adminPanelTitle ?? '',
    seoDescription: data.seoDescription ?? '',
    seoKeywords: data.seoKeywords ?? '',
    defaultLocale: data.defaultLocale ?? 'en',
    headlessMode: !!data.headlessMode,
    autoReportForIndividualRuns: !!data.autoReportForIndividualRuns,
    enforce2fa: !!data.enforce2fa,
    maxRetryCount: data.maxRetryCount ?? 0,
  };
}

async function save() {
  saving.value = true;
  errors.value = {};
  try {
    await api.put('/api/settings', form.value);
    toasts.success('Settings updated successfully');
    await Promise.all([auth.bootstrap(true), load()]);
  } catch (e) {
    if (e.status === 422 && e.payload?.errors) {
      errors.value = e.payload.errors;
      toasts.error('Please fix the highlighted fields');
    } else {
      toasts.error(e.message);
    }
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    await load();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    loading.value = false;
  }
});
</script>
