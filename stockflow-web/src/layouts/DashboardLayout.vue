<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  RouterView,
  useRoute,
} from 'vue-router'

import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTopbar from '@/components/layout/AppTopbar.vue'

const route = useRoute()
const isSidebarOpen = ref(false)

function openSidebar(): void {
  isSidebarOpen.value = true
}

function closeSidebar(): void {
  isSidebarOpen.value = false
}

watch(
  () => route.fullPath,
  () => {
    closeSidebar()
  },
)
</script>

<template>
  <div class="dashboard-layout">
    <AppSidebar
      :is-open="isSidebarOpen"
      @close="closeSidebar"
    />

    <div
      v-if="isSidebarOpen"
      class="sidebar-overlay"
      @click="closeSidebar"
    ></div>

    <div class="dashboard-main">
      <AppTopbar
        @toggle-sidebar="openSidebar"
      />

      <main class="dashboard-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.dashboard-layout {
  min-height: 100vh;
  background: #f1f5f9;
}

.dashboard-main {
  min-height: 100vh;
  margin-left: 260px;
}

.dashboard-content {
  padding: 28px;
}

.sidebar-overlay {
  position: fixed;
  z-index: 40;
  inset: 0;

  background: rgb(15 23 42 / 55%);
  backdrop-filter: blur(2px);
}

@media (max-width: 900px) {
  .dashboard-main {
    margin-left: 0;
  }

  .dashboard-content {
    padding: 20px 16px;
  }
}
</style>