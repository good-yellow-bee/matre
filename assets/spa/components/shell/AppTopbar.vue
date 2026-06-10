<template>
  <header class="flex h-14 shrink-0 items-center gap-3 border-b border-edge bg-panel px-4">
    <button
      class="cursor-pointer rounded-md p-2 text-ink-mute transition-colors hover:bg-panel-2 hover:text-ink"
      :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="emit('toggle-sidebar')"
    >
      <PanelLeft class="h-4.5 w-4.5" />
    </button>

    <nav class="flex min-w-0 items-center gap-2 text-sm">
      <span class="text-ink-faint">{{ route.meta.section || 'Admin' }}</span>
      <ChevronRight class="h-3.5 w-3.5 shrink-0 text-ink-faint" />
      <span class="truncate font-semibold text-ink">{{ route.meta.title || 'Dashboard' }}</span>
    </nav>

    <div class="ml-auto flex items-center gap-1.5">
      <span
        v-if="runningCount > 0"
        class="badge mr-1 text-run"
        title="Test runs in progress"
      >
        <span class="led led-pulse"></span>
        {{ runningCount }} running
      </span>
      <button
        class="cursor-pointer rounded-md p-2 text-ink-mute transition-colors hover:bg-panel-2 hover:text-ink"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
      >
        <Sun v-if="isDark" class="h-4.5 w-4.5" />
        <Moon v-else class="h-4.5 w-4.5" />
      </button>
    </div>
  </header>
</template>

<script setup>
import { ChevronRight, Moon, PanelLeft, Sun } from 'lucide-vue-next';
import { useRoute } from 'vue-router';
import { useTheme } from '../../composables/useTheme';
import { useRunningActivity } from '../../composables/useRunningActivity';

defineProps({ collapsed: { type: Boolean, default: false } });
const emit = defineEmits(['toggle-sidebar']);

const route = useRoute();
const { isDark, toggle } = useTheme();
const { runningCount } = useRunningActivity();
</script>
