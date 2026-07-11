<script setup lang="ts">
import axios from 'axios'
import {
  onMounted,
  reactive,
  ref,
} from 'vue'
import { useRouter } from 'vue-router'

import {
  getStockAdjustments,
} from '@/services/stockAdjustmentService'

import type {
  StockAdjustmentFilters,
  StockAdjustmentPaginationMeta,
  StockAdjustmentReason,
  StockAdjustmentRecord,
  StockAdjustmentValidationErrors,
} from '@/types/stockAdjustment'

interface ApiErrorResponse {
  message?: string
  errors?: StockAdjustmentValidationErrors
}

const router = useRouter()

const adjustments =
  ref<StockAdjustmentRecord[]>([])

const pagination =
  ref<StockAdjustmentPaginationMeta | null>(
    null,
  )

const isLoading = ref(false)
const errorMessage = ref('')

const filters =
  reactive<StockAdjustmentFilters>({
    search: '',
    reason: '',
    date_from: '',
    date_to: '',
    page: 1,
    per_page: 10,
  })

async function loadAdjustments(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response =
      await getStockAdjustments(filters)

    adjustments.value = response.data
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

      if (error.response.status === 401) {
        errorMessage.value =
          'Session login sudah tidak valid.'

        return
      }

      if (error.response.status === 403) {
        errorMessage.value =
          error.response.data.message ??
          'Anda tidak memiliki akses ke penyesuaian stok.'

        return
      }

      if (error.response.status === 422) {
        errorMessage.value =
          error.response.data.message ??
          'Filter penyesuaian stok tidak valid.'

        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil data penyesuaian stok.'
  } finally {
    isLoading.value = false
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1

  await loadAdjustments()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.reason = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.page = 1

  await loadAdjustments()
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

  await loadAdjustments()
}

function openCreateAdjustment(): void {
  router.push({
    name: 'stock-adjustments.create',
  })
}

function openAdjustmentDetail(
  adjustment: StockAdjustmentRecord,
): void {
  router.push({
    name: 'stock-adjustments.show',

    params: {
      id: adjustment.id,
    },
  })
}

function reasonLabel(
  reason: StockAdjustmentReason,
): string {
  const labels: Record<
    StockAdjustmentReason,
    string
  > = {
    stock_opname: 'Stock Opname',
    damaged: 'Barang Rusak',
    lost: 'Barang Hilang',
    expired: 'Kedaluwarsa',
    correction: 'Koreksi Data',
    other: 'Lainnya',
  }

  return labels[reason]
}

function formatDate(
  value: string,
): string {
  return new Intl.DateTimeFormat(
    'id-ID',
    {
      day: '2-digit',
      month: 'long',
      year: 'numeric',
    },
  ).format(
    new Date(`${value}T00:00:00`),
  )
}

onMounted(() => {
  loadAdjustments()
})
</script>

<template>
  <section class="adjustments-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Inventory
        </p>

        <h2>Penyesuaian Stok</h2>

        <p class="subtitle">
          Catat perbedaan antara stok sistem
          dan jumlah barang fisik sebenarnya.
        </p>
      </div>

      <button
        type="button"
        class="primary-button"
        @click="openCreateAdjustment"
      >
        Catat Penyesuaian
      </button>
    </header>

    <section class="filter-card">
      <form
        class="filter-grid"
        @submit.prevent="applyFilters"
      >
        <div class="form-group search-field">
          <label for="adjustment-search">
            Pencarian
          </label>

          <input
            id="adjustment-search"
            v-model="filters.search"
            type="search"
            placeholder="Cari nomor, produk, SKU, atau catatan..."
          />
        </div>

        <div class="form-group">
          <label for="adjustment-reason">
            Alasan
          </label>

          <select
            id="adjustment-reason"
            v-model="filters.reason"
          >
            <option value="">
              Semua alasan
            </option>

            <option value="stock_opname">
              Stock opname
            </option>

            <option value="damaged">
              Barang rusak
            </option>

            <option value="lost">
              Barang hilang
            </option>

            <option value="expired">
              Kedaluwarsa
            </option>

            <option value="correction">
              Koreksi data
            </option>

            <option value="other">
              Lainnya
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="adjustment-date-from">
            Dari Tanggal
          </label>

          <input
            id="adjustment-date-from"
            v-model="filters.date_from"
            type="date"
          />
        </div>

        <div class="form-group">
          <label for="adjustment-date-to">
            Sampai Tanggal
          </label>

          <input
            id="adjustment-date-to"
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
        Memuat penyesuaian stok...
      </div>

      <div
        v-else-if="adjustments.length === 0"
        class="empty-state"
      >
        <div class="empty-icon">
          ±
        </div>

        <h3>
          Belum ada penyesuaian stok
        </h3>

        <p>
          Catat penyesuaian ketika jumlah
          barang fisik berbeda dengan stok
          yang tercatat di sistem.
        </p>

        <button
          type="button"
          class="primary-button"
          @click="openCreateAdjustment"
        >
          Catat Penyesuaian
        </button>
      </div>

      <div
        v-else
        class="table-wrapper"
      >
        <table>
          <thead>
            <tr>
              <th>Nomor Penyesuaian</th>
              <th>Tanggal</th>
              <th>Alasan</th>
              <th>Jumlah Produk</th>
              <th>Dicatat Oleh</th>
              <th>Catatan</th>
              <th>Status</th>
              <th class="action-column">
                Aksi
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                adjustment in adjustments
              "
              :key="adjustment.id"
            >
              <td>
                <strong class="adjustment-number">
                  {{
                    adjustment.adjustment_number
                  }}
                </strong>
              </td>

              <td>
                {{
                  formatDate(
                    adjustment.adjustment_date,
                  )
                }}
              </td>

              <td>
                <span
                  :class="[
                    'reason-badge',
                    adjustment.reason,
                  ]"
                >
                  {{
                    reasonLabel(
                      adjustment.reason,
                    )
                  }}
                </span>
              </td>

              <td>
                {{
                  adjustment.items_count
                }}
                produk
              </td>

              <td>
                {{
                  adjustment.creator.name
                }}
              </td>

              <td>
                <span class="notes-text">
                  {{
                    adjustment.notes ?? '-'
                  }}
                </span>
              </td>

              <td>
                <span class="status-badge">
                  Selesai
                </span>
              </td>

              <td class="action-cell">
                <button
                  type="button"
                  class="detail-button"
                  @click="
                    openAdjustmentDetail(
                      adjustment,
                    )
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
          penyesuaian
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
.adjustments-page {
  width: 100%;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
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

.primary-button,
.filter-button,
.reset-button,
.detail-button,
.pagination button {
  border: 0;
  border-radius: 10px;
  font-weight: 700;
}

.primary-button {
  padding: 12px 18px;
  background: #047857;
  color: white;
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
    minmax(250px, 1fr)
    180px
    165px
    165px
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
  min-width: 1120px;
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

.adjustment-number {
  color: #0f172a;
  white-space: nowrap;
}

.reason-badge,
.status-badge {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 750;
  white-space: nowrap;
}

.reason-badge.stock_opname {
  background: #dbeafe;
  color: #1d4ed8;
}

.reason-badge.damaged,
.reason-badge.expired {
  background: #fee2e2;
  color: #b91c1c;
}

.reason-badge.lost {
  background: #ffedd5;
  color: #c2410c;
}

.reason-badge.correction {
  background: #fef3c7;
  color: #b45309;
}

.reason-badge.other {
  background: #f1f5f9;
  color: #475569;
}

.status-badge {
  background: #dcfce7;
  color: #15803d;
}

.notes-text {
  display: block;
  max-width: 230px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #64748b;
}

.action-column,
.action-cell {
  width: 90px;
  text-align: right;
}

.detail-button {
  padding: 8px 12px;
  background: #e2e8f0;
  color: #334155;
}

.detail-button:hover {
  background: #cbd5e1;
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

  font-size: 26px;
  font-weight: 800;
}

.empty-state h3 {
  margin: 17px 0 7px;
  color: #0f172a;
}

.empty-state p {
  max-width: 460px;
  margin: 0 0 20px;
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

@media (max-width: 1100px) {
  .filter-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .search-field {
    grid-column: 1 / -1;
  }
}

@media (max-width: 650px) {
  .page-header {
    flex-direction: column;
  }

  .primary-button {
    width: 100%;
  }

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