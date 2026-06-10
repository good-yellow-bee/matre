<template>
  <div class="flex h-screen overflow-hidden bg-surface">
    <AppSidebar :collapsed="collapsed" />
    <div class="flex min-w-0 flex-1 flex-col">
      <AppTopbar :collapsed="collapsed" @toggle-sidebar="toggleSidebar" />
      <main class="relative flex-1 overflow-y-auto">
        <div class="glow-band pointer-events-none absolute inset-x-0 top-0 h-64"></div>
        <div class="relative mx-auto max-w-[1500px] px-6 py-6">
          <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import AppSidebar from '../components/shell/AppSidebar.vue';
import AppTopbar from '../components/shell/AppTopbar.vue';

const STORAGE_KEY = 'matre_sidebar_collapsed';
const collapsed = ref(localStorage.getItem(STORAGE_KEY) === 'true');

function toggleSidebar() {
  collapsed.value = !collapsed.value;
  localStorage.setItem(STORAGE_KEY, String(collapsed.value));
}
</script>
