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
  getSale,
} from '@/services/saleService'

import type {
  SaleRecord,
} from '@/types/sale'

interface ApiErrorResponse {
  message?: string
}

const route = useRoute()
const router = useRouter()

const sale =
  ref<SaleRecord | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const saleId = computed(() => {
  return Number(route.params.id)
})

async function loadSale():
  Promise<void> {
  if (
    !Number.isInteger(saleId.value) ||
    saleId.value <= 0
  ) {
    errorMessage.value =
      'ID transaksi tidak valid.'

    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    sale.value =
      await getSale(saleId.value)
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
          'Transaksi tidak ditemukan.'

        return
      }

      errorMessage.value =
        error.response.data.message ??
        'Detail transaksi gagal dimuat.'

      return
    }

    errorMessage.value =
      'Detail transaksi gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

function goBack(): void {
  router.push({
    name: 'sales.index',
  })
}

function openCashSession(): void {
  if (!sale.value) {
    return
  }

  router.push({
    name: 'cash-sessions.show',

    params: {
      id: sale.value.cash_session.id,
    },
  })
}

function printReceipt(): void {
  window.print()
}

function formatCurrency(
  value: number | undefined,
): string {
  if (value === undefined) {
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

onMounted(() => {
  loadSale()
})
</script>

<template>
  <section class="page">
    <div class="page-actions no-print">
      <button
        type="button"
        class="secondary-button"
        @click="goBack"
      >
        ← Kembali
      </button>

      <button
        type="button"
        class="print-button"
        @click="printReceipt"
      >
        Cetak Nota
      </button>
    </div>

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
      Memuat detail transaksi...
    </div>

    <template v-else-if="sale">
      <header class="detail-header">
        <div>
          <p class="eyebrow">
            Detail Transaksi
          </p>

          <h1>
            {{ sale.sale_number }}
          </h1>

          <p>
            {{
              formatDateTime(
                sale.sold_at,
              )
            }}
          </p>
        </div>

        <span class="status-badge">
          Completed
        </span>
      </header>

      <section class="summary-grid">
        <article>
          <span>Kasir</span>
          <strong>
            {{ sale.cashier.name }}
          </strong>
        </article>

        <article>
          <span>Sesi Kasir</span>

          <button
            type="button"
            class="session-link"
            @click="openCashSession"
          >
            {{
              sale.cash_session
                .session_number
            }}
          </button>
        </article>

        <article>
          <span>Metode Pembayaran</span>
          <strong>Cash / Tunai</strong>
        </article>

        <article>
          <span>Jumlah Produk</span>
          <strong>
            {{ sale.items_count ?? 0 }}
            jenis
          </strong>
        </article>
      </section>

      <section class="items-card">
        <header>
          <h2>Produk Terjual</h2>
        </header>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Harga Jual</th>
                <th>Subtotal</th>
                <th>Diskon</th>
                <th>Net Sales</th>
                <th>HPP</th>
                <th>Laba</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="item in sale.items"
                :key="item.id"
              >
                <td>
                  <strong>
                    {{ item.product.name }}
                  </strong>

                  <span class="subtext">
                    {{ item.product.sku }}
                  </span>
                </td>

                <td>
                  {{ item.quantity }}
                  {{ item.product.unit }}
                </td>

                <td>
                  {{
                    formatCurrency(
                      item.selling_price,
                    )
                  }}
                </td>

                <td>
                  {{
                    formatCurrency(
                      item.subtotal,
                    )
                  }}
                </td>

                <td class="discount-text">
                  −
                  {{
                    formatCurrency(
                      item.allocated_discount,
                    )
                  }}
                </td>

                <td>
                  {{
                    formatCurrency(
                      item.net_sales,
                    )
                  }}
                </td>

                <td>
                  {{
                    formatCurrency(
                      item.cost_total,
                    )
                  }}
                </td>

                <td class="profit-text">
                  {{
                    formatCurrency(
                      item.gross_profit,
                    )
                  }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="bottom-grid">
        <article class="detail-card">
          <header>
            <h2>Pembayaran</h2>
          </header>

          <dl>
            <div>
              <dt>Subtotal</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.subtotal,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Diskon</dt>
              <dd class="discount-text">
                −
                {{
                  formatCurrency(
                    sale.discount_amount,
                  )
                }}
              </dd>
            </div>

            <div class="grand-total">
              <dt>Total Bayar</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.total_amount,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Uang Diterima</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.amount_paid,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Kembalian</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.change_amount,
                  )
                }}
              </dd>
            </div>
          </dl>
        </article>

        <article class="detail-card">
          <header>
            <h2>Profitabilitas</h2>
          </header>

          <dl>
            <div>
              <dt>Penjualan Bersih</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.total_amount,
                  )
                }}
              </dd>
            </div>

            <div>
              <dt>Total HPP</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.total_cost,
                  )
                }}
              </dd>
            </div>

            <div class="profit-total">
              <dt>Laba Kotor</dt>
              <dd>
                {{
                  formatCurrency(
                    sale.gross_profit,
                  )
                }}
              </dd>
            </div>
          </dl>
        </article>
      </section>

      <section
        v-if="sale.promotion || sale.notes"
        class="bottom-grid"
      >
        <article
          v-if="sale.promotion"
          class="detail-card"
        >
          <header>
            <h2>Promo</h2>
          </header>

          <dl>
            <div>
              <dt>Nama</dt>
              <dd>
                {{ sale.promotion.name }}
              </dd>
            </div>

            <div>
              <dt>Kode</dt>
              <dd>
                {{ sale.promotion.code }}
              </dd>
            </div>
          </dl>
        </article>

        <article
          v-if="sale.notes"
          class="detail-card"
        >
          <header>
            <h2>Catatan</h2>
          </header>

          <p class="notes">
            {{ sale.notes }}
          </p>
        </article>
      </section>
    </template>
  </section>
</template>

<style scoped>
.page {
  display: grid;
  gap: 18px;
}

.page-actions {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.secondary-button,
.print-button {
  min-height: 40px;
  padding: 0 14px;
  border: 0;
  border-radius: 9px;
  font-weight: 700;
}

.secondary-button {
  background: #e2e8f0;
  color: #334155;
}

.print-button {
  background: #047857;
  color: white;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: white;
}

.eyebrow {
  margin: 0 0 5px;
  color: #047857;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.detail-header h1 {
  margin: 0;
  color: #0f172a;
  font-size: 25px;
}

.detail-header p {
  margin: 7px 0 0;
  color: #64748b;
}

.status-badge {
  padding: 7px 10px;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 10px;
  font-weight: 800;
}

.summary-grid {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.summary-grid article {
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: white;
}

.summary-grid span,
.summary-grid strong {
  display: block;
}

.summary-grid span {
  color: #64748b;
  font-size: 10px;
}

.summary-grid strong {
  margin-top: 6px;
  color: #0f172a;
}

.session-link {
  margin-top: 6px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #047857;
  font: inherit;
  font-weight: 800;
}

.items-card,
.detail-card {
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: white;
}

.items-card > header,
.detail-card > header {
  padding: 15px 17px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.items-card h2,
.detail-card h2 {
  margin: 0;
  color: #0f172a;
  font-size: 15px;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1000px;
  border-collapse: collapse;
}

th,
td {
  padding: 13px 14px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  font-size: 11px;
}

th {
  color: #64748b;
  font-size: 9px;
  text-transform: uppercase;
}

.subtext {
  display: block;
  margin-top: 4px;
  color: #94a3b8;
  font-size: 9px;
}

.bottom-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.detail-card dl {
  margin: 0;
  padding: 5px 17px;
}

.detail-card dl div {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 12px 0;
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
}

.grand-total dd {
  color: #047857;
  font-size: 18px;
}

.profit-total dd,
.profit-text {
  color: #1d4ed8 !important;
}

.discount-text {
  color: #dc2626 !important;
}

.notes {
  margin: 0;
  padding: 17px;
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

@media (max-width: 850px) {
  .summary-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .bottom-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 500px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }

  .detail-header {
    flex-direction: column;
  }
}

@media print {
  .no-print {
    display: none !important;
  }

  .page {
    gap: 10px;
  }

  .detail-header,
  .summary-grid article,
  .items-card,
  .detail-card {
    box-shadow: none;
  }
}
</style>