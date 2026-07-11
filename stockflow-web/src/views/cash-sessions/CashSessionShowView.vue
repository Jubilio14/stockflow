<script setup lang="ts">
import axios from 'axios'
import {
  computed,
  onMounted,
  ref,
} from 'vue'
import {
  useRoute,
  useRouter,
} from 'vue-router'

import {
  getCashSession,
} from '@/services/cashSessionService'

import type {
  CashSessionItem,
} from '@/types/cashSession'

interface ApiErrorResponse {
  message?: string
}

const route = useRoute()
const router = useRouter()

const session =
  ref<CashSessionItem | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const sessionId = computed(() => {
  return Number(route.params.id)
})

async function loadSession():
  Promise<void> {
  if (
    !Number.isInteger(sessionId.value) ||
    sessionId.value <= 0
  ) {
    errorMessage.value =
      'ID sesi kasir tidak valid.'

    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    session.value =
      await getCashSession(
        sessionId.value,
      )
  } catch (error: unknown) {
    if (
      axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      if (!error.response) {
        errorMessage.value =
          'Tidak dapat terhubung ke server Laravel.'

        return
      }

      if (error.response.status === 404) {
        errorMessage.value =
          'Sesi kasir tidak ditemukan.'

        return
      }

      errorMessage.value =
        error.response.data.message ??
        'Detail sesi kasir gagal dimuat.'

      return
    }

    errorMessage.value =
      'Detail sesi kasir gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

function goBack(): void {
  router.push({
    name: 'cash-sessions.index',
  })
}

function differenceLabel(): string {
  if (!session.value) {
    return '-'
  }

  if (session.value.status === 'open') {
    return 'Sesi masih berjalan'
  }

  if (
    session.value.difference_status ===
    'balanced'
  ) {
    return 'Uang sesuai'
  }

  if (
    session.value.difference_status ===
    'over'
  ) {
    return 'Uang lebih'
  }

  if (
    session.value.difference_status ===
    'short'
  ) {
    return 'Uang kurang'
  }

  return '-'
}

function differenceClass(): string {
  if (!session.value) {
    return ''
  }

  if (session.value.status === 'open') {
    return 'open'
  }

  return (
    session.value.difference_status ?? ''
  )
}

function formatCurrency(
  value: number | null,
): string {
  if (value === null) {
    return '-'
  }

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
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    },
  ).format(new Date(value))
}

function formatDuration(
  minutes: number | null,
): string {
  if (minutes === null) {
    return '-'
  }

  const totalMinutes =
    Math.max(0, Math.floor(minutes))

  const hours =
    Math.floor(totalMinutes / 60)

  const remainingMinutes =
    totalMinutes % 60

  if (hours === 0) {
    return `${remainingMinutes} menit`
  }

  return (
    `${hours} jam ` +
    `${remainingMinutes} menit`
  )
}

onMounted(() => {
  loadSession()
})
</script>

<template>
  <section class="page">
    <button
      type="button"
      class="back-button"
      @click="goBack"
    >
      ← Kembali ke Riwayat
    </button>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="isLoading"
      class="state-card"
    >
      Memuat detail sesi kasir...
    </div>

    <template v-else-if="session">
      <header class="detail-header">
        <div>
          <p class="eyebrow">
            Detail Sesi Kasir
          </p>

          <h1>
            {{ session.session_number }}
          </h1>

          <p>
            Audit pembukaan, transaksi
            tunai, dan penutupan kas.
          </p>
        </div>

        <div class="header-status">
          <span
            :class="[
              'session-badge',
              session.status,
            ]"
          >
            {{
              session.status === 'open'
                ? 'Open'
                : 'Closed'
            }}
          </span>

          <span
            :class="[
              'difference-badge',
              differenceClass(),
            ]"
          >
            {{ differenceLabel() }}
          </span>
        </div>
      </header>

      <section class="money-grid">
        <article>
          <span>Modal Awal</span>

          <strong>
            {{
              formatCurrency(
                session.opening_cash,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Penjualan Tunai</span>

          <strong>
            {{
              formatCurrency(
                session.cash_sales_total,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Uang Seharusnya</span>

          <strong>
            {{
              formatCurrency(
                session.status ===
                'closed'
                  ? session
                      .expected_closing_cash
                  : session
                      .expected_cash_now,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Uang Fisik</span>

          <strong>
            {{
              formatCurrency(
                session
                  .actual_closing_cash,
              )
            }}
          </strong>
        </article>

        <article
          :class="[
            'difference-card',
            differenceClass(),
          ]"
        >
          <span>Selisih Kas</span>

          <strong>
            {{
              formatCurrency(
                session.difference,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Jumlah Transaksi</span>

          <strong>
            {{ session.sales_count ?? 0 }}
          </strong>
        </article>
      </section>

      <section class="detail-grid">
        <article class="detail-card">
          <header>
            <h2>Informasi Sesi</h2>
          </header>

          <dl>
            <div>
              <dt>Kasir</dt>
              <dd>
                {{ session.cashier.name }}
              </dd>
            </div>

            <div>
              <dt>Dibuka pada</dt>
              <dd>
                {{
                  formatDateTime(
                    session.opened_at,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Ditutup pada</dt>
              <dd>
                {{
                  formatDateTime(
                    session.closed_at,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Durasi sesi</dt>
              <dd>
                {{
                  formatDuration(
                    session
                      .duration_minutes,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Ditutup oleh</dt>
              <dd>
                {{
                  session.closer?.name ??
                  'Belum ditutup'
                }}
              </dd>
            </div>
          </dl>
        </article>

        <article class="detail-card">
          <header>
            <h2>Rekonsiliasi Kas</h2>
          </header>

          <dl>
            <div>
              <dt>Modal awal</dt>
              <dd>
                {{
                  formatCurrency(
                    session.opening_cash,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Penjualan cash</dt>
              <dd>
                {{
                  formatCurrency(
                    session
                      .cash_sales_total,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Expected cash</dt>
              <dd>
                {{
                  formatCurrency(
                    session.status ===
                    'closed'
                      ? session
                          .expected_closing_cash
                      : session
                          .expected_cash_now,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Actual cash</dt>
              <dd>
                {{
                  formatCurrency(
                    session
                      .actual_closing_cash,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Status selisih</dt>
              <dd>
                <span
                  :class="[
                    'difference-badge',
                    differenceClass(),
                  ]"
                >
                  {{ differenceLabel() }}
                </span>
              </dd>
            </div>
          </dl>
        </article>
      </section>

      <section class="notes-grid">
        <article class="detail-card">
          <header>
            <h2>Catatan Pembukaan</h2>
          </header>

          <p>
            {{
              session.opening_notes ??
              'Tidak ada catatan pembukaan.'
            }}
          </p>
        </article>

        <article class="detail-card">
          <header>
            <h2>Catatan Penutupan</h2>
          </header>

          <p>
            {{
              session.closing_notes ??
              'Tidak ada catatan penutupan.'
            }}
          </p>
        </article>
      </section>
    </template>
  </section>
</template>

<style scoped>
.page {
  display: grid;
  gap: 19px;
}

.back-button {
  justify-self: start;
  min-height: 39px;
  padding: 0 13px;

  border: 0;
  border-radius: 9px;

  background: #e2e8f0;
  color: #334155;

  font-weight: 700;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;

  padding: 23px;

  border: 1px solid #e2e8f0;
  border-radius: 15px;

  background: white;
}

.eyebrow {
  margin: 0 0 6px;
  color: #047857;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.detail-header h1 {
  margin: 0;
  color: #0f172a;
  font-size: 26px;
}

.detail-header p {
  margin: 7px 0 0;
  color: #64748b;
}

.header-status {
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
}

.session-badge,
.difference-badge {
  display: inline-flex;
  align-items: center;

  padding: 6px 9px;
  border-radius: 999px;

  font-size: 10px;
  font-weight: 800;
}

.session-badge.open {
  background: #dcfce7;
  color: #15803d;
}

.session-badge.closed {
  background: #e2e8f0;
  color: #475569;
}

.difference-badge.balanced {
  background: #dcfce7;
  color: #15803d;
}

.difference-badge.over {
  background: #dbeafe;
  color: #1d4ed8;
}

.difference-badge.short {
  background: #fee2e2;
  color: #b91c1c;
}

.difference-badge.open {
  background: #fef3c7;
  color: #b45309;
}

.money-grid {
  display: grid;
  grid-template-columns:
    repeat(6, minmax(0, 1fr));
  gap: 12px;
}

.money-grid article {
  padding: 17px;

  border: 1px solid #e2e8f0;
  border-radius: 13px;

  background: white;
}

.money-grid span,
.money-grid strong {
  display: block;
}

.money-grid span {
  color: #64748b;
  font-size: 10px;
  font-weight: 700;
}

.money-grid strong {
  margin-top: 7px;
  color: #0f172a;
  font-size: 16px;
}

.difference-card.balanced {
  border-color: #86efac;
  background: #f0fdf4;
}

.difference-card.balanced strong {
  color: #15803d;
}

.difference-card.over {
  border-color: #93c5fd;
  background: #eff6ff;
}

.difference-card.over strong {
  color: #1d4ed8;
}

.difference-card.short {
  border-color: #fca5a5;
  background: #fef2f2;
}

.difference-card.short strong {
  color: #b91c1c;
}

.detail-grid,
.notes-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.detail-card {
  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 14px;

  background: white;
}

.detail-card header {
  padding: 15px 18px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.detail-card h2 {
  margin: 0;
  color: #0f172a;
  font-size: 15px;
}

.detail-card dl {
  margin: 0;
  padding: 5px 18px;
}

.detail-card dl div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;

  padding: 13px 0;
  border-bottom: 1px solid #e2e8f0;
}

.detail-card dl div:last-child {
  border-bottom: 0;
}

.detail-card dt {
  color: #64748b;
  font-size: 11px;
}

.detail-card dd {
  margin: 0;
  color: #0f172a;
  font-size: 12px;
  font-weight: 700;
  text-align: right;
}

.detail-card > p {
  margin: 0;
  padding: 18px;
  color: #475569;
  line-height: 1.7;
}

.alert-error,
.state-card {
  padding: 18px;
  border-radius: 11px;
}

.alert-error {
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}

.state-card {
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
}

@media (max-width: 1150px) {
  .money-grid {
    grid-template-columns:
      repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 700px) {
  .detail-header {
    flex-direction: column;
  }

  .money-grid,
  .detail-grid,
  .notes-grid {
    grid-template-columns: 1fr;
  }

  .detail-card dl div {
    align-items: flex-start;
    flex-direction: column;
    gap: 5px;
  }

  .detail-card dd {
    text-align: left;
  }
}
</style>