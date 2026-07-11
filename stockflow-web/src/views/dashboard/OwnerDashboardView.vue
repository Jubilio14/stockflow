<script setup lang="ts">
import axios from 'axios'
import {
  onMounted,
  ref,
} from 'vue'
import { useRouter } from 'vue-router'

import {
  getDashboardOverview,
} from '@/services/dashboardService'

import type {
  DashboardDifferenceStatus,
  DashboardOverviewResponse,
  DashboardRecentSale,
} from '@/types/dashboard'

interface ApiErrorResponse {
  message?: string
}

const router = useRouter()

const overview =
  ref<DashboardOverviewResponse | null>(
    null,
  )

const isLoading = ref(false)
const errorMessage = ref('')

async function loadDashboard():
  Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    overview.value =
      await getDashboardOverview()
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

      if (error.response.status === 403) {
        errorMessage.value =
          error.response.data.message ??
          'Anda tidak memiliki akses ke dashboard ini.'

        return
      }

      errorMessage.value =
        error.response.data.message ??
        'Dashboard gagal dimuat.'

      return
    }

    errorMessage.value =
      'Dashboard gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

function openReports(): void {
  router.push({
    name: 'reports.sales',
  })
}

function openSales(): void {
  router.push({
    name: 'sales.index',
  })
}

function openSale(
  sale: DashboardRecentSale,
): void {
  router.push({
    name: 'sales.show',

    params: {
      id: sale.id,
    },
  })
}

function openProducts(): void {
  router.push({
    name: 'products.index',
  })
}

function openCashSessions(): void {
  router.push({
    name: 'cash-sessions.index',
  })
}

function openCashSession(
  cashSessionId: number,
): void {
  router.push({
    name: 'cash-sessions.show',

    params: {
      id: cashSessionId,
    },
  })
}

function differenceLabel(
  status: DashboardDifferenceStatus,
): string {
  if (status === 'balanced') {
    return 'Uang sesuai'
  }

  if (status === 'over') {
    return 'Uang lebih'
  }

  if (status === 'short') {
    return 'Uang kurang'
  }

  return 'Belum tersedia'
}

function differenceClass(
  status: DashboardDifferenceStatus,
): string {
  return status ?? 'unknown'
}

function paymentLabel(
  paymentMethod: string,
): string {
  const labels:
    Record<string, string> = {
      cash: 'Tunai',
      qris: 'QRIS',
      transfer: 'Transfer',
      debit: 'Debit',
    }

  return (
    labels[paymentMethod] ??
    paymentMethod
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
  return (
    new Intl.NumberFormat(
      'id-ID',
      {
        maximumFractionDigits: 2,
      },
    ).format(value) + '%'
  )
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

function formatTime(
  value: string | null,
): string {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat(
    'id-ID',
    {
      hour: '2-digit',
      minute: '2-digit',
    },
  ).format(new Date(value))
}

function formatDuration(
  minutes: number,
): string {
  const totalMinutes =
    Math.max(
      0,
      Math.floor(minutes),
    )

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
  loadDashboard()
})
</script>

<template>
  <section class="dashboard-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          StockFlow Overview
        </p>

        <h1>Dashboard</h1>

        <p>
          Ringkasan penjualan, stok, dan
          operasional kasir hari ini.
        </p>
      </div>

      <button
        type="button"
        class="refresh-button"
        :disabled="isLoading"
        @click="loadDashboard"
      >
        {{
          isLoading
            ? 'Memuat...'
            : 'Perbarui Data'
        }}
      </button>
    </header>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="isLoading && !overview"
      class="loading-card"
    >
      Memuat dashboard StockFlow...
    </div>

    <template v-else-if="overview">
      <section class="summary-grid">
        <article class="summary-card revenue">
          <span>Pendapatan Hari Ini</span>

          <strong>
            {{
              formatCurrency(
                overview.today
                  .total_revenue,
              )
            }}
          </strong>

          <small>
            Setelah diskon
            {{
              formatCurrency(
                overview.today
                  .total_discount,
              )
            }}
          </small>
        </article>

        <article class="summary-card profit">
          <span>Laba Kotor Hari Ini</span>

          <strong>
            {{
              formatCurrency(
                overview.today
                  .gross_profit,
              )
            }}
          </strong>

          <small>
            Margin
            {{
              formatPercentage(
                overview.today
                  .gross_margin_percentage,
              )
            }}
          </small>
        </article>

        <article class="summary-card">
          <span>Transaksi Hari Ini</span>

          <strong>
            {{
              formatNumber(
                overview.today
                  .total_transactions,
              )
            }}
          </strong>

          <small>
            Rata-rata
            {{
              formatCurrency(
                overview.today
                  .average_transaction,
              )
            }}
          </small>
        </article>

        <article class="summary-card items">
          <span>Produk Terjual</span>

          <strong>
            {{
              formatNumber(
                overview.today
                  .total_items_sold,
              )
            }}
            unit
          </strong>

          <small>
            Total unit hari ini
          </small>
        </article>
      </section>

      <section class="quick-actions">
        <button
          type="button"
          @click="openReports"
        >
          <strong>Laporan Pendapatan</strong>
          <span>Lihat analitik periode</span>
        </button>

        <button
          type="button"
          @click="openSales"
        >
          <strong>Riwayat Penjualan</strong>
          <span>Lihat seluruh transaksi</span>
        </button>

        <button
          type="button"
          @click="openCashSessions"
        >
          <strong>Riwayat Sesi Kasir</strong>
          <span>Audit selisih uang</span>
        </button>

        <button
          type="button"
          @click="openProducts"
        >
          <strong>Manajemen Produk</strong>
          <span>Periksa stok produk</span>
        </button>
      </section>

      <section class="operation-grid">
        <article class="panel-card">
          <header class="panel-header">
            <div>
              <h2>Sesi Kasir Aktif</h2>

              <p>
                Status meja kasir saat ini.
              </p>
            </div>

            <span
              :class="[
                'status-badge',
                overview.active_cash_session
                  ? 'active'
                  : 'inactive',
              ]"
            >
              {{
                overview.active_cash_session
                  ? 'Open'
                  : 'Tidak Aktif'
              }}
            </span>
          </header>

          <div
            v-if="
              overview.active_cash_session
            "
            class="session-content"
          >
            <div class="session-main">
              <div class="avatar">
                {{
                  overview
                    .active_cash_session
                    .cashier.name
                    .charAt(0)
                    .toUpperCase()
                }}
              </div>

              <div>
                <strong>
                  {{
                    overview
                      .active_cash_session
                      .cashier.name
                  }}
                </strong>

                <span>
                  {{
                    overview
                      .active_cash_session
                      .session_number
                  }}
                </span>
              </div>
            </div>

            <dl class="information-list">
              <div>
                <dt>Dibuka</dt>

                <dd>
                  {{
                    formatDateTime(
                      overview
                        .active_cash_session
                        .opened_at,
                    )
                  }}
                </dd>
              </div>

              <div>
                <dt>Durasi</dt>

                <dd>
                  {{
                    formatDuration(
                      overview
                        .active_cash_session
                        .duration_minutes,
                    )
                  }}
                </dd>
              </div>

              <div>
                <dt>Transaksi</dt>

                <dd>
                  {{
                    overview
                      .active_cash_session
                      .sales_count
                  }}
                </dd>
              </div>

              <div>
                <dt>Penjualan Tunai</dt>

                <dd>
                  {{
                    formatCurrency(
                      overview
                        .active_cash_session
                        .cash_sales_total,
                    )
                  }}
                </dd>
              </div>

              <div>
                <dt>Expected Cash</dt>

                <dd class="highlight">
                  {{
                    formatCurrency(
                      overview
                        .active_cash_session
                        .expected_cash_now,
                    )
                  }}
                </dd>
              </div>
            </dl>

            <button
              type="button"
              class="panel-button"
              @click="
                openCashSession(
                  overview
                    .active_cash_session
                    .id,
                )
              "
            >
              Lihat Detail Sesi
            </button>
          </div>

          <div
            v-else
            class="empty-panel"
          >
            <strong>
              Belum ada sesi kasir aktif
            </strong>

            <p>
              Tidak ada user yang sedang
              menjalankan meja kasir.
            </p>
          </div>
        </article>

        <article class="panel-card">
          <header class="panel-header">
            <div>
              <h2>Rekonsiliasi Terakhir</h2>

              <p>
                Hasil penutupan sesi terbaru.
              </p>
            </div>
          </header>

          <div
            v-if="
              overview
                .latest_cash_difference
            "
            class="difference-content"
          >
            <div
              :class="[
                'difference-result',
                differenceClass(
                  overview
                    .latest_cash_difference
                    .difference_status,
                ),
              ]"
            >
              <span>
                {{
                  differenceLabel(
                    overview
                      .latest_cash_difference
                      .difference_status,
                  )
                }}
              </span>

              <strong>
                {{
                  formatCurrency(
                    overview
                      .latest_cash_difference
                      .difference,
                  )
                }}
              </strong>
            </div>

            <dl class="information-list">
              <div>
                <dt>Kasir</dt>

                <dd>
                  {{
                    overview
                      .latest_cash_difference
                      .cashier.name
                  }}
                </dd>
              </div>

              <div>
                <dt>Nomor Sesi</dt>

                <dd>
                  {{
                    overview
                      .latest_cash_difference
                      .session_number
                  }}
                </dd>
              </div>

              <div>
                <dt>Ditutup</dt>

                <dd>
                  {{
                    formatDateTime(
                      overview
                        .latest_cash_difference
                        .closed_at,
                    )
                  }}
                </dd>
              </div>

              <div>
                <dt>Expected Cash</dt>

                <dd>
                  {{
                    formatCurrency(
                      overview
                        .latest_cash_difference
                        .expected_closing_cash,
                    )
                  }}
                </dd>
              </div>

              <div>
                <dt>Actual Cash</dt>

                <dd>
                  {{
                    formatCurrency(
                      overview
                        .latest_cash_difference
                        .actual_closing_cash,
                    )
                  }}
                </dd>
              </div>
            </dl>

            <button
              type="button"
              class="panel-button"
              @click="
                openCashSession(
                  overview
                    .latest_cash_difference
                    .id,
                )
              "
            >
              Lihat Audit Sesi
            </button>
          </div>

          <div
            v-else
            class="empty-panel"
          >
            <strong>
              Belum ada sesi ditutup
            </strong>

            <p>
              Hasil rekonsiliasi kas belum
              tersedia.
            </p>
          </div>
        </article>
      </section>

      <section class="bottom-grid">
        <article class="panel-card">
          <header class="panel-header">
            <div>
              <h2>Stok Menipis</h2>

              <p>
                Produk mencapai batas minimum.
              </p>
            </div>

            <span class="count-badge">
              {{
                overview.inventory
                  .low_stock_count
              }}
              produk
            </span>
          </header>

          <div
            v-if="
              overview.inventory
                .low_stock_products
                .length === 0
            "
            class="empty-panel"
          >
            <strong>Stok masih aman</strong>

            <p>
              Tidak ada produk yang mencapai
              stok minimum.
            </p>
          </div>

          <div
            v-else
            class="stock-list"
          >
            <article
              v-for="
                product in
                overview.inventory
                  .low_stock_products
              "
              :key="product.id"
              class="stock-item"
            >
              <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
              />

              <div
                v-else
                class="product-placeholder"
              >
                {{
                  product.name
                    .charAt(0)
                    .toUpperCase()
                }}
              </div>

              <div class="stock-information">
                <strong>
                  {{ product.name }}
                </strong>

                <span>
                  {{ product.sku }}
                  ·
                  {{
                    product.category
                      ?.name ??
                    'Tanpa kategori'
                  }}
                </span>
              </div>

              <div class="stock-value">
                <strong>
                  {{ product.current_stock }}
                  {{ product.unit }}
                </strong>

                <span>
                  Minimum
                  {{ product.minimum_stock }}
                </span>
              </div>
            </article>
          </div>

          <button
            type="button"
            class="panel-button footer-button"
            @click="openProducts"
          >
            Lihat Semua Produk
          </button>
        </article>

        <article class="panel-card recent-sales-card">
          <header class="panel-header">
            <div>
              <h2>Transaksi Terbaru</h2>

              <p>
                Lima penjualan paling baru.
              </p>
            </div>

            <button
              type="button"
              class="text-button"
              @click="openSales"
            >
              Lihat Semua
            </button>
          </header>

          <div
            v-if="
              overview.recent_sales
                .length === 0
            "
            class="empty-panel"
          >
            <strong>
              Belum ada transaksi
            </strong>

            <p>
              Transaksi terbaru akan muncul
              di bagian ini.
            </p>
          </div>

          <div
            v-else
            class="sale-list"
          >
            <button
              v-for="
                sale in
                  overview.recent_sales
              "
              :key="sale.id"
              type="button"
              class="sale-item"
              @click="openSale(sale)"
            >
              <div>
                <strong>
                  {{ sale.sale_number }}
                </strong>

                <span>
                  {{ sale.cashier.name }}
                  ·
                  {{
                    formatTime(
                      sale.sold_at,
                    )
                  }}
                </span>
              </div>

              <div class="sale-total">
                <strong>
                  {{
                    formatCurrency(
                      sale.total_amount,
                    )
                  }}
                </strong>

                <span>
                  {{ sale.total_items }} item
                  ·
                  {{
                    paymentLabel(
                      sale.payment_method,
                    )
                  }}
                </span>
              </div>
            </button>
          </div>
        </article>
      </section>
    </template>
  </section>
</template>

<style scoped>
.dashboard-page {
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
  font-size: 29px;
}

.page-header p {
  margin: 8px 0 0;
  color: #64748b;
}

.refresh-button {
  min-height: 41px;
  padding: 0 15px;

  border: 1px solid #cbd5e1;
  border-radius: 10px;

  background: white;
  color: #334155;

  font-weight: 700;
}

.refresh-button:disabled {
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
  padding: 80px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: white;
  color: #64748b;
  text-align: center;
}

.summary-grid {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 13px;
}

.summary-card {
  padding: 18px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
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
  font-size: 21px;
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

.summary-card.items strong {
  color: #7c3aed;
}

.quick-actions {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 11px;
}

.quick-actions button {
  padding: 15px;

  border: 1px solid #e2e8f0;
  border-radius: 12px;

  background: white;
  text-align: left;
}

.quick-actions button:hover {
  border-color: #6ee7b7;
  background: #f0fdf4;
}

.quick-actions strong,
.quick-actions span {
  display: block;
}

.quick-actions strong {
  color: #0f172a;
  font-size: 12px;
}

.quick-actions span {
  margin-top: 5px;
  color: #64748b;
  font-size: 10px;
}

.operation-grid,
.bottom-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.panel-card {
  min-width: 0;
  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 14px;

  background: white;
}

.panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 15px;

  padding: 16px 18px;
  border-bottom: 1px solid #e2e8f0;
}

.panel-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 16px;
}

.panel-header p {
  margin: 5px 0 0;
  color: #64748b;
  font-size: 10px;
}

.status-badge,
.count-badge {
  padding: 6px 9px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 800;
}

.status-badge.active {
  background: #dcfce7;
  color: #15803d;
}

.status-badge.inactive {
  background: #e2e8f0;
  color: #64748b;
}

.count-badge {
  background: #fef3c7;
  color: #b45309;
}

.session-content,
.difference-content {
  padding: 18px;
}

.session-main {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 14px;
}

.avatar {
  width: 42px;
  height: 42px;

  display: grid;
  place-items: center;

  border-radius: 12px;
  background: #d1fae5;
  color: #047857;

  font-weight: 800;
}

.session-main strong,
.session-main span {
  display: block;
}

.session-main strong {
  color: #0f172a;
}

.session-main span {
  margin-top: 3px;
  color: #64748b;
  font-size: 10px;
}

.information-list {
  margin: 0;
}

.information-list div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;

  padding: 10px 0;
  border-bottom: 1px solid #e2e8f0;
}

.information-list div:last-child {
  border-bottom: 0;
}

.information-list dt {
  color: #64748b;
  font-size: 10px;
}

.information-list dd {
  margin: 0;
  color: #0f172a;
  font-size: 11px;
  font-weight: 700;
  text-align: right;
}

.information-list dd.highlight {
  color: #047857;
}

.panel-button {
  width: 100%;
  min-height: 39px;
  margin-top: 14px;

  border: 0;
  border-radius: 9px;

  background: #d1fae5;
  color: #047857;

  font-weight: 700;
}

.difference-result {
  margin-bottom: 14px;
  padding: 15px;
  border-radius: 11px;
}

.difference-result span,
.difference-result strong {
  display: block;
}

.difference-result span {
  font-size: 10px;
  font-weight: 700;
}

.difference-result strong {
  margin-top: 6px;
  font-size: 21px;
}

.difference-result.balanced {
  background: #dcfce7;
  color: #15803d;
}

.difference-result.over {
  background: #dbeafe;
  color: #1d4ed8;
}

.difference-result.short {
  background: #fee2e2;
  color: #b91c1c;
}

.difference-result.unknown {
  background: #f1f5f9;
  color: #64748b;
}

.empty-panel {
  padding: 45px 20px;
  color: #64748b;
  text-align: center;
}

.empty-panel strong {
  display: block;
  color: #334155;
}

.empty-panel p {
  margin: 6px 0 0;
  font-size: 11px;
  line-height: 1.5;
}

.stock-list,
.sale-list {
  padding: 6px 18px;
}

.stock-item,
.sale-item {
  display: flex;
  align-items: center;
  gap: 11px;

  padding: 11px 0;
  border-bottom: 1px solid #e2e8f0;
}

.stock-item:last-child,
.sale-item:last-child {
  border-bottom: 0;
}

.stock-item img,
.product-placeholder {
  width: 40px;
  height: 40px;
  flex: 0 0 auto;
  border-radius: 10px;
}

.stock-item img {
  object-fit: cover;
}

.product-placeholder {
  display: grid;
  place-items: center;
  background: #d1fae5;
  color: #047857;
  font-weight: 800;
}

.stock-information {
  min-width: 0;
  flex: 1;
}

.stock-information strong,
.stock-information span,
.stock-value strong,
.stock-value span {
  display: block;
}

.stock-information strong {
  overflow: hidden;
  color: #0f172a;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stock-information span,
.stock-value span {
  margin-top: 4px;
  color: #94a3b8;
  font-size: 9px;
}

.stock-value {
  text-align: right;
}

.stock-value strong {
  color: #b45309;
  font-size: 11px;
}

.footer-button {
  width: calc(100% - 36px);
  margin: 8px 18px 18px;
}

.text-button {
  padding: 0;
  border: 0;
  background: transparent;
  color: #047857;
  font-size: 10px;
  font-weight: 800;
}

.sale-item {
  width: 100%;
  border-top: 0;
  border-right: 0;
  border-left: 0;
  background: transparent;
  text-align: left;
}

.sale-item:hover {
  background: #f8fafc;
}

.sale-item > div:first-child {
  min-width: 0;
  flex: 1;
}

.sale-item strong,
.sale-item span {
  display: block;
}

.sale-item strong {
  color: #0f172a;
  font-size: 11px;
}

.sale-item span {
  margin-top: 4px;
  color: #94a3b8;
  font-size: 9px;
}

.sale-total {
  text-align: right;
}

.sale-total strong {
  color: #047857;
}

@media (max-width: 1100px) {
  .summary-grid,
  .quick-actions {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 750px) {
  .page-header,
  .operation-grid,
  .bottom-grid {
    grid-template-columns: 1fr;
  }

  .page-header {
    flex-direction: column;
  }
}

@media (max-width: 520px) {
  .summary-grid,
  .quick-actions {
    grid-template-columns: 1fr;
  }
}
</style>