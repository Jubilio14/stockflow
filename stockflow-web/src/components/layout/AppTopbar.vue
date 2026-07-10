<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

defineEmits<{
  'toggle-sidebar': []
}>()

const route = useRoute()
const authStore = useAuthStore()

const pageTitle = computed(() => {
  return typeof route.meta.title === 'string'
    ? route.meta.title
    : 'StockFlow'
})
</script>

<template>
  <header class="app-topbar">
    <div class="topbar-left">
      <button
        type="button"
        class="menu-button"
        aria-label="Buka sidebar"
        @click="$emit('toggle-sidebar')"
      >
        ☰
      </button>

      <div>
        <p>StockFlow</p>
        <h1>{{ pageTitle }}</h1>
      </div>
    </div>

    <div class="topbar-user">
      <div>
        <strong>{{ authStore.user?.name }}</strong>
        <span>{{ authStore.user?.email }}</span>
      </div>

      <div class="topbar-avatar">
        {{
          authStore.user?.name
            .charAt(0)
            .toUpperCase() ?? 'U'
        }}
      </div>
    </div>
  </header>
</template>

<style scoped>
.app-topbar {
  position: sticky;
  z-index: 20;
  top: 0;

  min-height: 78px;

  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;

  padding: 14px 28px;

  border-bottom: 1px solid #e2e8f0;
  background: rgb(255 255 255 / 92%);
  backdrop-filter: blur(12px);
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.topbar-left p {
  margin: 0 0 3px;
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.topbar-left h1 {
  margin: 0;
  color: #0f172a;
  font-size: 21px;
}

.menu-button {
  width: 42px;
  height: 42px;

  display: none;
  place-items: center;

  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 10px;

  background: white;
  color: #334155;

  font-size: 20px;
}

.topbar-user {
  display: flex;
  align-items: center;
  gap: 11px;
}

.topbar-user strong,
.topbar-user span {
  display: block;
  text-align: right;
}

.topbar-user strong {
  color: #334155;
  font-size: 13px;
}

.topbar-user span {
  margin-top: 3px;
  color: #64748b;
  font-size: 11px;
}

.topbar-avatar {
  width: 40px;
  height: 40px;

  display: grid;
  place-items: center;

  border-radius: 12px;
  background: #d1fae5;
  color: #047857;

  font-weight: 800;
}

@media (max-width: 900px) {
  .app-topbar {
    padding: 14px 18px;
  }

  .menu-button {
    display: grid;
  }
}

@media (max-width: 560px) {
  .topbar-user > div:first-child {
    display: none;
  }
}
</style>