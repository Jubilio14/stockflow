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
  getStockAdjustment,
} from '@/services/stockAdjustmentService'

import type {
  StockAdjustmentReason,
  StockAdjustmentRecord,
  StockAdjustmentValidationErrors,
} from '@/types/stockAdjustment'

interface ApiErrorResponse {
  message?: string
  errors?: StockAdjustmentValidationErrors
}

const route = useRoute()
const router = useRouter()

const adjustment =
  ref<StockAdjustmentRecord | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const adjustmentId = computed(() => {
  const id = Number(route.params.id)

  if (
    !Number.isInteger(id) ||
    id <= 0
  ) {
    return null
  }

  return id
})

const adjustmentItems = computed(() => {
  return adjustment.value?.items ?? []
})

const totalQuantityChange = computed(() => {
  return adjustmentItems.value.reduce(
    (total, item) => {
      return (
        total +
        Number(item.quantity_change)
      )
    },
    0,
  )
})

const totalInventoryValueChange =
  computed(() => {
    return adjustmentItems.value.reduce(
      (total, item) => {
        return (
          total +
          Number(
            item.inventory_value_change,
          )
        )
      },
      0,
    )
  })

const increasedProductCount = computed(() => {
  return adjustmentItems.value.filter(
    (item) =>
      item.quantity_change > 0,
  ).length
})

const decreasedProductCount = computed(() => {
  return adjustmentItems.value.filter(
    (item) =>
      item.quantity_change < 0,
  ).length
})

async function loadAdjustment(): Promise<void> {
  if (adjustmentId.value === null) {
    errorMessage.value =
      'ID penyesuaian stok tidak valid.'

    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    adjustment.value =
      await getStockAdjustment(
        adjustmentId.value,
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

      if (
        error.response.status === 401
      ) {
        errorMessage.value =
          'Session login sudah tidak valid.'

        return
      }

      if (
        error.response.status === 403
      ) {
        errorMessage.value =
          error.response.data.message ??
          'Anda tidak memiliki akses ke detail penyesuaian stok.'

        return
      }

      if (
        error.response.status === 404
      ) {
        errorMessage.value =
          'Penyesuaian stok tidak ditemukan.'

        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil detail penyesuaian stok.'
  } finally {
    isLoading.value = false
  }
}

function goBack(): void {
  router.push({
    name: 'stock-adjustments',
  })
}

function openStockMovements(): void {
  router.push({
    name: 'stock-movements',
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
    expired: 'Barang Kedaluwarsa',
    correction: 'Koreksi Data',
    other: 'Lainnya',
  }

  return labels[reason]
}

function statusLabel(
  status: string,
): string {
  if (status === 'completed') {
    return 'Selesai'
  }

  return status
}

function formatQuantityChange(
  quantity: number,
): string {
  if (quantity > 0) {
    return `+${quantity}`
  }

  return String(quantity)
}

function changeClass(
  quantity: number,
): string {
  if (quantity > 0) {
    return 'positive'
  }

  if (quantity < 0) {
    return 'negative'
  }

  return 'neutral'
}

function formatCurrency(
  value: number,
  maximumFractionDigits = 2,
): string {
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

onMounted(() => {
  loadAdjustment()
})
</script>

<template>
  <section class="adjustment-detail-page">
    <header class="page-header">
      <div>
        <button
          type="button"
          class="back-button"
          @click="goBack"
        >
          ← Kembali ke Penyesuaian Stok
        </button>

        <p class="eyebrow">
          Inventory
        </p>

        <h2>
          Detail Penyesuaian Stok
        </h2>

        <p class="subtitle">
          Lihat stok sebelum dan sesudah
          penyesuaian serta perubahan nilai
          persediaan produk.
        </p>
      </div>

      <button
        v-if="adjustment"
        type="button"
        class="movement-button"
        @click="openStockMovements"
      >
        Lihat Riwayat Stok
      </button>
    </header>

    <div
      v-if="isLoading"
      class="state-card"
    >
      Memuat detail penyesuaian stok...
    </div>

    <div
      v-else-if="errorMessage"
      class="error-state"
    >
      <div class="error-icon">
        !
      </div>

      <h3>
        Detail tidak dapat dimuat
      </h3>

      <p>
        {{ errorMessage }}
      </p>

      <button
        type="button"
        class="secondary-button"
        @click="goBack"
      >
        Kembali ke Daftar
      </button>
    </div>

    <template v-else-if="adjustment">
      <section class="transaction-card">
        <div class="transaction-heading">
          <div>
            <span class="transaction-label">
              Nomor Penyesuaian
            </span>

            <h3>
              {{
                adjustment.adjustment_number
              }}
            </h3>

            <p>
              Dicatat pada
              {{
                formatDateTime(
                  adjustment.created_at,
                )
              }}
            </p>
          </div>

          <span class="status-badge">
            {{
              statusLabel(
                adjustment.status,
              )
            }}
          </span>
        </div>

        <div class="transaction-information">
          <div>
            <span>
              Tanggal Penyesuaian
            </span>

            <strong>
              {{
                formatDate(
                  adjustment.adjustment_date,
                )
              }}
            </strong>
          </div>

          <div>
            <span>
              Alasan
            </span>

            <strong>
              {{
                reasonLabel(
                  adjustment.reason,
                )
              }}
            </strong>
          </div>

          <div>
            <span>
              Dicatat Oleh
            </span>

            <strong>
              {{
                adjustment.creator.name
              }}
            </strong>
          </div>

          <div>
            <span>
              Jumlah Produk
            </span>

            <strong>
              {{ adjustmentItems.length }}
              produk
            </strong>
          </div>
        </div>

        <div
          v-if="adjustment.notes"
          class="notes-section"
        >
          <span>
            Catatan
          </span>

          <p>
            {{ adjustment.notes }}
          </p>
        </div>
      </section>

      <section class="summary-grid">
        <article class="summary-card">
          <span>
            Produk Disesuaikan
          </span>

          <strong>
            {{ adjustmentItems.length }}
          </strong>

          <small>
            Produk berbeda
          </small>
        </article>

        <article class="summary-card">
          <span>
            Total Perubahan Stok
          </span>

          <strong
            :class="
              changeClass(
                totalQuantityChange,
              )
            "
          >
            {{
              formatQuantityChange(
                totalQuantityChange,
              )
            }}
          </strong>

          <small>
            {{ increasedProductCount }}
            bertambah,
            {{ decreasedProductCount }}
            berkurang
          </small>
        </article>

        <article class="summary-card">
          <span>
            Perubahan Nilai Persediaan
          </span>

          <strong
            :class="
              changeClass(
                totalInventoryValueChange,
              )
            "
          >
            {{
              formatCurrency(
                totalInventoryValueChange,
                4,
              )
            }}
          </strong>

          <small>
            Berdasarkan average cost
          </small>
        </article>
      </section>

      <section class="items-card">
        <header class="card-header">
          <div>
            <h3>
              Rincian Produk
            </h3>

            <p>
              Data berikut merupakan snapshot
              stok saat penyesuaian diproses.
            </p>
          </div>
        </header>

        <div
          v-if="
            adjustmentItems.length === 0
          "
          class="empty-items"
        >
          Tidak ada rincian produk pada
          penyesuaian ini.
        </div>

        <div
          v-else
          class="table-wrapper"
        >
          <table>
            <thead>
              <tr>
                <th>Produk</th>
                <th>Stok Sistem</th>
                <th>Stok Fisik</th>
                <th>Perubahan</th>
                <th>Average Cost</th>
                <th>
                  Perubahan Nilai
                  Persediaan
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="
                  item in adjustmentItems
                "
                :key="item.id"
              >
                <td>
                  <div class="product-cell">
                    <img
                      v-if="
                        item.product.image_url
                      "
                      :src="
                        item.product.image_url
                      "
                      :alt="
                        item.product.name
                      "
                      class="product-image"
                    />

                    <div
                      v-else
                      class="product-placeholder"
                    >
                      {{
                        productInitial(
                          item.product.name,
                        )
                      }}
                    </div>

                    <div class="product-information">
                      <strong>
                        {{
                          item.product.name
                        }}
                      </strong>

                      <span>
                        SKU:
                        {{
                          item.product.sku
                        }}
                      </span>
                    </div>
                  </div>
                </td>

                <td>
                  <div class="stock-value">
                    <strong>
                      {{ item.system_stock }}
                    </strong>

                    <span>
                      {{ item.product.unit }}
                    </span>
                  </div>
                </td>

                <td>
                  <div class="stock-value">
                    <strong>
                      {{ item.actual_stock }}
                    </strong>

                    <span>
                      {{ item.product.unit }}
                    </span>
                  </div>
                </td>

                <td>
                  <div class="change-information">
                    <strong
                      :class="
                        changeClass(
                          item.quantity_change,
                        )
                      "
                    >
                      {{
                        formatQuantityChange(
                          item.quantity_change,
                        )
                      }}
                      {{ item.product.unit }}
                    </strong>

                    <small>
                      {{ item.system_stock }}
                      →
                      {{ item.actual_stock }}
                    </small>
                  </div>
                </td>

                <td>
                  <div class="cost-change">
                    <span>
                      {{
                        formatCurrency(
                          item.average_cost_before,
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
                          item.average_cost_after,
                          4,
                        )
                      }}
                    </strong>
                  </div>

                  <small class="unchanged-label">
                    Tidak berubah
                  </small>
                </td>

                <td>
                  <strong
                    :class="
                      changeClass(
                        item.inventory_value_change,
                      )
                    "
                  >
                    {{
                      formatCurrency(
                        item.inventory_value_change,
                        4,
                      )
                    }}
                  </strong>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="items-footer">
          <div>
            <span>
              Total Perubahan Nilai
            </span>

            <strong
              :class="
                changeClass(
                  totalInventoryValueChange,
                )
              "
            >
              {{
                formatCurrency(
                  totalInventoryValueChange,
                  4,
                )
              }}
            </strong>
          </div>
        </footer>
      </section>

      <section class="audit-information">
        <div class="audit-icon">
          i
        </div>

        <div>
          <h3>
            Informasi Audit
          </h3>

          <p>
            Penyesuaian stok yang telah
            disimpan tidak dapat diedit atau
            dihapus. Setiap perubahan juga
            tercatat otomatis pada Riwayat
            Stok.
          </p>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.adjustment-detail-page {
  width: 100%;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 24px;
}

.back-button {
  margin-bottom: 18px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #047857;
  font-weight: 700;
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
  max-width: 720px;
  margin: 9px 0 0;
  color: #64748b;
  line-height: 1.6;
}

.movement-button,
.secondary-button {
  border: 0;
  border-radius: 10px;
  font-weight: 700;
}

.movement-button {
  padding: 11px 16px;
  background: #d1fae5;
  color: #047857;
}

.secondary-button {
  padding: 11px 16px;
  background: #e2e8f0;
  color: #334155;
}

.state-card,
.error-state,
.transaction-card,
.summary-card,
.items-card,
.audit-information {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
  box-shadow:
    0 10px 30px rgb(15 23 42 / 4%);
}

.state-card {
  padding: 65px 24px;
  color: #64748b;
  text-align: center;
}

.error-state {
  display: grid;
  justify-items: center;
  padding: 55px 24px;
  text-align: center;
}

.error-icon {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 23px;
  font-weight: 800;
}

.error-state h3 {
  margin: 16px 0 0;
  color: #0f172a;
}

.error-state p {
  margin: 8px 0 20px;
  color: #64748b;
}

.transaction-card {
  margin-bottom: 20px;
  padding: 23px;
}

.transaction-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e2e8f0;
}

.transaction-label {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.transaction-heading h3 {
  margin: 5px 0 0;
  color: #0f172a;
  font-size: 22px;
}

.transaction-heading p {
  margin: 7px 0 0;
  color: #94a3b8;
  font-size: 12px;
}

.status-badge {
  display: inline-flex;
  padding: 6px 11px;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 12px;
  font-weight: 750;
}

.transaction-information {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 20px;
  padding-top: 20px;
}

.transaction-information span,
.transaction-information strong {
  display: block;
}

.transaction-information span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.transaction-information strong {
  margin-top: 6px;
  color: #0f172a;
  font-size: 14px;
}

.notes-section {
  margin-top: 20px;
  padding: 14px;
  border-radius: 11px;
  background: #f8fafc;
}

.notes-section span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.notes-section p {
  margin: 6px 0 0;
  color: #334155;
  line-height: 1.6;
  white-space: pre-wrap;
}

.summary-grid {
  display: grid;
  grid-template-columns:
    repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.summary-card {
  padding: 20px;
}

.summary-card span,
.summary-card strong,
.summary-card small {
  display: block;
}

.summary-card span {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.summary-card strong {
  margin-top: 7px;
  color: #0f172a;
  font-size: 23px;
}

.summary-card small {
  margin-top: 5px;
  color: #94a3b8;
}

.positive {
  color: #15803d !important;
}

.negative {
  color: #dc2626 !important;
}

.neutral {
  color: #64748b !important;
}

.items-card {
  margin-bottom: 20px;
  overflow: hidden;
}

.card-header {
  padding: 22px;
  border-bottom: 1px solid #e2e8f0;
}

.card-header h3 {
  margin: 0;
  color: #0f172a;
  font-size: 18px;
}

.card-header p {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 13px;
}

.empty-items {
  padding: 50px 20px;
  color: #64748b;
  text-align: center;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1100px;
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

.product-cell {
  display: flex;
  align-items: center;
  gap: 11px;
}

.product-image,
.product-placeholder {
  width: 46px;
  height: 46px;
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

.stock-value strong,
.stock-value span {
  display: block;
}

.stock-value strong {
  color: #0f172a;
  font-size: 16px;
}

.stock-value span {
  margin-top: 3px;
  color: #64748b;
  font-size: 11px;
}

.change-information strong,
.change-information small {
  display: block;
}

.change-information small {
  margin-top: 4px;
  color: #64748b;
}

.cost-change {
  display: flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
}

.cost-change span {
  color: #64748b;
}

.cost-change .arrow {
  color: #94a3b8;
}

.cost-change strong {
  color: #0f172a;
}

.unchanged-label {
  display: block;
  margin-top: 5px;
  color: #047857;
  font-size: 10px;
  font-weight: 700;
}

.items-footer {
  display: flex;
  justify-content: flex-end;
  padding: 19px 22px;
  background: #f8fafc;
}

.items-footer div {
  min-width: 260px;
  text-align: right;
}

.items-footer span,
.items-footer strong {
  display: block;
}

.items-footer span {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.items-footer strong {
  margin-top: 5px;
  font-size: 22px;
}

.audit-information {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 20px;
}

.audit-icon {
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: #dbeafe;
  color: #1d4ed8;
  font-weight: 800;
}

.audit-information h3 {
  margin: 0;
  color: #0f172a;
  font-size: 15px;
}

.audit-information p {
  margin: 6px 0 0;
  color: #64748b;
  line-height: 1.6;
}

@media (max-width: 900px) {
  .page-header {
    flex-direction: column;
  }

  .movement-button {
    width: 100%;
  }

  .transaction-information {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 550px) {
  .transaction-heading {
    flex-direction: column;
  }

  .transaction-information {
    grid-template-columns: 1fr;
  }

  .transaction-card {
    padding: 19px;
  }

  .items-footer {
    justify-content: stretch;
  }

  .items-footer div {
    width: 100%;
    min-width: 0;
    text-align: left;
  }
}
</style>