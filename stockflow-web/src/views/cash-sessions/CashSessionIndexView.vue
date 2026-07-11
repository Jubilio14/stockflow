<script setup lang="ts">
import axios from 'axios'
import {
  onMounted,
  reactive,
  ref,
} from 'vue'
import {
  useRouter,
} from 'vue-router'

import {
  getCashSessions,
} from '@/services/cashSessionService'

import type {
  CashSessionFilters,
  CashSessionItem,
  CashSessionPaginationMeta,
} from '@/types/cashSession'

interface ApiErrorResponse {
  message?: string
}

const router = useRouter()

const sessions =
  ref<CashSessionItem[]>([])

const pagination =
  ref<CashSessionPaginationMeta | null>(
    null,
  )

const isLoading = ref(false)
const errorMessage = ref('')

const filters =
  reactive<CashSessionFilters>({
    search: '',
    status: '',
    difference_status: '',
    date_from: '',
    date_to: '',
    page: 1,
    per_page: 10,
  })

async function loadSessions():
  Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response =
      await getCashSessions(filters)

    sessions.value =
      response.data

    pagination.value =
      response.meta
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

      errorMessage.value =
        error.response.data.message ??
        'Riwayat sesi kasir gagal dimuat.'

      return
    }

    errorMessage.value =
      'Riwayat sesi kasir gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

async function applyFilters():
  Promise<void> {
  filters.page = 1

  await loadSessions()
}

async function resetFilters():
  Promise<void> {
  filters.search = ''
  filters.status = ''
  filters.difference_status = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.page = 1

  await loadSessions()
}

async function changePage(
  page: number,
): Promise<void> {
  const lastPage =
    pagination.value?.last_page ?? 1

  if (
    page < 1 ||
    page > lastPage ||
    page === filters.page
  ) {
    return
  }

  filters.page = page

  await loadSessions()
}

function openDetail(
  session: CashSessionItem,
): void {
  router.push({
    name: 'cash-sessions.show',

    params: {
      id: session.id,
    },
  })
}

function differenceLabel(
  session: CashSessionItem,
): string {
  if (session.status === 'open') {
    return 'Belum ditutup'
  }

  if (
    session.difference_status ===
    'balanced'
  ) {
    return 'Sesuai'
  }

  if (
    session.difference_status ===
    'over'
  ) {
    return 'Lebih'
  }

  if (
    session.difference_status ===
    'short'
  ) {
    return 'Kurang'
  }

  return '-'
}

function differenceClass(
  session: CashSessionItem,
): string {
  if (session.status === 'open') {
    return 'open'
  }

  return (
    session.difference_status ?? ''
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
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
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

  if (remainingMinutes === 0) {
    return `${hours} jam`
  }

  return (
    `${hours} jam ` +
    `${remainingMinutes} menit`
  )
}

onMounted(() => {
  loadSessions()
})
</script>

<template>
  <section class="page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Rekonsiliasi Kas
        </p>

        <h1>Riwayat Sesi Kasir</h1>

        <p class="description">
          Pantau pembukaan, penutupan, uang
          fisik, dan selisih setiap sesi
          kasir.
        </p>
      </div>
    </header>

    <form
      class="filter-card"
      @submit.prevent="applyFilters"
    >
      <div class="search-field">
        <label for="search">
          Cari
        </label>

        <input
          id="search"
          v-model="filters.search"
          type="search"
          placeholder="Nomor sesi atau nama kasir"
        />
      </div>

      <div>
        <label for="status">
          Status Sesi
        </label>

        <select
          id="status"
          v-model="filters.status"
        >
          <option value="">
            Semua status
          </option>

          <option value="open">
            Open
          </option>

          <option value="closed">
            Closed
          </option>
        </select>
      </div>

      <div>
        <label for="difference">
          Selisih Kas
        </label>

        <select
          id="difference"
          v-model="
            filters.difference_status
          "
        >
          <option value="">
            Semua selisih
          </option>

          <option value="balanced">
            Uang sesuai
          </option>

          <option value="over">
            Uang lebih
          </option>

          <option value="short">
            Uang kurang
          </option>
        </select>
      </div>

      <div>
        <label for="date-from">
          Dari Tanggal
        </label>

        <input
          id="date-from"
          v-model="filters.date_from"
          type="date"
        />
      </div>

      <div>
        <label for="date-to">
          Sampai Tanggal
        </label>

        <input
          id="date-to"
          v-model="filters.date_to"
          type="date"
        />
      </div>

      <div class="filter-actions">
        <button
          type="submit"
          class="primary-button"
        >
          Terapkan
        </button>

        <button
          type="button"
          class="secondary-button"
          @click="resetFilters"
        >
          Reset
        </button>
      </div>
    </form>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <section class="table-card">
      <div
        v-if="isLoading"
        class="table-state"
      >
        Memuat riwayat sesi kasir...
      </div>

      <div
        v-else-if="sessions.length === 0"
        class="table-state"
      >
        Belum ada sesi kasir yang sesuai
        dengan filter.
      </div>

      <div
        v-else
        class="table-wrapper"
      >
        <table>
          <thead>
            <tr>
              <th>Sesi</th>
              <th>Kasir</th>
              <th>Waktu</th>
              <th>Transaksi</th>
              <th>Kas Seharusnya</th>
              <th>Uang Fisik</th>
              <th>Selisih</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="session in sessions"
              :key="session.id"
            >
              <td>
                <strong class="session-number">
                  {{ session.session_number }}
                </strong>

                <span class="subtext">
                  Modal
                  {{
                    formatCurrency(
                      session.opening_cash,
                    )
                  }}
                </span>
              </td>

              <td>
                <strong>
                  {{ session.cashier.name }}
                </strong>

                <span class="subtext">
                  Ditutup:
                  {{
                    session.closer?.name ??
                    '-'
                  }}
                </span>
              </td>

              <td>
                <span>
                  {{
                    formatDateTime(
                      session.opened_at,
                    )
                  }}
                </span>

                <span class="subtext">
                  {{
                    session.closed_at
                      ? `Tutup ${formatDateTime(
                          session.closed_at,
                        )}`
                      : 'Masih berjalan'
                  }}
                </span>

                <span class="subtext">
                  {{
                    formatDuration(
                      session.duration_minutes,
                    )
                  }}
                </span>
              </td>

              <td>
                {{
                  session.sales_count ?? 0
                }}
              </td>

              <td>
                {{
                  formatCurrency(
                    session.status === 'closed'
                      ? session
                          .expected_closing_cash
                      : session
                          .expected_cash_now,
                  )
                }}
              </td>

              <td>
                {{
                  formatCurrency(
                    session
                      .actual_closing_cash,
                  )
                }}
              </td>

              <td>
                <strong
                  :class="[
                    'difference-value',
                    differenceClass(
                      session,
                    ),
                  ]"
                >
                  {{
                    formatCurrency(
                      session.difference,
                    )
                  }}
                </strong>
              </td>

              <td>
                <div class="status-stack">
                  <span
                    :class="[
                      'session-badge',
                      session.status,
                    ]"
                  >
                    {{
                      session.status ===
                      'open'
                        ? 'Open'
                        : 'Closed'
                    }}
                  </span>

                  <span
                    :class="[
                      'difference-badge',
                      differenceClass(
                        session,
                      ),
                    ]"
                  >
                    {{
                      differenceLabel(
                        session,
                      )
                    }}
                  </span>
                </div>
              </td>

              <td>
                <button
                  type="button"
                  class="detail-button"
                  @click="
                    openDetail(session)
                  "
                >
                  Detail
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="
          pagination &&
          pagination.total > 0
        "
        class="pagination"
      >
        <span>
          {{ pagination.from }}
          –
          {{ pagination.to }}
          dari
          {{ pagination.total }}
          sesi
        </span>

        <div>
          <button
            type="button"
            :disabled="
              pagination.current_page === 1
            "
            @click="
              changePage(
                pagination.current_page - 1,
              )
            "
          >
            Sebelumnya
          </button>

          <strong>
            {{ pagination.current_page }}
            /
            {{ pagination.last_page }}
          </strong>

          <button
            type="button"
            :disabled="
              pagination.current_page ===
              pagination.last_page
            "
            @click="
              changePage(
                pagination.current_page + 1,
              )
            "
          >
            Selanjutnya
          </button>
        </div>
      </footer>
    </section>
  </section>
</template>

<style scoped>
.page {
  display: grid;
  gap: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.eyebrow {
  margin: 0 0 6px;
  color: #047857;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.page-header h1 {
  margin: 0;
  color: #0f172a;
  font-size: 28px;
}

.description {
  margin: 8px 0 0;
  color: #64748b;
}

.filter-card {
  display: grid;
  grid-template-columns:
    minmax(210px, 1.5fr)
    repeat(4, minmax(140px, 1fr))
    auto;
  gap: 12px;
  align-items: end;

  padding: 17px;

  border: 1px solid #e2e8f0;
  border-radius: 14px;

  background: white;
}

.filter-card > div {
  display: grid;
  gap: 6px;
}

.filter-card label {
  color: #475569;
  font-size: 11px;
  font-weight: 700;
}

.filter-card input,
.filter-card select {
  width: 100%;
  height: 41px;
  padding: 0 11px;

  border: 1px solid #cbd5e1;
  border-radius: 9px;

  outline: none;
  background: white;
  color: #0f172a;
  font: inherit;
}

.filter-card input:focus,
.filter-card select:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.filter-actions {
  display: flex !important;
  flex-direction: row;
  gap: 7px !important;
}

.primary-button,
.secondary-button,
.detail-button,
.pagination button {
  min-height: 39px;
  border: 0;
  border-radius: 9px;
  font-weight: 700;
}

.primary-button {
  padding: 0 15px;
  background: #047857;
  color: white;
}

.secondary-button {
  padding: 0 14px;
  background: #e2e8f0;
  color: #334155;
}

.alert-error {
  padding: 13px 15px;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #b91c1c;
}

.table-card {
  min-width: 0;
  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 14px;

  background: white;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1200px;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 15px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
}

th {
  background: #f8fafc;
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

td {
  color: #334155;
  font-size: 12px;
}

tbody tr:hover {
  background: #f8fafc;
}

.session-number {
  color: #0f172a;
}

.subtext {
  display: block;
  margin-top: 4px;
  color: #94a3b8;
  font-size: 10px;
}

.status-stack {
  display: grid;
  justify-items: start;
  gap: 5px;
}

.session-badge,
.difference-badge {
  display: inline-flex;
  align-items: center;

  padding: 5px 8px;
  border-radius: 999px;

  font-size: 9px;
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

.difference-value.balanced {
  color: #15803d;
}

.difference-value.over {
  color: #1d4ed8;
}

.difference-value.short {
  color: #b91c1c;
}

.detail-button {
  padding: 0 12px;
  background: #d1fae5;
  color: #047857;
}

.table-state {
  padding: 70px 20px;
  color: #64748b;
  text-align: center;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;

  padding: 14px 16px;
  color: #64748b;
  font-size: 11px;
}

.pagination div {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pagination button {
  padding: 0 12px;
  background: #e2e8f0;
  color: #334155;
}

.pagination button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

@media (max-width: 1250px) {
  .filter-card {
    grid-template-columns:
      repeat(3, minmax(0, 1fr));
  }

  .search-field {
    grid-column: span 2;
  }
}

@media (max-width: 700px) {
  .filter-card {
    grid-template-columns: 1fr;
  }

  .search-field {
    grid-column: auto;
  }

  .filter-actions {
    display: grid !important;
    grid-template-columns: 1fr 1fr;
  }

  .pagination {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>