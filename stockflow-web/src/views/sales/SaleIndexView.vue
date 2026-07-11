<script setup lang="ts">
import axios from 'axios'
import {
  onMounted,
  reactive,
  ref,
} from 'vue'
import {
  useRoute,
  useRouter,
} from 'vue-router'

import {
  getSales,
} from '@/services/saleService'

import type {
  SaleFilters,
  SalePaginationMeta,
  SaleRecord,
} from '@/types/sale'

interface ApiErrorResponse {
  message?: string
}

const route = useRoute()
const router = useRouter()

const sales = ref<SaleRecord[]>([])

const pagination =
  ref<SalePaginationMeta | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const routeSessionId =
  Number(route.query.cash_session_id)

const filters =
  reactive<SaleFilters>({
    search: '',

    payment_method: '',

    cash_session_id:
      Number.isInteger(routeSessionId) &&
      routeSessionId > 0
        ? routeSessionId
        : '',

    date_from: '',
    date_to: '',

    page: 1,
    per_page: 10,
  })

async function loadSales():
  Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response =
      await getSales(filters)

    sales.value = response.data
    pagination.value = response.meta
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
        'Riwayat penjualan gagal dimuat.'

      return
    }

    errorMessage.value =
      'Riwayat penjualan gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

async function applyFilters():
  Promise<void> {
  filters.page = 1

  await updateRouteQuery()
  await loadSales()
}

async function resetFilters():
  Promise<void> {
  filters.search = ''
  filters.payment_method = ''
  filters.cash_session_id = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.page = 1

  await router.replace({
    name: 'sales.index',
  })

  await loadSales()
}

async function updateRouteQuery():
  Promise<void> {
  await router.replace({
    name: 'sales.index',

    query: {
      cash_session_id:
        filters.cash_session_id === ''
          ? undefined
          : String(
              filters.cash_session_id,
            ),
    },
  })
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

  await loadSales()
}

function openDetail(
  sale: SaleRecord,
): void {
  router.push({
    name: 'sales.show',

    params: {
      id: sale.id,
    },
  })
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
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    },
  ).format(new Date(value))
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

onMounted(() => {
  loadSales()
})
</script>

<template>
  <section class="page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Transaksi
        </p>

        <h1>Riwayat Penjualan</h1>

        <p>
          Lihat seluruh transaksi, pembayaran,
          diskon, dan laba penjualan.
        </p>
      </div>

      <span
        v-if="filters.cash_session_id !== ''"
        class="session-filter-badge"
      >
        Filter sesi #{{ filters.cash_session_id }}
      </span>
    </header>

    <form
      class="filter-card"
      @submit.prevent="applyFilters"
    >
      <div class="search-field">
        <label for="sale-search">
          Cari Transaksi
        </label>

        <input
          id="sale-search"
          v-model="filters.search"
          type="search"
          placeholder="Nomor transaksi, produk, SKU, atau promo"
        />
      </div>

      <div>
        <label for="payment-method">
          Pembayaran
        </label>

        <select
          id="payment-method"
          v-model="filters.payment_method"
        >
          <option value="">
            Semua metode
          </option>

          <option value="cash">
            Tunai
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

      <div>
        <label for="sale-date-from">
          Dari Tanggal
        </label>

        <input
          id="sale-date-from"
          v-model="filters.date_from"
          type="date"
        />
      </div>

      <div>
        <label for="sale-date-to">
          Sampai Tanggal
        </label>

        <input
          id="sale-date-to"
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
        Memuat riwayat penjualan...
      </div>

      <div
        v-else-if="sales.length === 0"
        class="table-state"
      >
        Belum ada transaksi yang sesuai
        dengan filter.
      </div>

      <div
        v-else
        class="table-wrapper"
      >
        <table>
          <thead>
            <tr>
              <th>Transaksi</th>
              <th>Kasir &amp; Sesi</th>
              <th>Barang</th>
              <th>Subtotal</th>
              <th>Diskon</th>
              <th>Total</th>
              <th>Pembayaran</th>
              <th>Laba Kotor</th>
              <th />
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="sale in sales"
              :key="sale.id"
            >
              <td>
                <strong class="sale-number">
                  {{ sale.sale_number }}
                </strong>

                <span class="subtext">
                  {{
                    formatDateTime(
                      sale.sold_at,
                    )
                  }}
                </span>
              </td>

              <td>
                <strong>
                  {{ sale.cashier.name }}
                </strong>

                <span class="subtext">
                  {{
                    sale.cash_session
                      .session_number
                  }}
                </span>
              </td>

              <td>
                {{ sale.items_count ?? 0 }}
                jenis
              </td>

              <td>
                {{
                  formatCurrency(
                    sale.subtotal,
                  )
                }}
              </td>

              <td>
                <span class="discount-text">
                  −
                  {{
                    formatCurrency(
                      sale.discount_amount,
                    )
                  }}
                </span>
              </td>

              <td>
                <strong class="total-text">
                  {{
                    formatCurrency(
                      sale.total_amount,
                    )
                  }}
                </strong>
              </td>

              <td>
                <span class="payment-badge">
                  {{
                    paymentLabel(
                      sale.payment_method,
                    )
                  }}
                </span>
              </td>

              <td>
                <strong class="profit-text">
                  {{
                    formatCurrency(
                      sale.gross_profit,
                    )
                  }}
                </strong>
              </td>

              <td>
                <button
                  type="button"
                  class="detail-button"
                  @click="openDetail(sale)"
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
          transaksi
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

.session-filter-badge {
  padding: 8px 11px;
  border-radius: 999px;
  background: #d1fae5;
  color: #047857;
  font-size: 11px;
  font-weight: 800;
}

.filter-card {
  display: grid;
  grid-template-columns:
    minmax(260px, 1.7fr)
    repeat(3, minmax(150px, 1fr))
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
  min-width: 1180px;
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

.sale-number {
  color: #0f172a;
}

.subtext {
  display: block;
  margin-top: 4px;
  color: #94a3b8;
  font-size: 10px;
}

.discount-text {
  color: #dc2626;
}

.total-text {
  color: #047857;
}

.profit-text {
  color: #1d4ed8;
}

.payment-badge {
  display: inline-flex;
  padding: 5px 8px;
  border-radius: 999px;
  background: #d1fae5;
  color: #047857;
  font-size: 9px;
  font-weight: 800;
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

@media (max-width: 1050px) {
  .filter-card {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .search-field {
    grid-column: span 2;
  }
}

@media (max-width: 650px) {
  .page-header {
    flex-direction: column;
  }

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