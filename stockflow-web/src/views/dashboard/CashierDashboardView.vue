<script setup lang="ts">
import axios from 'axios'
import {
  computed,
  onMounted,
  ref,
} from 'vue'
import { useRouter } from 'vue-router'

import {
  getCurrentCashSession,
} from '@/services/cashSessionService'

import type {
  CashSessionItem,
} from '@/types/cashSession'

interface Props {
  userName: string
}

interface ApiErrorResponse {
  message?: string
}

defineProps<Props>()

const router = useRouter()

const session =
  ref<CashSessionItem | null>(null)

const registerInUse = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const isMySessionOpen = computed(() => {
  return (
    session.value?.status === 'open' &&
    session.value
      .is_owned_by_current_user
  )
})

const isOtherSessionOpen = computed(() => {
  return (
    session.value?.status === 'open' &&
    !session.value
      .is_owned_by_current_user
  )
})

async function loadSession():
  Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response =
      await getCurrentCashSession()

    session.value =
      response.session

    registerInUse.value =
      response.register_in_use
  } catch (error: unknown) {
    if (
      axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      errorMessage.value =
        error.response?.data.message ??
        'Status sesi kasir gagal dimuat.'

      return
    }

    errorMessage.value =
      'Status sesi kasir gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

function openSessionPage(): void {
  router.push({
    name: 'cashier.session',
  })
}

function openPos(): void {
  router.push({
    name: 'pos',
  })
}

function formatCurrency(
  value: number,
): string {
  return new Intl.NumberFormat(
    'id-ID',
    {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 2,
    },
  ).format(value)
}

function formatDateTime(
  value: string | null,
): string {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat(
    'id-ID',
    {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    },
  ).format(new Date(value))
}

onMounted(() => {
  loadSession()
})
</script>

<template>
  <section class="cashier-dashboard">
    <header class="welcome-card">
      <div>
        <p>Selamat Datang</p>

        <h1>Halo, {{ userName }}</h1>

        <span>
          Siapkan sesi kasir sebelum
          memproses transaksi melalui POS.
        </span>
      </div>

      <div class="role-badge">
        Cashier
      </div>
    </header>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="isLoading"
      class="loading-card"
    >
      Memeriksa status sesi kasir...
    </div>

    <template v-else>
      <section
        v-if="isMySessionOpen"
        class="session-card active"
      >
        <header>
          <div>
            <span class="status-label">
              Sesi Kasir Aktif
            </span>

            <h2>
              {{ session?.session_number }}
            </h2>
          </div>

          <span class="open-badge">
            Open
          </span>
        </header>

        <div class="session-grid">
          <article>
            <span>Dibuka Pada</span>

            <strong>
              {{
                formatDateTime(
                  session?.opened_at ??
                  null,
                )
              }}
            </strong>
          </article>

          <article>
            <span>Modal Awal</span>

            <strong>
              {{
                formatCurrency(
                  session
                    ?.opening_cash ?? 0,
                )
              }}
            </strong>
          </article>

          <article>
            <span>Kasir</span>

            <strong>
              {{ session?.cashier.name }}
            </strong>
          </article>
        </div>

        <div class="session-actions">
          <button
            type="button"
            class="primary-button"
            @click="openPos"
          >
            Masuk ke POS
          </button>

          <button
            type="button"
            class="secondary-button"
            @click="openSessionPage"
          >
            Lihat / Tutup Sesi
          </button>
        </div>
      </section>

      <section
        v-else-if="isOtherSessionOpen"
        class="session-card blocked"
      >
        <span class="status-label">
          Meja Kasir Sedang Digunakan
        </span>

        <h2>
          {{ session?.cashier.name }}
        </h2>

        <p>
          Sesi
          <strong>
            {{ session?.session_number }}
          </strong>
          sedang aktif. Tunggu sampai sesi
          tersebut ditutup.
        </p>

        <button
          type="button"
          class="secondary-button"
          @click="loadSession"
        >
          Periksa Kembali
        </button>
      </section>

      <section
        v-else
        class="session-card empty"
      >
        <div class="empty-icon">
          Rp
        </div>

        <div>
          <span class="status-label">
            Belum Ada Sesi Aktif
          </span>

          <h2>Buka Kasir Terlebih Dahulu</h2>

          <p>
            Masukkan modal awal sebelum
            mulai melayani transaksi.
          </p>
        </div>

        <button
          type="button"
          class="primary-button"
          @click="openSessionPage"
        >
          Buka Sesi Kasir
        </button>
      </section>

      <section class="quick-grid">
        <button
          type="button"
          @click="openSessionPage"
        >
          <strong>Sesi Kasir</strong>

          <span>
            Buka, lihat, atau tutup sesi
          </span>
        </button>

        <button
          type="button"
          :disabled="!isMySessionOpen"
          @click="openPos"
        >
          <strong>Buka POS</strong>

          <span>
            Proses transaksi penjualan
          </span>
        </button>

        <button
          type="button"
          @click="loadSession"
        >
          <strong>Perbarui Status</strong>

          <span>
            Periksa kondisi meja kasir
          </span>
        </button>
      </section>
    </template>
  </section>
</template>

<style scoped>
.cashier-dashboard {
  display: grid;
  gap: 20px;
}

.welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;

  padding: 27px 30px;

  border: 1px solid #a7f3d0;
  border-radius: 18px;

  background:
    linear-gradient(
      135deg,
      #ecfdf5,
      #ffffff
    );
}

.welcome-card p {
  margin: 0;
  color: #047857;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.welcome-card h1 {
  margin: 10px 0 0;
  color: #0f172a;
  font-size: 29px;
}

.welcome-card span {
  display: block;
  margin-top: 8px;
  color: #64748b;
}

.role-badge {
  padding: 10px 17px;
  border-radius: 999px;
  background: #047857;
  color: white;
  font-size: 11px;
  font-weight: 800;
}

.alert-error {
  padding: 13px 15px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #b91c1c;
}

.loading-card {
  padding: 70px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 15px;
  background: white;
  color: #64748b;
  text-align: center;
}

.session-card {
  padding: 23px;

  border: 1px solid #e2e8f0;
  border-radius: 16px;

  background: white;
}

.session-card.active {
  border-color: #6ee7b7;
}

.session-card.blocked {
  border-color: #fcd34d;
  background: #fffbeb;
}

.session-card.empty {
  display: flex;
  align-items: center;
  gap: 18px;
}

.session-card header {
  display: flex;
  justify-content: space-between;
  gap: 18px;
}

.status-label {
  color: #047857;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
}

.session-card h2 {
  margin: 7px 0 0;
  color: #0f172a;
  font-size: 21px;
}

.session-card p {
  margin: 8px 0 18px;
  color: #64748b;
  line-height: 1.6;
}

.open-badge {
  align-self: flex-start;
  padding: 6px 10px;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 10px;
  font-weight: 800;
}

.session-grid {
  display: grid;
  grid-template-columns:
    repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 20px;
}

.session-grid article {
  padding: 14px;
  border-radius: 11px;
  background: #f8fafc;
}

.session-grid span,
.session-grid strong {
  display: block;
}

.session-grid span {
  color: #64748b;
  font-size: 10px;
}

.session-grid strong {
  margin-top: 6px;
  color: #0f172a;
  font-size: 12px;
}

.session-actions {
  display: flex;
  gap: 10px;
  margin-top: 19px;
}

.primary-button,
.secondary-button {
  min-height: 42px;
  padding: 0 15px;

  border: 0;
  border-radius: 10px;

  font-weight: 750;
}

.primary-button {
  background: #047857;
  color: white;
}

.secondary-button {
  background: #e2e8f0;
  color: #334155;
}

.empty-icon {
  width: 55px;
  height: 55px;

  display: grid;
  place-items: center;
  flex: 0 0 auto;

  border-radius: 15px;
  background: #d1fae5;
  color: #047857;

  font-weight: 800;
}

.session-card.empty > div:nth-child(2) {
  flex: 1;
}

.quick-grid {
  display: grid;
  grid-template-columns:
    repeat(3, minmax(0, 1fr));
  gap: 13px;
}

.quick-grid button {
  min-height: 110px;
  padding: 17px;

  border: 1px solid #e2e8f0;
  border-radius: 13px;

  background: white;
  text-align: left;
}

.quick-grid button:hover:not(:disabled) {
  border-color: #6ee7b7;
  background: #f0fdf4;
}

.quick-grid button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.quick-grid strong,
.quick-grid span {
  display: block;
}

.quick-grid strong {
  color: #0f172a;
  font-size: 13px;
}

.quick-grid span {
  margin-top: 7px;
  color: #64748b;
  font-size: 10px;
}

@media (max-width: 750px) {
  .welcome-card,
  .session-card.empty {
    align-items: flex-start;
    flex-direction: column;
  }

  .session-grid,
  .quick-grid {
    grid-template-columns: 1fr;
  }

  .session-actions {
    display: grid;
  }
}
</style>