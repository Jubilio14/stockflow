<script setup lang="ts">
import axios from 'axios'
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue'

import {
  getReportCashiers,
  getSalesReport,
} from '@/services/salesReportService'

import type {
  DailySalesReportRow,
  ReportCashierOption,
  SalesReportFilters,
  SalesReportResponse,
} from '@/types/salesReport'

interface ApiErrorResponse {
  message?: string

  errors?: Record<
    string,
    string[]
  >
}

type ReportPreset =
  | 'today'
  | 'last7'
  | 'month'
  | 'custom'

const report =
  ref<SalesReportResponse | null>(
    null,
  )

const cashiers =
  ref<ReportCashierOption[]>([])

const activePreset =
  ref<ReportPreset>('today')

const isLoading = ref(false)
const isLoadingCashiers = ref(false)

const errorMessage = ref('')

const today =
  toLocalDateString(new Date())

const filters =
  reactive<SalesReportFilters>({
    date_from: today,
    date_to: today,
    cashier_id: '',
    payment_method: '',
  })

const summary = computed(() => {
  return report.value?.summary ?? null
})

const hasSales = computed(() => {
  return (
    (summary.value
      ?.total_transactions ?? 0) > 0
  )
})

const maximumDailyRevenue =
  computed(() => {
    const revenues =
      report.value?.daily.map(
        (row) => row.total_revenue,
      ) ?? []

    return Math.max(
      ...revenues,
      0,
    )
  })

async function initializeReport():
  Promise<void> {
  await Promise.all([
    loadCashiers(),
    loadReport(),
  ])
}

async function loadCashiers():
  Promise<void> {
  isLoadingCashiers.value = true

  try {
    cashiers.value =
      await getReportCashiers()
  } catch {
    /*
     * Filter kasir tidak boleh membuat
     * seluruh halaman laporan gagal.
     */

    cashiers.value = []
  } finally {
    isLoadingCashiers.value = false
  }
}

async function loadReport():
  Promise<void> {
  if (!validateDates()) {
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    report.value =
      await getSalesReport(filters)
  } catch (error: unknown) {
    handleReportError(error)
  } finally {
    isLoading.value = false
  }
}

function handleReportError(
  error: unknown,
): void {
  if (
    !axios.isAxiosError<ApiErrorResponse>(
      error,
    )
  ) {
    errorMessage.value =
      'Laporan penjualan gagal dimuat.'

    return
  }

  if (!error.response) {
    errorMessage.value =
      'Tidak dapat terhubung ke server Laravel.'

    return
  }

  const data =
    error.response.data

  if (error.response.status === 422) {
    const firstValidationError =
      Object.values(
        data.errors ?? {},
      )[0]?.[0]

    errorMessage.value =
      firstValidationError ??
      data.message ??
      'Filter laporan tidak valid.'

    return
  }

  if (error.response.status === 403) {
    errorMessage.value =
      data.message ??
      'Anda tidak memiliki akses ke laporan penjualan.'

    return
  }

  errorMessage.value =
    data.message ??
    'Laporan penjualan gagal dimuat.'
}

function validateDates(): boolean {
  errorMessage.value = ''

  if (
    !filters.date_from ||
    !filters.date_to
  ) {
    errorMessage.value =
      'Tanggal awal dan akhir wajib diisi.'

    return false
  }

  if (
    filters.date_to <
    filters.date_from
  ) {
    errorMessage.value =
      'Tanggal akhir tidak boleh sebelum tanggal awal.'

    return false
  }

  return true
}

async function applyFilters():
  Promise<void> {
  activePreset.value = 'custom'

  await loadReport()
}

async function applyPreset(
  preset: Exclude<
    ReportPreset,
    'custom'
  >,
): Promise<void> {
  const currentDate = new Date()

  if (preset === 'today') {
    const date =
      toLocalDateString(currentDate)

    filters.date_from = date
    filters.date_to = date
  }

  if (preset === 'last7') {
    const startDate =
      new Date(currentDate)

    startDate.setDate(
      currentDate.getDate() - 6,
    )

    filters.date_from =
      toLocalDateString(startDate)

    filters.date_to =
      toLocalDateString(currentDate)
  }

  if (preset === 'month') {
    const firstDay =
      new Date(
        currentDate.getFullYear(),
        currentDate.getMonth(),
        1,
      )

    const lastDay =
      new Date(
        currentDate.getFullYear(),
        currentDate.getMonth() + 1,
        0,
      )

    filters.date_from =
      toLocalDateString(firstDay)

    filters.date_to =
      toLocalDateString(lastDay)
  }

  activePreset.value = preset

  await loadReport()
}

async function resetFilters():
  Promise<void> {
  filters.cashier_id = ''
  filters.payment_method = ''

  await applyPreset('today')
}

function handleCustomDateChange(): void {
  activePreset.value = 'custom'
}

function revenueBarWidth(
  row: DailySalesReportRow,
): string {
  if (
    maximumDailyRevenue.value <= 0
  ) {
    return '0%'
  }

  const percentage =
    (
      row.total_revenue /
      maximumDailyRevenue.value
    ) * 100

  return `${Math.max(
    percentage,
    row.total_revenue > 0
      ? 4
      : 0,
  )}%`
}

function toLocalDateString(
  date: Date,
): string {
  const year =
    date.getFullYear()

  const month =
    String(
      date.getMonth() + 1,
    ).padStart(2, '0')

  const day =
    String(
      date.getDate(),
    ).padStart(2, '0')

  return `${year}-${month}-${day}`
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

function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    'id-ID',
  ).format(value)
}

function formatPercentage(
  value: number,
): string {
  return new Intl.NumberFormat(
    'id-ID',
    {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    },
  ).format(value) + '%'
}

function formatReportDate(
  value: string,
): string {
  const [
    year,
    month,
    day,
  ] = value
    .split('-')
    .map(Number)

  const date =
    new Date(
      year ?? 0,
      (month ?? 1) - 1,
      day ?? 1,
    )

  return new Intl.DateTimeFormat(
    'id-ID',
    {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    },
  ).format(date)
}

onMounted(() => {
  initializeReport()
})
</script>

<template>
  <section class="report-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Analitik Penjualan
        </p>

        <h1>
          Laporan Pendapatan
        </h1>

        <p>
          Pantau pendapatan, HPP, diskon,
          dan laba kotor berdasarkan periode.
        </p>
      </div>

      <div
        v-if="report"
        class="period-badge"
      >
        {{ report.filters.total_days }}
        hari
      </div>
    </header>

    <section class="preset-card">
      <span>Periode Cepat</span>

      <div class="preset-buttons">
        <button
          type="button"
          :class="{
            active:
              activePreset === 'today',
          }"
          @click="
            applyPreset('today')
          "
        >
          Hari Ini
        </button>

        <button
          type="button"
          :class="{
            active:
              activePreset === 'last7',
          }"
          @click="
            applyPreset('last7')
          "
        >
          7 Hari Terakhir
        </button>

        <button
          type="button"
          :class="{
            active:
              activePreset === 'month',
          }"
          @click="
            applyPreset('month')
          "
        >
          Bulan Ini
        </button>

        <span
          v-if="
            activePreset === 'custom'
          "
          class="custom-badge"
        >
          Custom
        </span>
      </div>
    </section>

    <form
      class="filter-card"
      @submit.prevent="applyFilters"
    >
      <div>
        <label for="report-date-from">
          Dari Tanggal
        </label>

        <input
          id="report-date-from"
          v-model="filters.date_from"
          type="date"
          @change="
            handleCustomDateChange
          "
        />
      </div>

      <div>
        <label for="report-date-to">
          Sampai Tanggal
        </label>

        <input
          id="report-date-to"
          v-model="filters.date_to"
          type="date"
          @change="
            handleCustomDateChange
          "
        />
      </div>

      <div>
        <label for="report-cashier">
          Kasir
        </label>

        <select
          id="report-cashier"
          v-model="filters.cashier_id"
          :disabled="
            isLoadingCashiers
          "
        >
          <option value="">
            Semua kasir
          </option>

          <option
            v-for="cashier in cashiers"
            :key="cashier.id"
            :value="cashier.id"
          >
            {{ cashier.name }}
          </option>
        </select>
      </div>

      <div>
        <label for="report-payment">
          Pembayaran
        </label>

        <select
          id="report-payment"
          v-model="
            filters.payment_method
          "
        >
          <option value="">
            Semua metode
          </option>

          <option value="cash">
            Tunai / Cash
          </option>

          <option value="qris">
            QRIS
          </option>

          <option value="transfer">
            Transfer
          </option>

          <option value="debit">
            Debit
          </option>
        </select>
      </div>

      <div class="filter-actions">
        <button
          type="submit"
          class="primary-button"
          :disabled="isLoading"
        >
          {{
            isLoading
              ? 'Memuat...'
              : 'Terapkan'
          }}
        </button>

        <button
          type="button"
          class="secondary-button"
          :disabled="isLoading"
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

    <div
      v-if="isLoading && !report"
      class="loading-card"
    >
      Memuat laporan penjualan...
    </div>

    <template v-else-if="report && summary">
      <section class="summary-grid">
        <article class="summary-card revenue">
          <span>Total Pendapatan</span>

          <strong>
            {{
              formatCurrency(
                summary.total_revenue,
              )
            }}
          </strong>

          <small>
            Setelah diskon
          </small>
        </article>

        <article class="summary-card profit">
          <span>Laba Kotor</span>

          <strong>
            {{
              formatCurrency(
                summary.gross_profit,
              )
            }}
          </strong>

          <small>
            Margin
            {{
              formatPercentage(
                summary
                  .gross_margin_percentage,
              )
            }}
          </small>
        </article>

        <article class="summary-card">
          <span>Total Transaksi</span>

          <strong>
            {{
              formatNumber(
                summary
                  .total_transactions,
              )
            }}
          </strong>

          <small>
            Rata-rata
            {{
              formatCurrency(
                summary
                  .average_transaction,
              )
            }}
          </small>
        </article>

        <article class="summary-card">
          <span>Produk Terjual</span>

          <strong>
            {{
              formatNumber(
                summary
                  .total_items_sold,
              )
            }}
          </strong>

          <small>
            Total unit produk
          </small>
        </article>

        <article class="summary-card discount">
          <span>Total Diskon</span>

          <strong>
            {{
              formatCurrency(
                summary.total_discount,
              )
            }}
          </strong>

          <small>
            Potongan transaksi
          </small>
        </article>

        <article class="summary-card cost">
          <span>Total HPP</span>

          <strong>
            {{
              formatCurrency(
                summary.total_cost,
              )
            }}
          </strong>

          <small>
            Harga pokok penjualan
          </small>
        </article>
      </section>

      <section
        v-if="!hasSales"
        class="empty-report"
      >
        <div class="empty-icon">
          Rp
        </div>

        <h2>
          Belum ada penjualan
        </h2>

        <p>
          Tidak ada transaksi selesai pada
          periode dan filter yang dipilih.
        </p>
      </section>

      <section
        v-else
        class="daily-card"
      >
        <header class="card-header">
          <div>
            <h2>Rekap Harian</h2>

            <p>
              Rincian performa penjualan
              untuk setiap tanggal.
            </p>
          </div>

          <span>
            {{
              report.filters.date_from
            }}
            –
            {{
              report.filters.date_to
            }}
          </span>
        </header>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Transaksi</th>
                <th>Produk</th>
                <th>Pendapatan</th>
                <th>Diskon</th>
                <th>HPP</th>
                <th>Laba Kotor</th>
                <th>Margin</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="
                  row in report.daily
                "
                :key="row.date"
                :class="{
                  empty:
                    row
                      .total_transactions ===
                    0,
                }"
              >
                <td>
                  <strong>
                    {{
                      formatReportDate(
                        row.date,
                      )
                    }}
                  </strong>

                  <div
                    class="revenue-bar"
                  >
                    <span
                      :style="{
                        width:
                          revenueBarWidth(
                            row,
                          ),
                      }"
                    />
                  </div>
                </td>

                <td>
                  {{
                    formatNumber(
                      row
                        .total_transactions,
                    )
                  }}
                </td>

                <td>
                  {{
                    formatNumber(
                      row
                        .total_items_sold,
                    )
                  }}
                  unit
                </td>

                <td>
                  <strong class="revenue-text">
                    {{
                      formatCurrency(
                        row.total_revenue,
                      )
                    }}
                  </strong>
                </td>

                <td>
                  <span class="discount-text">
                    {{
                      formatCurrency(
                        row.total_discount,
                      )
                    }}
                  </span>
                </td>

                <td>
                  {{
                    formatCurrency(
                      row.total_cost,
                    )
                  }}
                </td>

                <td>
                  <strong class="profit-text">
                    {{
                      formatCurrency(
                        row.gross_profit,
                      )
                    }}
                  </strong>
                </td>

                <td>
                  {{
                    formatPercentage(
                      row
                        .gross_margin_percentage,
                    )
                  }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.report-page {
  display: grid;
  gap: 20px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
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

.page-header p {
  margin: 8px 0 0;
  color: #64748b;
}

.period-badge {
  padding: 8px 12px;
  border-radius: 999px;
  background: #d1fae5;
  color: #047857;
  font-size: 11px;
  font-weight: 800;
}

.preset-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;

  padding: 14px 17px;
  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: white;
}

.preset-card > span {
  color: #475569;
  font-size: 12px;
  font-weight: 750;
}

.preset-buttons {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
}

.preset-buttons button {
  min-height: 36px;
  padding: 0 12px;

  border: 1px solid #cbd5e1;
  border-radius: 9px;

  background: white;
  color: #475569;

  font-size: 11px;
  font-weight: 700;
}

.preset-buttons button.active {
  border-color: #059669;
  background: #d1fae5;
  color: #047857;
}

.custom-badge {
  padding: 6px 9px;
  border-radius: 999px;
  background: #fef3c7;
  color: #b45309;
  font-size: 10px;
  font-weight: 800;
}

.filter-card {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(150px, 1fr))
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
.secondary-button {
  min-height: 41px;
  padding: 0 14px;

  border: 0;
  border-radius: 9px;

  font-weight: 700;
}

.primary-button {
  background: #047857;
  color: white;
}

.secondary-button {
  background: #e2e8f0;
  color: #334155;
}

.primary-button:disabled,
.secondary-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
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
  border-radius: 14px;
  background: white;
  color: #64748b;
  text-align: center;
}

.summary-grid {
  display: grid;
  grid-template-columns:
    repeat(3, minmax(0, 1fr));
  gap: 13px;
}

.summary-card {
  padding: 18px;

  border: 1px solid #e2e8f0;
  border-radius: 13px;

  background: white;
}

.summary-card span,
.summary-card strong,
.summary-card small {
  display: block;
}

.summary-card span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.summary-card strong {
  margin-top: 8px;
  color: #0f172a;
  font-size: 22px;
}

.summary-card small {
  margin-top: 6px;
  color: #94a3b8;
  font-size: 10px;
}

.summary-card.revenue {
  border-color: #6ee7b7;
  background: #f0fdf4;
}

.summary-card.revenue strong {
  color: #047857;
}

.summary-card.profit {
  border-color: #93c5fd;
  background: #eff6ff;
}

.summary-card.profit strong {
  color: #1d4ed8;
}

.summary-card.discount strong {
  color: #dc2626;
}

.summary-card.cost strong {
  color: #7c3aed;
}

.empty-report {
  display: grid;
  justify-items: center;

  padding: 60px 20px;

  border: 1px solid #e2e8f0;
  border-radius: 14px;

  background: white;
  text-align: center;
}

.empty-icon {
  width: 54px;
  height: 54px;

  display: grid;
  place-items: center;

  border-radius: 15px;
  background: #d1fae5;
  color: #047857;
  font-weight: 800;
}

.empty-report h2 {
  margin: 15px 0 0;
  color: #0f172a;
  font-size: 19px;
}

.empty-report p {
  margin: 7px 0 0;
  color: #64748b;
}

.daily-card {
  min-width: 0;
  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 14px;

  background: white;
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;

  padding: 17px 19px;
  border-bottom: 1px solid #e2e8f0;
}

.card-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 17px;
}

.card-header p {
  margin: 5px 0 0;
  color: #64748b;
  font-size: 11px;
}

.card-header > span {
  color: #64748b;
  font-size: 11px;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1050px;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 15px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

th {
  background: #f8fafc;
  color: #64748b;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

td {
  color: #334155;
  font-size: 11px;
}

tbody tr:hover {
  background: #f8fafc;
}

tbody tr.empty {
  opacity: 0.55;
}

.revenue-text {
  color: #047857;
}

.discount-text {
  color: #dc2626;
}

.profit-text {
  color: #1d4ed8;
}

.revenue-bar {
  width: 130px;
  height: 4px;

  margin-top: 7px;
  overflow: hidden;

  border-radius: 999px;
  background: #e2e8f0;
}

.revenue-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: #10b981;
}

@media (max-width: 1100px) {
  .filter-card {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .filter-actions {
    grid-column: span 2;
  }
}

@media (max-width: 750px) {
  .page-header,
  .preset-card,
  .card-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .summary-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 550px) {
  .filter-card,
  .summary-grid {
    grid-template-columns: 1fr;
  }

  .filter-actions {
    display: grid !important;
    grid-column: auto;
    grid-template-columns: 1fr 1fr;
  }
}
</style>