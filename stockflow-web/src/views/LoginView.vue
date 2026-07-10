<script setup lang="ts">
import axios from 'axios'
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

interface ValidationErrors {
  email?: string[]
  password?: string[]
  remember?: string[]
}

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({
  email: 'owner@stockflow.test',
  password: 'Password123!',
  remember: false,
})

const errors = ref<ValidationErrors>({})
const generalError = ref('')

async function submitLogin(): Promise<void> {
  errors.value = {}
  generalError.value = ''

  try {
    await authStore.login(form)

    const redirectPath =
      typeof route.query.redirect === 'string'
        ? route.query.redirect
        : '/dashboard'

    await router.replace(redirectPath)
  } catch (error: unknown) {
    if (axios.isAxiosError(error)) {
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors ?? {}
        return
      }

      if (error.response?.status === 419) {
        generalError.value =
          'Sesi keamanan sudah tidak valid. Silakan muat ulang halaman.'
        return
      }
    }

    generalError.value =
      'Login gagal. Pastikan Laravel berjalan dan coba kembali.'
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-panel">
      <div class="brand">
        <div class="brand-icon">SF</div>

        <div>
          <h1>StockFlow</h1>
          <p>Inventory & Point of Sale</p>
        </div>
      </div>

      <div class="heading">
        <p class="eyebrow">Welcome back</p>
        <h2>Masuk ke akun Anda</h2>
        <p>
          Kelola produk, stok, pembelian, dan transaksi toko dalam satu
          aplikasi.
        </p>
      </div>

      <div v-if="generalError" class="alert">
        {{ generalError }}
      </div>

      <form class="login-form" @submit.prevent="submitLogin">
        <div class="form-group">
          <label for="email">Email</label>

          <input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            placeholder="nama@stockflow.test"
          />

          <small v-if="errors.email" class="field-error">
            {{ errors.email[0] }}
          </small>
        </div>

        <div class="form-group">
          <div class="label-row">
            <label for="password">Password</label>
          </div>

          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            placeholder="Masukkan password"
          />

          <small v-if="errors.password" class="field-error">
            {{ errors.password[0] }}
          </small>
        </div>

        <label class="remember-option">
          <input v-model="form.remember" type="checkbox" />
          <span>Ingat saya</span>
        </label>

        <button
          type="submit"
          class="submit-button"
          :disabled="authStore.isLoading"
        >
          {{ authStore.isLoading ? 'Sedang masuk...' : 'Masuk' }}
        </button>
      </form>

      <p class="demo-info">
        Demo Owner:
        <strong>owner@stockflow.test</strong>
      </p>
    </section>

    <section class="visual-panel">
      <div class="visual-content">
        <span class="visual-badge">StockFlow Retail System</span>

        <h2>Kontrol stok dan penjualan dengan lebih akurat.</h2>

        <p>
          Pantau pergerakan barang, nilai persediaan, omzet, HPP, dan laba
          toko dari satu dashboard.
        </p>

        <div class="stat-preview">
          <div>
            <span>Stok tersedia</span>
            <strong>1.248 item</strong>
          </div>

          <div>
            <span>Penjualan hari ini</span>
            <strong>Rp4.850.000</strong>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(420px, 0.9fr) minmax(520px, 1.1fr);
  background: #f8fafc;
}

.login-panel {
  width: min(100%, 520px);
  margin: auto;
  padding: 48px;
}

.brand {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 72px;
}

.brand-icon {
  width: 46px;
  height: 46px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: #047857;
  color: white;
  font-weight: 800;
}

.brand h1,
.brand p {
  margin: 0;
}

.brand h1 {
  font-size: 20px;
}

.brand p {
  margin-top: 3px;
  color: #64748b;
  font-size: 13px;
}

.heading {
  margin-bottom: 30px;
}

.eyebrow {
  margin: 0 0 10px;
  color: #047857;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.heading h2 {
  margin: 0 0 12px;
  font-size: 32px;
  line-height: 1.2;
}

.heading > p:last-child {
  margin: 0;
  color: #64748b;
  line-height: 1.7;
}

.alert {
  margin-bottom: 20px;
  padding: 14px 16px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 14px;
}

.login-form {
  display: grid;
  gap: 20px;
}

.form-group {
  display: grid;
  gap: 8px;
}

.form-group label {
  color: #334155;
  font-size: 14px;
  font-weight: 650;
}

.form-group input {
  width: 100%;
  height: 48px;
  padding: 0 14px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  outline: none;
  background: white;
  color: #0f172a;
  transition:
    border-color 150ms ease,
    box-shadow 150ms ease;
}

.form-group input:focus {
  border-color: #059669;
  box-shadow: 0 0 0 4px rgb(5 150 105 / 12%);
}

.field-error {
  color: #dc2626;
  font-size: 13px;
}

.remember-option {
  display: flex;
  align-items: center;
  gap: 9px;
  color: #475569;
  font-size: 14px;
}

.remember-option input {
  width: 16px;
  height: 16px;
  accent-color: #047857;
}

.submit-button {
  height: 50px;
  border: 0;
  border-radius: 12px;
  background: #047857;
  color: white;
  font-weight: 700;
  transition:
    background 150ms ease,
    transform 150ms ease;
}

.submit-button:hover:not(:disabled) {
  background: #065f46;
  transform: translateY(-1px);
}

.submit-button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.demo-info {
  margin: 24px 0 0;
  color: #64748b;
  font-size: 13px;
  text-align: center;
}

.visual-panel {
  min-height: calc(100vh - 32px);
  display: grid;
  place-items: center;
  margin: 16px;
  padding: 64px;
  border-radius: 28px;
  background:
    linear-gradient(rgb(4 120 87 / 90%), rgb(6 95 70 / 94%)),
    radial-gradient(circle at top right, #34d399, transparent 50%);
  color: white;
}

.visual-content {
  width: min(100%, 560px);
}

.visual-badge {
  display: inline-flex;
  padding: 8px 13px;
  border: 1px solid rgb(255 255 255 / 25%);
  border-radius: 999px;
  background: rgb(255 255 255 / 10%);
  font-size: 13px;
  font-weight: 650;
}

.visual-content h2 {
  max-width: 500px;
  margin: 28px 0 20px;
  font-size: clamp(38px, 5vw, 58px);
  line-height: 1.08;
}

.visual-content > p {
  max-width: 500px;
  margin: 0;
  color: rgb(255 255 255 / 78%);
  line-height: 1.8;
}

.stat-preview {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
  margin-top: 48px;
}

.stat-preview div {
  padding: 20px;
  border: 1px solid rgb(255 255 255 / 18%);
  border-radius: 18px;
  background: rgb(255 255 255 / 10%);
  backdrop-filter: blur(10px);
}

.stat-preview span,
.stat-preview strong {
  display: block;
}

.stat-preview span {
  margin-bottom: 8px;
  color: rgb(255 255 255 / 70%);
  font-size: 13px;
}

.stat-preview strong {
  font-size: 20px;
}

@media (max-width: 900px) {
  .login-page {
    grid-template-columns: 1fr;
  }

  .visual-panel {
    display: none;
  }

  .login-panel {
    padding: 32px 24px;
  }

  .brand {
    margin-bottom: 48px;
  }
}
</style>