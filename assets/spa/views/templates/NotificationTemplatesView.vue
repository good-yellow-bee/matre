<template>
  <div>
    <PageHeader
      title="Notification Templates"
      subtitle="Customize notification messages sent via Slack and Email when test runs complete. Use template variables to include dynamic content like test results and environment info."
    >
      <template #actions>
        <button class="btn-danger" @click="confirmResetOpen = true">
          <RotateCcw class="h-4 w-4" />
          Reset All to Defaults
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="space-y-5">
      <div v-for="i in 2" :key="i" class="card p-5">
        <div class="skeleton mb-4 h-5 w-44"></div>
        <div class="skeleton mb-2 h-9 w-full"></div>
        <div class="skeleton h-9 w-full"></div>
      </div>
    </div>

    <div v-else class="space-y-5">
      <section
        v-for="(channel, index) in channels"
        :key="channel.key"
        class="card rise overflow-hidden"
        :style="`--i: ${index + 1}`"
      >
        <header class="flex items-center gap-2.5 border-b border-edge px-5 py-3.5" :class="channel.headerClass">
          <span class="grid h-8 w-8 place-items-center rounded-lg" :class="channel.iconClass">
            <component :is="channel.icon" class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">{{ channel.label }} Templates</h2>
          <span class="ml-auto font-mono text-xs text-ink-faint">{{ grouped[channel.key]?.length || 0 }}</span>
        </header>

        <table v-if="grouped[channel.key]?.length" class="w-full">
          <thead class="border-b border-edge bg-panel-2/60">
            <tr>
              <th class="th-base w-2/5">Event</th>
              <th class="th-base w-1/5">Status</th>
              <th class="th-base w-1/5">Last Modified</th>
              <th class="th-base w-1/5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-edge">
            <tr v-for="template in grouped[channel.key]" :key="template.id" class="transition-colors hover:bg-panel-2/50">
              <td class="td-base">
                <span class="font-semibold">{{ template.nameLabel }}</span>
                <span
                  v-if="template.isDefault"
                  class="badge ml-2 border border-edge bg-panel-2 text-ink-faint"
                  title="System default template"
                >Default</span>
              </td>
              <td class="td-base">
                <span
                  class="badge border"
                  :class="template.isActive ? 'text-pass border-pass/25 bg-pass/10' : 'text-skip border-skip/25 bg-skip/10'"
                >
                  {{ template.isActive ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="td-base text-xs text-ink-mute">{{ formatDate(template.updatedAt || template.createdAt) }}</td>
              <td class="td-base">
                <div class="flex items-center justify-end gap-1">
                  <RouterLink
                    class="btn-ghost btn-sm"
                    title="Edit template"
                    :to="{ name: 'notification-template-edit', params: { id: template.id } }"
                  >
                    <Pencil class="h-3.5 w-3.5" />
                    Edit
                  </RouterLink>
                  <button
                    class="btn-ghost btn-sm"
                    :title="template.isActive ? 'Deactivate template' : 'Activate template'"
                    :disabled="togglingId === template.id"
                    @click="toggleTarget = template"
                  >
                    <Loader2 v-if="togglingId === template.id" class="h-3.5 w-3.5 animate-spin" />
                    <Pause v-else-if="template.isActive" class="h-3.5 w-3.5" />
                    <Play v-else class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="px-5 py-8 text-center text-sm text-ink-faint">No templates configured for this channel.</p>
      </section>

      <!-- Variables reference -->
      <section class="card rise p-5" style="--i: 3">
        <header class="mb-1 flex items-center gap-2.5">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent-soft text-accent">
            <Braces class="h-4 w-4" />
          </span>
          <h2 class="text-sm font-bold uppercase tracking-wider text-ink">Template Variables</h2>
        </header>
        <p class="mb-4 text-xs text-ink-mute">
          Use these variables in your templates with the <code v-pre class="font-mono text-accent">{{ variable_name }}</code> syntax:
        </p>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="variable in variables"
            :key="variable.name"
            class="flex items-baseline gap-2 rounded-md border border-edge bg-panel-2 px-2.5 py-1.5"
          >
            <code class="shrink-0 font-mono text-xs font-semibold text-accent">{{ variable.name }}</code>
            <span class="truncate text-[11px] text-ink-mute" :title="variable.description">{{ variable.description }}</span>
          </div>
        </div>
      </section>
    </div>

    <ConfirmDialog
      :open="confirmResetOpen"
      title="Reset All Templates"
      message="Reset all templates to defaults? This will overwrite any customizations you have made to subjects and bodies, and reactivate every template."
      confirm-label="Reset All"
      danger
      :busy="resetting"
      @confirm="resetDefaults"
      @cancel="confirmResetOpen = false"
    />

    <ConfirmDialog
      :open="!!toggleTarget"
      :title="toggleTarget?.isActive ? 'Deactivate Template' : 'Activate Template'"
      :message="toggleTarget?.isActive
        ? `Deactivate the “${toggleTarget?.nameLabel}” ${toggleTarget?.channel} template? Notifications using it will stop being sent.`
        : `Activate the “${toggleTarget?.nameLabel}” ${toggleTarget?.channel} template?`"
      :confirm-label="toggleTarget?.isActive ? 'Deactivate' : 'Activate'"
      :busy="togglingId !== null"
      @confirm="confirmToggle"
      @cancel="toggleTarget = null"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Braces, Loader2, Mail, Pause, Pencil, Play, RotateCcw, Slack } from 'lucide-vue-next';
import PageHeader from '../../components/ui/PageHeader.vue';
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';

const toasts = useToastStore();

const channels = [
  {
    key: 'slack',
    label: 'Slack',
    icon: Slack,
    headerClass: 'bg-broken/5',
    iconClass: 'bg-broken/10 text-broken',
  },
  {
    key: 'email',
    label: 'Email',
    icon: Mail,
    headerClass: 'bg-accent-soft',
    iconClass: 'bg-accent-soft text-accent',
  },
];

const templates = ref([]);
const variables = ref([]);
const loading = ref(true);
const togglingId = ref(null);
const toggleTarget = ref(null);
const confirmResetOpen = ref(false);
const resetting = ref(false);

const grouped = computed(() => {
  const groups = {};
  for (const template of templates.value) {
    (groups[template.channel] ??= []).push(template);
  }
  return groups;
});

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

async function fetchAll() {
  loading.value = true;
  try {
    const [list, vars] = await Promise.all([
      api.get('/api/notification-templates'),
      api.get('/api/notification-templates/variables'),
    ]);
    templates.value = list.data;
    variables.value = vars;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

async function confirmToggle() {
  const template = toggleTarget.value;
  togglingId.value = template.id;
  try {
    const data = await api.post(`/api/notification-templates/${template.id}/toggle-active`);
    template.isActive = data.isActive;
    toasts.success(data.message);
    toggleTarget.value = null;
  } catch (e) {
    toasts.error(e.message);
  } finally {
    togglingId.value = null;
  }
}

async function resetDefaults() {
  resetting.value = true;
  try {
    const data = await api.post('/api/notification-templates/reset-defaults');
    toasts.success(data.message);
    confirmResetOpen.value = false;
    await fetchAll();
  } catch (e) {
    toasts.error(e.message);
  } finally {
    resetting.value = false;
  }
}

onMounted(fetchAll);
</script>
