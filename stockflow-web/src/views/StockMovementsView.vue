<script setup lang="ts">
import axios from 'axios'
import {
  onMounted,
  reactive,
  ref,
} from 'vue'
import { useRouter } from 'vue-router'

import { getProducts } from '@/services/productService'
import {
  getStockMovements,
} from '@/services/stockMovementService'

import type { ProductItem } from '@/types/product'

import type {
  StockMovementFilters,
  StockMovementItem,
  StockMovementPaginationMeta,
  StockMovementValidationErrors,
} from '@/types/stockMovement'

interface ApiErrorResponse {
  message?: string

  errors?:
    StockMovementValidationErrors
}

const router = useRouter()

const stockMovements =
  ref<StockMovementItem[]>([])

const products =
  ref<ProductItem[]>([])

const pagination =
  ref<StockMovementPaginationMeta | null>(
    null,
  )

const isLoading = ref(false)
const errorMessage = ref('')

const filters =
  reactive<StockMovementFilters>({
    search: '',
    type: '',
    product_id: '',
    date_from: '',
    date_to: '',
    page: 1,
    per_page: 10,
  })

async function loadStockMovements(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response =
      await getStockMovements(filters)

    stockMovements.value =
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

      if (error.response.status === 401) {
        errorMessage.value =
          'Session login sudah tidak valid.'

        return
      }

      if (error.response.status === 403) {
        errorMessage.value =
          error.response.data.message ??
          'Anda tidak memiliki akses ke riwayat stok.'

        return
      }

      if (error.response.status === 422) {
        errorMessage.value =
          error.response.data.message ??
          'Filter riwayat stok tidak valid.'

        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil riwayat stok.'
  } finally {
    isLoading.value = false
  }
}

async function loadProductOptions(): Promise<void> {
  try {
    const response = await getProducts({
      search: '',
      category_id: '',
      status: '',
      stock_status: '',
      page: 1,
      per_page: 100,
    })

    products.value = response.data
  } catch {
    errorMessage.value =
      'Pilihan produk gagal dimuat.'
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1

  await loadStockMovements()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.type = ''
  filters.product_id = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.page = 1

  await loadStockMovements()
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

  await loadStockMovements()
}

function canOpenReference(
  movement: StockMovementItem,
): boolean {
  return (
    movement.reference_id !== null &&
    (
      movement.reference_type ===
        'purchase' ||
      movement.reference_type ===
        'stock_adjustment'
    )
  )
}

function openReference(
  movement: StockMovementItem,
): void {
  if (
    !canOpenReference(movement) ||
    movement.reference_id === null
  ) {
    return
  }

  if (
    movement.reference_type ===
    'purchase'
  ) {
    router.push({
      name: 'purchases.show',

      params: {
        id: movement.reference_id,
      },
    })

    return
  }

  if (
    movement.reference_type ===
    'stock_adjustment'
  ) {
    router.push({
      name:
        'stock-adjustments.show',

      params: {
        id: movement.reference_id,
      },
    })
  }
}

function movementTypeLabel(
  type: StockMovementItem['type'],
): string {
  const labels: Record<
    StockMovementItem['type'],
    string
  > = {
    purchase: 'Pembelian',
    sale: 'Penjualan',
    adjustment: 'Penyesuaian',
  }

  return labels[type]
}

function referenceLabel(
  movement: StockMovementItem,
): string {
  if (
    movement.reference_type ===
      'purchase' &&
    movement.reference_id
  ) {
    return `Pembelian #${movement.reference_id}`
  }

  if (
    movement.reference_type ===
      'stock_adjustment' &&
    movement.reference_id
  ) {
    return `Penyesuaian #${movement.reference_id}`
  }

  if (
    movement.reference_type ===
      'sale' &&
    movement.reference_id
  ) {
    return `Penjualan #${movement.reference_id}`
  }

  return '-'
}

function formatQuantityChange(
  quantity: number,
): string {
  if (quantity > 0) {
    return `+${quantity}`
  }

  return String(quantity)
}

function formatCurrency(
  value: number | null,
  maximumFractionDigits = 0,
): string {
  if (value === null) {
    return '-'
  }

  return new Intl.NumberFormat(
    'id-ID',
    {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits,
    },
  ).format(value)
}

function formatDateTime(
  value: string,
): string {
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

function productInitial(
  name: string,
): string {
  return name
    .charAt(0)
    .toUpperCase()
}

onMounted(async () => {
  await Promise.all([
    loadStockMovements(),
    loadProductOptions(),
  ])
})
</script>

<template>
  <section class="movements-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Inventory
        </p>

        <h2>Riwayat Stok</h2>

        <p class="subtitle">
          Lihat seluruh barang masuk, barang
          keluar, dan perubahan average cost
          produk.
        </p>
      </div>
    </header>

    <section class="filter-card">
      <form
        class="filter-grid"
        @submit.prevent="applyFilters"
      >
        <div class="form-group search-field">
          <label for="movement-search">
            Pencarian
          </label>

          <input
            id="movement-search"
            v-model="filters.search"
            type="search"
            placeholder="Cari nama, SKU, atau catatan..."
          />
        </div>

        <div class="form-group">
          <label for="movement-type">
            Jenis
          </label>

          <select
            id="movement-type"
            v-model="filters.type"
          >
            <option value="">
              Semua jenis
            </option>

            <option value="purchase">
              Pembelian
            </option>

            <option value="sale">
              Penjualan
            </option>

            <option value="adjustment">
              Penyesuaian
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="movement-product">
            Produk
          </label>

          <select
            id="movement-product"
            v-model="filters.product_id"
          >
            <option value="">
              Semua produk
            </option>

            <option
              v-for="product in products"
              :key="product.id"
              :value="product.id"
            >
              {{ product.sku }} —
              {{ product.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="movement-date-from">
            Dari Tanggal
          </label>

          <input
            id="movement-date-from"
            v-model="filters.date_from"
            type="date"
          />
        </div>

        <div class="form-group">
          <label for="movement-date-to">
            Sampai Tanggal
          </label>

          <input
            id="movement-date-to"
            v-model="filters.date_to"
            type="date"
            :min="
              filters.date_from ||
              undefined
            "
          />
        </div>

        <div class="filter-actions">
          <button
            type="submit"
            class="filter-button"
          >
            Terapkan
          </button>

          <button
            type="button"
            class="reset-button"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </form>
    </section>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <section class="table-card">
      <div
        v-if="isLoading"
        class="state-message"
      >
        Memuat riwayat pergerakan stok...
      </div>

      <div
        v-else-if="
          stockMovements.length === 0
        "
        class="empty-state"
      >
        <div class="empty-icon">
          ⇅
        </div>

        <h3>
          Belum ada pergerakan stok
        </h3>

        <p>
          Pergerakan stok akan otomatis
          tercatat setelah ada pembelian,
          penjualan, atau penyesuaian stok.
        </p>
      </div>

      <div
        v-else
        class="table-wrapper"
      >
        <table>
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Produk</th>
              <th>Jenis</th>
              <th>Perubahan</th>
              <th>Stok</th>
              <th>Harga Satuan</th>
              <th>Average Cost</th>
              <th>Diproses Oleh</th>
              <th>Referensi</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                movement in stockMovements
              "
              :key="movement.id"
            >
              <td>
                <span class="date-text">
                  {{
                    formatDateTime(
                      movement.movement_at,
                    )
                  }}
                </span>
              </td>

              <td>
                <div class="product-cell">
                  <img
                    v-if="
                      movement.product.image_url
                    "
                    :src="
                      movement.product.image_url
                    "
                    :alt="
                      movement.product.name
                    "
                    class="product-image"
                  />

                  <div
                    v-else
                    class="product-placeholder"
                  >
                    {{
                      productInitial(
                        movement.product.name,
                      )
                    }}
                  </div>

                  <div class="product-information">
                    <strong>
                      {{
                        movement.product.name
                      }}
                    </strong>

                    <span>
                      {{
                        movement.product.sku
                      }}
                    </span>
                  </div>
                </div>
              </td>

              <td>
                <span
                  :class="[
                    'type-badge',
                    movement.type,
                  ]"
                >
                  {{
                    movementTypeLabel(
                      movement.type,
                    )
                  }}
                </span>
              </td>

              <td>
                <strong
                  :class="[
                    'quantity-change',
                    movement.quantity_change > 0
                      ? 'positive'
                      : 'negative',
                  ]"
                >
                  {{
                    formatQuantityChange(
                      movement.quantity_change,
                    )
                  }}
                  {{ movement.product.unit }}
                </strong>
              </td>

              <td>
                <div class="stock-change">
                  <span>
                    {{ movement.stock_before }}
                  </span>

                  <span class="arrow">
                    →
                  </span>

                  <strong>
                    {{ movement.stock_after }}
                  </strong>
                </div>
              </td>

              <td>
                {{
                  formatCurrency(
                    movement.unit_cost,
                    4,
                  )
                }}
              </td>

              <td>
                <div class="cost-change">
                  <span>
                    {{
                      formatCurrency(
                        movement.average_cost_before,
                        4,
                      )
                    }}
                  </span>

                  <span class="arrow">
                    →
                  </span>

                  <strong>
                    {{
                      formatCurrency(
                        movement.average_cost_after,
                        4,
                      )
                    }}
                  </strong>
                </div>
              </td>

              <td>
                <div class="creator-information">
                  <strong>
                    {{
                      movement.creator.name
                    }}
                  </strong>

                  <span>
                    {{
                      movement.notes ??
                      '-'
                    }}
                  </span>
                </div>
              </td>

              <td>
                <button
                  v-if="
                    canOpenReference(
                      movement,
                    )
                  "
                  type="button"
                  class="reference-button"
                  @click="
                    openReference(
                      movement,
                    )
                  "
                >
                  {{
                    referenceLabel(
                      movement,
                    )
                  }}
                </button>

                <span
                  v-else
                  class="reference-text"
                >
                  {{
                    referenceLabel(
                      movement,
                    )
                  }}
                </span>
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
        <p>
          Menampilkan
          <strong>
            {{ pagination.from }}
          </strong>
          sampai
          <strong>
            {{ pagination.to }}
          </strong>
          dari
          <strong>
            {{ pagination.total }}
          </strong>
          pergerakan stok
        </p>

        <div class="pagination-buttons">
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

          <span>
            Halaman
            {{ pagination.current_page }}
            dari
            {{ pagination.last_page }}
          </span>

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
            Berikutnya
          </button>
        </div>
      </footer>
    </section>
  </section>
</template>

<style scoped>
.movements-page {
  width: 100%;
}

.page-header {
  margin-bottom: 24px;
}

.eyebrow {
  margin: 0 0 7px;
  color: #047857;
  font-size: 12px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.07em;
}

.page-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 28px;
}

.subtitle {
  margin: 9px 0 0;
  color: #64748b;
}

.filter-card,
.table-card {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
  box-shadow:
    0 10px 30px rgb(15 23 42 / 4%);
}

.filter-card {
  margin-bottom: 20px;
  padding: 20px;
}

.filter-grid {
  display: grid;
  grid-template-columns:
    minmax(220px, 1fr)
    155px
    220px
    155px
    155px
    auto;
  gap: 13px;
  align-items: end;
}

.form-group {
  min-width: 0;
  display: grid;
  gap: 7px;
}

.form-group label {
  color: #475569;
  font-size: 13px;
  font-weight: 700;
}

.form-group input,
.form-group select {
  width: 100%;
  height: 44px;
  padding: 0 12px;

  border: 1px solid #cbd5e1;
  border-radius: 10px;

  outline: none;
  background: white;
  color: #0f172a;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.filter-actions {
  display: flex;
  gap: 8px;
}

.filter-button,
.reset-button,
.pagination button,
.reference-button {
  border: 0;
  border-radius: 10px;
  font-weight: 700;
}

.filter-button,
.reset-button {
  height: 44px;
  padding: 0 15px;
}

.filter-button {
  background: #0f172a;
  color: white;
}

.reset-button {
  background: #e2e8f0;
  color: #334155;
}

.alert-error {
  margin-bottom: 18px;
  padding: 13px 15px;

  border: 1px solid #fecaca;
  border-radius: 11px;

  background: #fef2f2;
  color: #b91c1c;
}

.table-card {
  overflow: hidden;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1450px;
  border-collapse: collapse;
}

th,
td {
  padding: 16px 18px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

th {
  background: #f8fafc;
  color: #64748b;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

td {
  color: #334155;
  font-size: 13px;
  vertical-align: middle;
}

.date-text {
  display: block;
  width: 115px;
  line-height: 1.5;
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 11px;
}

.product-image,
.product-placeholder {
  width: 45px;
  height: 45px;
  flex: 0 0 auto;
  border-radius: 11px;
}

.product-image {
  border: 1px solid #e2e8f0;
  object-fit: cover;
}

.product-placeholder {
  display: grid;
  place-items: center;

  background: #d1fae5;
  color: #047857;

  font-size: 17px;
  font-weight: 800;
}

.product-information strong,
.product-information span {
  display: block;
}

.product-information strong {
  color: #0f172a;
}

.product-information span {
  margin-top: 4px;
  color: #64748b;
  font-size: 11px;
}

.type-badge {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 750;
}

.type-badge.purchase {
  background: #dcfce7;
  color: #15803d;
}

.type-badge.sale {
  background: #dbeafe;
  color: #1d4ed8;
}

.type-badge.adjustment {
  background: #fef3c7;
  color: #b45309;
}

.quantity-change {
  white-space: nowrap;
}

.quantity-change.positive {
  color: #15803d;
}

.quantity-change.negative {
  color: #dc2626;
}

.stock-change,
.cost-change {
  display: flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
}

.stock-change span,
.cost-change span {
  color: #64748b;
}

.arrow {
  color: #94a3b8 !important;
}

.stock-change strong {
  color: #0f172a;
}

.cost-change strong {
  color: #047857;
}

.creator-information {
  max-width: 210px;
}

.creator-information strong,
.creator-information span {
  display: block;
}

.creator-information strong {
  color: #0f172a;
}

.creator-information span {
  margin-top: 4px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;

  color: #64748b;
  font-size: 11px;
}

.reference-button {
  padding: 8px 11px;
  background: #d1fae5;
  color: #047857;
  white-space: nowrap;
}

.reference-button:hover {
  background: #a7f3d0;
}

.reference-text {
  color: #94a3b8;
}

.state-message,
.empty-state {
  padding: 65px 24px;
  text-align: center;
}

.state-message {
  color: #64748b;
}

.empty-state {
  display: grid;
  justify-items: center;
}

.empty-icon {
  width: 56px;
  height: 56px;

  display: grid;
  place-items: center;

  border-radius: 16px;
  background: #d1fae5;
  color: #047857;

  font-size: 27px;
  font-weight: 800;
}

.empty-state h3 {
  margin: 17px 0 7px;
  color: #0f172a;
}

.empty-state p {
  max-width: 470px;
  margin: 0;

  color: #64748b;
  line-height: 1.6;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;

  padding: 17px 18px;
}

.pagination p {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.pagination-buttons {
  display: flex;
  align-items: center;
  gap: 11px;
}

.pagination-buttons button {
  padding: 9px 12px;
  background: #e2e8f0;
  color: #334155;
}

.pagination-buttons button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.pagination-buttons span {
  color: #64748b;
  font-size: 13px;
}

@media (max-width: 1250px) {
  .filter-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .search-field {
    grid-column: 1 / -1;
  }
}

@media (max-width: 650px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }

  .search-field {
    grid-column: auto;
  }

  .filter-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .pagination-buttons {
    justify-content: space-between;
  }
}
</style>