<script setup lang="ts">
import { computed } from 'vue'

import CashierDashboardView from
  '@/views/dashboard/CashierDashboardView.vue'

import OwnerDashboardView from
  '@/views/dashboard/OwnerDashboardView.vue'

import { useAuthStore } from
  '@/stores/auth'

const authStore = useAuthStore()

const userRole = computed(() => {
  return authStore.user?.role ?? ''
})

const userName = computed(() => {
  return (
    authStore.user?.name ??
    'Pengguna StockFlow'
  )
})

const isManagement = computed(() => {
  return [
    'owner',
    'admin',
  ].includes(userRole.value)
})
</script>

<template>
  <OwnerDashboardView
    v-if="isManagement"
  />

  <CashierDashboardView
    v-else
    :user-name="userName"
  />
</template>