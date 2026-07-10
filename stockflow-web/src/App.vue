<script setup lang="ts">
import { onMounted, ref } from 'vue'
import api from '@/services/api'

interface HelloResponse {
  success: boolean
  message: string
  application: string
}

const loading = ref(true)
const errorMessage = ref('')
const apiMessage = ref('')
const applicationName = ref('StockFlow')

async function loadApiMessage() {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get<HelloResponse>('/hello')

    apiMessage.value = response.data.message
    applicationName.value = response.data.application
  } catch (error) {
    console.error(error)

    errorMessage.value =
      'Gagal terhubung ke Laravel API. Pastikan Laravel sedang berjalan.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadApiMessage()
})
</script>

<template>
  <main class="connection-page">
    <section class="connection-card">
      <p class="badge">Frontend Vue SPA</p>

      <h1>{{ applicationName }}</h1>

      <p v-if="loading" class="status">
        Sedang menghubungkan ke Laravel API...
      </p>

      <p v-else-if="errorMessage" class="error">
        {{ errorMessage }}
      </p>

      <div v-else class="success">
        <p>{{ apiMessage }}</p>
        <small>Vue berhasil menerima JSON dari Laravel.</small>
      </div>
    </section>
  </main>
</template>

<style scoped>
.connection-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background: #f3f4f6;
  font-family:
    Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.connection-card {
  width: min(100%, 520px);
  padding: 40px;
  border: 1px solid #e5e7eb;
  border-radius: 20px;
  background: white;
  text-align: center;
  box-shadow: 0 20px 50px rgb(15 23 42 / 8%);
}

.badge {
  display: inline-block;
  margin: 0 0 16px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #047857;
  font-size: 14px;
  font-weight: 600;
}

h1 {
  margin: 0 0 16px;
  font-size: 36px;
  color: #111827;
}

.status {
  color: #4b5563;
}

.success {
  padding: 16px;
  border-radius: 12px;
  background: #ecfdf5;
  color: #065f46;
}

.success p {
  margin: 0 0 6px;
  font-weight: 700;
}

.success small {
  color: #047857;
}

.error {
  padding: 16px;
  border-radius: 12px;
  background: #fef2f2;
  color: #b91c1c;
}
</style>