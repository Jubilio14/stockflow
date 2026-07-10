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

import { getPurchase } from '@/services/purchaseService'

import type {
  PurchaseItemRecord,
  PurchaseValidationErrors,
} from '@/types/purchase'

interface ApiErrorResponse {
  message?: string
  errors?: PurchaseValidationErrors
}

const route = useRoute()
const router = useRouter()

const purchase =
  ref<PurchaseItemRecord | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const purchaseId = computed(() => {
  const id = Number(route.params.id)

  return Number.isInteger(id) && id > 0
    ? id
    : null
})

const purchaseItems = computed(() => {
  return purchase.value?.items ?? []
})

const totalQuantity = computed(() => {
  return purchaseItems.value.reduce(
    (total, item) =>
      total + Number(item.quantity),
    0,
  )
})

async function loadPurchase(): Promise<void> {
  if (purchaseId.value === null) {
    errorMessage.value =
      'ID pembelian tidak valid.'

    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    purchase.value =
      await getPurchase(purchaseId.value)
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
          'Anda tidak memiliki akses ke detail pembelian.'

        return
      }

      if (error.response.status === 404) {
        errorMessage.value =
          'Transaksi pembelian tidak ditemukan.'

        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil detail pembelian.'
  } finally {
    isLoading.value = false
  }
}

function goBack(): void {
  router.push({
    name: 'purchases',
  })
}

function formatCurrency(
  value: number,
  maximumFractionDigits = 0,
): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits,
  }).format(value)
}

function formatDate(
  value: string,
): string {
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(
    new Date(`${value}T00:00:00`),
  )
}

function formatDateTime(
  value: string | null,
): string {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function productInitial(
  name: string,
): string {
  return name.charAt(0).toUpperCase()
}

function statusLabel(
  status: string,
): string {
  if (status === 'completed') {
    return 'Selesai'
  }

  return status
}

onMounted(() => {
  loadPurchase()
})
</script>

<template>
  <section class="purchase-detail-page">
    <header class="page-header">
      <div>
        <button
          type="button"
          class="back-button"
          @click="goBack"
        >
          ← Kembali ke Pembelian
        </button>

        <p class="eyebrow">
          Inventory
        </p>

        <h2>Detail Pembelian</h2>

        <p class="subtitle">
          Lihat rincian transaksi, perubahan stok,
          dan perhitungan average cost produk.
        </p>
      </div>
    </header>

    <div
      v-if="isLoading"
      class="state-card"
    >
      Memuat detail pembelian...
    </div>

    <div
      v-else-if="errorMessage"
      class="error-state"
    >
      <h3>Detail tidak dapat dimuat</h3>

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

    <template v-else-if="purchase">
      <section class="transaction-card">
        <div class="transaction-heading">
          <div>
            <span class="transaction-label">
              Nomor Pembelian
            </span>

            <h3>
              {{ purchase.purchase_number }}
            </h3>

            <p>
              Dicatat pada
              {{ formatDateTime(purchase.created_at) }}
            </p>
          </div>

          <span class="status-badge">
            {{ statusLabel(purchase.status) }}
          </span>
        </div>

        <div class="transaction-information">
          <div>
            <span>Tanggal Pembelian</span>

            <strong>
              {{ formatDate(purchase.purchase_date) }}
            </strong>
          </div>

          <div>
            <span>Supplier</span>

            <strong>
              {{ purchase.supplier.name }}
            </strong>

            <small>
              {{ purchase.supplier.code }}
            </small>
          </div>

          <div>
            <span>Nomor Invoice</span>

            <strong>
              {{ purchase.invoice_number ?? '-' }}
            </strong>
          </div>

          <div>
            <span>Dicatat Oleh</span>

            <strong>
              {{ purchase.creator.name }}
            </strong>
          </div>
        </div>

        <div
          v-if="purchase.notes"
          class="notes-section"
        >
          <span>Catatan</span>

          <p>
            {{ purchase.notes }}
          </p>
        </div>
      </section>

      <section class="summary-grid">
        <article class="summary-card">
          <span>Total Pembelian</span>

          <strong>
            {{
              formatCurrency(
                purchase.total_amount,
              )
            }}
          </strong>
        </article>

        <article class="summary-card">
          <span>Jumlah Produk</span>

          <strong>
            {{ purchaseItems.length }}
          </strong>

          <small>
            Produk berbeda
          </small>
        </article>

        <article class="summary-card">
          <span>Total Kuantitas</span>

          <strong>
            {{ totalQuantity }}
          </strong>

          <small>
            Total barang masuk
          </small>
        </article>
      </section>

      <section class="items-card">
        <header class="card-header">
          <div>
            <h3>Rincian Barang</h3>

            <p>
              Stok dan average cost di bawah
              merupakan kondisi saat transaksi
              pembelian diproses.
            </p>
          </div>
        </header>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Harga Beli</th>
                <th>Subtotal</th>
                <th>Perubahan Stok</th>
                <th>Perubahan Average Cost</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="item in purchaseItems"
                :key="item.id"
              >
                <td>
                  <div class="product-cell">
                    <img
                      v-if="item.product.image_url"
                      :src="item.product.image_url"
                      :alt="item.product.name"
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
                        {{ item.product.name }}
                      </strong>

                      <span>
                        SKU:
                        {{ item.product.sku }}
                      </span>
                    </div>
                  </div>
                </td>

                <td>
                  <strong>
                    {{ item.quantity }}
                    {{ item.product.unit }}
                  </strong>
                </td>

                <td>
                  {{
                    formatCurrency(
                      item.unit_cost,
                      2,
                    )
                  }}

                  <small class="unit-note">
                    per {{ item.product.unit }}
                  </small>
                </td>

                <td>
                  <strong>
                    {{
                      formatCurrency(
                        item.subtotal,
                      )
                    }}
                  </strong>
                </td>

                <td>
                  <div class="change-information">
                    <span>
                      {{ item.stock_before }}
                      →
                      {{ item.stock_after }}
                    </span>

                    <small class="positive-change">
                      +{{ item.quantity }}
                      {{ item.product.unit }}
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
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="items-footer">
          <div>
            <span>Total Transaksi</span>

            <strong>
              {{
                formatCurrency(
                  purchase.total_amount,
                )
              }}
            </strong>
          </div>
        </footer>
      </section>

      <section class="calculation-information">
        <h3>
          Cara Weighted Average Cost Dihitung
        </h3>

        <p>
          Nilai persediaan lama ditambah nilai
          pembelian baru, kemudian dibagi dengan
          total stok setelah pembelian.
        </p>

        <code>
          ((stok lama × average cost lama)
          + (jumlah beli × harga beli))
          ÷ stok setelah pembelian
        </code>
      </section>
    </template>
  </section>
</template>

<style scoped>
.purchase-detail-page {
  width: 100%;
}

.page-header {
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
  margin: 9px 0 0;
  color: #64748b;
}

.state-card,
.error-state,
.transaction-card,
.summary-card,
.items-card,
.calculation-information {
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
  padding: 55px 24px;
  text-align: center;
}

.error-state h3 {
  margin: 0;
  color: #0f172a;
}

.error-state p {
  margin: 9px 0 20px;
  color: #64748b;
}

.secondary-button {
  padding: 11px 16px;
  border: 0;
  border-radius: 10px;
  background: #e2e8f0;
  color: #334155;
  font-weight: 700;
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
.transaction-information strong,
.transaction-information small {
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

.transaction-information small {
  margin-top: 3px;
  color: #94a3b8;
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

.unit-note {
  display: block;
  margin-top: 4px;
  color: #94a3b8;
}

.change-information span,
.change-information small {
  display: block;
}

.change-information span {
  color: #0f172a;
  font-weight: 700;
}

.positive-change {
  margin-top: 4px;
  color: #15803d;
  font-weight: 700;
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
  color: #047857;
}

.items-footer {
  display: flex;
  justify-content: flex-end;
  padding: 19px 22px;
  background: #f8fafc;
}

.items-footer div {
  min-width: 230px;
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
  color: #0f172a;
  font-size: 22px;
}

.calculation-information {
  padding: 21px;
}

.calculation-information h3 {
  margin: 0;
  color: #0f172a;
  font-size: 16px;
}

.calculation-information p {
  margin: 8px 0 14px;
  color: #64748b;
  line-height: 1.6;
}

.calculation-information code {
  display: block;
  padding: 13px 15px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #065f46;
  font-family:
    Consolas,
    monospace;
  line-height: 1.6;
}

@media (max-width: 900px) {
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