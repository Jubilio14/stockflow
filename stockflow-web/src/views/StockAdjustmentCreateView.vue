<script setup lang="ts">
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'

import { getProducts } from '@/services/productService'
import {
  createStockAdjustment,
} from '@/services/stockAdjustmentService'

import type { ProductItem } from '@/types/product'

import type {
  CreateStockAdjustmentPayload,
  StockAdjustmentReason,
  StockAdjustmentValidationErrors,
} from '@/types/stockAdjustment'

interface ApiErrorResponse {
  message?: string
  errors?: StockAdjustmentValidationErrors
}

interface AdjustmentFormItem {
  key: number
  product_id: number | ''
  actual_stock: string
}

interface AdjustmentFormState {
  adjustment_date: string
  reason: StockAdjustmentReason | ''
  notes: string
  items: AdjustmentFormItem[]
}

interface ReasonOption {
  value: StockAdjustmentReason
  label: string
}

const router = useRouter()

const products = ref<ProductItem[]>([])

const isLoadingProducts = ref(false)
const isSubmitting = ref(false)

const errorMessage = ref('')

const formErrors =
  ref<StockAdjustmentValidationErrors>({})

const reasonOptions: ReasonOption[] = [
  {
    value: 'stock_opname',
    label: 'Stock Opname',
  },
  {
    value: 'damaged',
    label: 'Barang Rusak',
  },
  {
    value: 'lost',
    label: 'Barang Hilang',
  },
  {
    value: 'expired',
    label: 'Barang Kedaluwarsa',
  },
  {
    value: 'correction',
    label: 'Koreksi Data',
  },
  {
    value: 'other',
    label: 'Lainnya',
  },
]

let itemSequence = 0

function createEmptyItem(): AdjustmentFormItem {
  itemSequence += 1

  return {
    key: itemSequence,
    product_id: '',
    actual_stock: '',
  }
}

function getLocalDateString(): string {
  const currentDate = new Date()

  const year =
    currentDate.getFullYear()

  const month = String(
    currentDate.getMonth() + 1,
  ).padStart(2, '0')

  const day = String(
    currentDate.getDate(),
  ).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const maximumAdjustmentDate =
  getLocalDateString()

const adjustmentForm =
  reactive<AdjustmentFormState>({
    adjustment_date:
      maximumAdjustmentDate,

    reason: '',

    notes: '',

    items: [
      createEmptyItem(),
    ],
  })

const selectedReasonLabel = computed(() => {
  if (adjustmentForm.reason === '') {
    return 'Belum dipilih'
  }

  return (
    reasonOptions.find(
      (option) =>
        option.value ===
        adjustmentForm.reason,
    )?.label ?? 'Belum dipilih'
  )
})

const validChangedItems = computed(() => {
  return adjustmentForm.items.filter(
    (item) => {
      const product =
        getSelectedProduct(
          item.product_id,
        )

      if (
        !product ||
        item.actual_stock === ''
      ) {
        return false
      }

      const actualStock =
        Number(item.actual_stock)

      return (
        Number.isInteger(actualStock) &&
        actualStock >= 0 &&
        actualStock !==
          product.current_stock
      )
    },
  )
})

const totalQuantityChange = computed(() => {
  return validChangedItems.value.reduce(
    (total, item) => {
      return (
        total +
        calculateQuantityChange(item)
      )
    },
    0,
  )
})

const totalInventoryValueChange =
  computed(() => {
    return validChangedItems.value.reduce(
      (total, item) => {
        return (
          total +
          calculateInventoryValueChange(
            item,
          )
        )
      },
      0,
    )
  })

async function loadProducts(): Promise<void> {
  isLoadingProducts.value = true
  errorMessage.value = ''

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
          'Anda tidak memiliki akses untuk mencatat penyesuaian stok.'

        return
      }
    }

    errorMessage.value =
      'Daftar produk gagal dimuat.'
  } finally {
    isLoadingProducts.value = false
  }
}

function getSelectedProduct(
  productId: number | '',
): ProductItem | null {
  if (productId === '') {
    return null
  }

  return (
    products.value.find(
      (product) =>
        product.id === productId,
    ) ?? null
  )
}

function availableProductsForItem(
  currentItem: AdjustmentFormItem,
): ProductItem[] {
  const selectedProductIds =
    adjustmentForm.items
      .filter(
        (item) =>
          item.key !==
            currentItem.key &&
          item.product_id !== '',
      )
      .map(
        (item) =>
          Number(item.product_id),
      )

  return products.value.filter(
    (product) =>
      product.id ===
        currentItem.product_id ||
      !selectedProductIds.includes(
        product.id,
      ),
  )
}

function calculateQuantityChange(
  item: AdjustmentFormItem,
): number {
  const product =
    getSelectedProduct(
      item.product_id,
    )

  if (
    !product ||
    item.actual_stock === ''
  ) {
    return 0
  }

  const actualStock =
    Number(item.actual_stock)

  if (
    !Number.isInteger(actualStock) ||
    actualStock < 0
  ) {
    return 0
  }

  return (
    actualStock -
    product.current_stock
  )
}

function calculateInventoryValueChange(
  item: AdjustmentFormItem,
): number {
  const product =
    getSelectedProduct(
      item.product_id,
    )

  if (!product) {
    return 0
  }

  return (
    calculateQuantityChange(item) *
    Number(product.average_cost)
  )
}

function formatQuantityChange(
  quantity: number,
): string {
  if (quantity > 0) {
    return `+${quantity}`
  }

  return String(quantity)
}

function quantityChangeClass(
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

function firstError(
  field: string,
): string | null {
  return (
    formErrors.value[field]?.[0] ??
    null
  )
}

function clearFieldError(
  field: string,
): void {
  if (!formErrors.value[field]) {
    return
  }

  const nextErrors = {
    ...formErrors.value,
  }

  delete nextErrors[field]

  formErrors.value = nextErrors
}

function clearItemErrors(
  index: number,
): void {
  const prefix = `items.${index}.`

  const nextErrors = {
    ...formErrors.value,
  }

  Object.keys(nextErrors)
    .filter(
      (key) =>
        key.startsWith(prefix),
    )
    .forEach((key) => {
      delete nextErrors[key]
    })

  delete nextErrors.items

  formErrors.value = nextErrors
}

function handleProductChange(
  item: AdjustmentFormItem,
  index: number,
): void {
  item.actual_stock = ''

  clearItemErrors(index)
}

function addAdjustmentItem(): void {
  if (
    products.value.length > 0 &&
    adjustmentForm.items.length >=
      products.value.length
  ) {
    toast.warning(
      'Semua produk sudah dipilih.',
    )

    return
  }

  adjustmentForm.items.push(
    createEmptyItem(),
  )
}

function removeAdjustmentItem(
  index: number,
): void {
  if (
    adjustmentForm.items.length === 1
  ) {
    return
  }

  adjustmentForm.items.splice(
    index,
    1,
  )

  formErrors.value = {}
}

function validateAdjustmentForm(): boolean {
  const errors:
    StockAdjustmentValidationErrors = {}

  if (!adjustmentForm.adjustment_date) {
    errors.adjustment_date = [
      'Tanggal penyesuaian wajib diisi.',
    ]
  } else if (
    adjustmentForm.adjustment_date >
    maximumAdjustmentDate
  ) {
    errors.adjustment_date = [
      'Tanggal penyesuaian tidak boleh melebihi hari ini.',
    ]
  }

  if (adjustmentForm.reason === '') {
    errors.reason = [
      'Alasan penyesuaian wajib dipilih.',
    ]
  }

  if (
    adjustmentForm.items.length === 0
  ) {
    errors.items = [
      'Minimal harus ada satu produk.',
    ]
  }

  const selectedProductIds: number[] = []

  adjustmentForm.items.forEach(
    (item, index) => {
      if (item.product_id === '') {
        errors[
          `items.${index}.product_id`
        ] = [
          'Produk wajib dipilih.',
        ]
      } else {
        const productId =
          Number(item.product_id)

        if (
          selectedProductIds.includes(
            productId,
          )
        ) {
          errors[
            `items.${index}.product_id`
          ] = [
            'Produk yang sama tidak boleh dipilih dua kali.',
          ]
        }

        selectedProductIds.push(
          productId,
        )
      }

      if (item.actual_stock === '') {
        errors[
          `items.${index}.actual_stock`
        ] = [
          'Stok fisik wajib diisi.',
        ]

        return
      }

      const actualStock =
        Number(item.actual_stock)

      if (
        !Number.isInteger(actualStock)
      ) {
        errors[
          `items.${index}.actual_stock`
        ] = [
          'Stok fisik harus berupa bilangan bulat.',
        ]

        return
      }

      if (actualStock < 0) {
        errors[
          `items.${index}.actual_stock`
        ] = [
          'Stok fisik tidak boleh negatif.',
        ]

        return
      }

      const product =
        getSelectedProduct(
          item.product_id,
        )

      if (
        product &&
        actualStock ===
          product.current_stock
      ) {
        errors[
          `items.${index}.actual_stock`
        ] = [
          'Stok fisik sama dengan stok sistem sehingga tidak perlu disesuaikan.',
        ]
      }
    },
  )

  formErrors.value = errors

  return (
    Object.keys(errors).length === 0
  )
}

function buildPayload():
  CreateStockAdjustmentPayload {
  return {
    adjustment_date:
      adjustmentForm.adjustment_date,

    reason:
      adjustmentForm.reason as
        StockAdjustmentReason,

    notes:
      adjustmentForm.notes.trim() ||
      null,

    items:
      adjustmentForm.items.map(
        (item) => ({
          product_id:
            Number(item.product_id),

          actual_stock:
            Number(item.actual_stock),
        }),
      ),
  }
}

async function submitAdjustment(): Promise<void> {
  if (!validateAdjustmentForm()) {
    errorMessage.value =
      'Periksa kembali data penyesuaian yang ditandai.'

    return
  }

  errorMessage.value = ''

  const confirmation =
    await Swal.fire({
      title:
        'Simpan penyesuaian stok?',

      html: `
        <div style="text-align:left; line-height:1.7">
          <div>
            Produk disesuaikan:
            <strong>${validChangedItems.value.length}</strong>
          </div>
          <div>
            Total perubahan:
            <strong>${formatQuantityChange(
              totalQuantityChange.value,
            )}</strong>
          </div>
          <div>
            Perubahan nilai persediaan:
            <strong>${formatCurrency(
              totalInventoryValueChange.value,
            )}</strong>
          </div>
        </div>
      `,

      icon: 'warning',

      showCancelButton: true,

      confirmButtonText:
        'Ya, simpan penyesuaian',

      cancelButtonText: 'Batal',

      reverseButtons: true,
    })

  if (!confirmation.isConfirmed) {
    return
  }

  isSubmitting.value = true

  try {
    const response =
      await createStockAdjustment(
        buildPayload(),
      )

    toast.success(response.message, {
      description:
        response.adjustment
          .adjustment_number,
    })

    await router.push({
      name:
        'stock-adjustments.show',

      params: {
        id: response.adjustment.id,
      },
    })
  } catch (error: unknown) {
    if (
      !axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      errorMessage.value =
        'Terjadi kesalahan yang tidak dikenali.'

      return
    }

    if (!error.response) {
      errorMessage.value =
        'Tidak dapat terhubung ke server Laravel.'

      return
    }

    const status =
      error.response.status

    const data =
      error.response.data

    if (status === 422) {
      formErrors.value =
        data.errors ?? {}

      errorMessage.value =
        'Periksa kembali data penyesuaian yang ditandai.'

      return
    }

    if (status === 401) {
      errorMessage.value =
        'Session login sudah tidak valid.'

      return
    }

    if (status === 403) {
      errorMessage.value =
        data.message ??
        'Anda tidak memiliki akses untuk mencatat penyesuaian.'

      return
    }

    errorMessage.value =
      data.message ??
      'Penyesuaian stok gagal disimpan.'
  } finally {
    isSubmitting.value = false
  }
}

function goBack(): void {
  router.push({
    name: 'stock-adjustments',
  })
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

onMounted(() => {
  loadProducts()
})
</script>

<template>
  <section class="adjustment-create-page">
    <header class="page-header">
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

      <h2>Catat Penyesuaian Stok</h2>

      <p class="subtitle">
        Sesuaikan stok sistem dengan jumlah
        barang fisik yang sebenarnya. Average
        cost produk tidak akan berubah.
      </p>
    </header>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="isLoadingProducts"
      class="loading-card"
    >
      Memuat daftar produk...
    </div>

    <form
      v-else
      class="adjustment-layout"
      novalidate
      @submit.prevent="submitAdjustment"
    >
      <main class="main-content">
        <section class="form-card">
          <header class="card-header">
            <div>
              <h3>
                Informasi Penyesuaian
              </h3>

              <p>
                Pilih tanggal, alasan, dan
                masukkan catatan pendukung.
              </p>
            </div>
          </header>

          <div class="information-grid">
            <div class="form-group">
              <label for="adjustment-date">
                Tanggal Penyesuaian
              </label>

              <input
                id="adjustment-date"
                v-model="
                  adjustmentForm.adjustment_date
                "
                type="date"
                :max="maximumAdjustmentDate"
                @input="
                  clearFieldError(
                    'adjustment_date',
                  )
                "
              />

              <span
                v-if="
                  firstError(
                    'adjustment_date',
                  )
                "
                class="field-error"
              >
                {{
                  firstError(
                    'adjustment_date',
                  )
                }}
              </span>
            </div>

            <div class="form-group">
              <label for="adjustment-reason">
                Alasan Penyesuaian
              </label>

              <select
                id="adjustment-reason"
                v-model="
                  adjustmentForm.reason
                "
                @change="
                  clearFieldError(
                    'reason',
                  )
                "
              >
                <option
                  value=""
                  disabled
                >
                  Pilih alasan
                </option>

                <option
                  v-for="
                    option in reasonOptions
                  "
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>

              <span
                v-if="
                  firstError('reason')
                "
                class="field-error"
              >
                {{ firstError('reason') }}
              </span>
            </div>

            <div class="form-group full-column">
              <label for="adjustment-notes">
                Catatan
              </label>

              <textarea
                id="adjustment-notes"
                v-model="
                  adjustmentForm.notes
                "
                rows="4"
                maxlength="1000"
                placeholder="Contoh: Ditemukan 2 botol rusak saat pengecekan gudang..."
              />

              <span
                v-if="
                  firstError('notes')
                "
                class="field-error"
              >
                {{ firstError('notes') }}
              </span>
            </div>
          </div>
        </section>

        <section class="form-card">
          <header class="card-header items-header">
            <div>
              <h3>
                Barang yang Disesuaikan
              </h3>

              <p>
                Pilih produk lalu masukkan
                jumlah stok fisik sebenarnya.
              </p>
            </div>

            <button
              type="button"
              class="add-item-button"
              :disabled="
                products.length === 0 ||
                adjustmentForm.items.length >=
                  products.length
              "
              @click="addAdjustmentItem"
            >
              + Tambah Produk
            </button>
          </header>

          <div
            v-if="products.length === 0"
            class="empty-products"
          >
            Belum ada produk yang dapat
            disesuaikan.
          </div>

          <div
            v-else
            class="items-wrapper"
          >
            <article
              v-for="(
                item,
                index
              ) in adjustmentForm.items"
              :key="item.key"
              class="adjustment-item"
            >
              <header class="item-header">
                <strong>
                  Produk {{ index + 1 }}
                </strong>

                <button
                  type="button"
                  class="remove-item-button"
                  :disabled="
                    adjustmentForm.items
                      .length === 1
                  "
                  @click="
                    removeAdjustmentItem(
                      index,
                    )
                  "
                >
                  Hapus
                </button>
              </header>

              <div class="item-grid">
                <div class="form-group product-field">
                  <label
                    :for="`product-${item.key}`"
                  >
                    Produk
                  </label>

                  <select
                    :id="`product-${item.key}`"
                    v-model="item.product_id"
                    @change="
                      handleProductChange(
                        item,
                        index,
                      )
                    "
                  >
                    <option
                      value=""
                      disabled
                    >
                      Pilih produk
                    </option>

                    <option
                      v-for="
                        product in
                        availableProductsForItem(
                          item,
                        )
                      "
                      :key="product.id"
                      :value="product.id"
                    >
                      {{ product.sku }} —
                      {{ product.name }}
                    </option>
                  </select>

                  <span
                    v-if="
                      firstError(
                        `items.${index}.product_id`,
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        `items.${index}.product_id`,
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label>
                    Stok Sistem
                  </label>

                  <div class="readonly-value">
                    {{
                      getSelectedProduct(
                        item.product_id,
                      )?.current_stock ?? '-'
                    }}

                    {{
                      getSelectedProduct(
                        item.product_id,
                      )?.unit ?? ''
                    }}
                  </div>
                </div>

                <div class="form-group">
                  <label
                    :for="`actual-stock-${item.key}`"
                  >
                    Stok Fisik
                  </label>

                  <input
                    :id="`actual-stock-${item.key}`"
                    v-model="item.actual_stock"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="Jumlah fisik"
                    :disabled="
                      item.product_id === ''
                    "
                    @input="
                      clearItemErrors(index)
                    "
                  />

                  <span
                    v-if="
                      firstError(
                        `items.${index}.actual_stock`,
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        `items.${index}.actual_stock`,
                      )
                    }}
                  </span>
                </div>
              </div>

              <div
                v-if="
                  getSelectedProduct(
                    item.product_id,
                  )
                "
                class="calculation-preview"
              >
                <div>
                  <span>
                    Average Cost
                  </span>

                  <strong>
                    {{
                      formatCurrency(
                        getSelectedProduct(
                          item.product_id,
                        )?.average_cost ?? 0,
                      )
                    }}
                  </strong>
                </div>

                <div>
                  <span>
                    Perubahan Stok
                  </span>

                  <strong
                    :class="
                      quantityChangeClass(
                        calculateQuantityChange(
                          item,
                        ),
                      )
                    "
                  >
                    {{
                      item.actual_stock === ''
                        ? '-'
                        : formatQuantityChange(
                            calculateQuantityChange(
                              item,
                            ),
                          )
                    }}

                    {{
                      getSelectedProduct(
                        item.product_id,
                      )?.unit
                    }}
                  </strong>
                </div>

                <div>
                  <span>
                    Stok Setelah Penyesuaian
                  </span>

                  <strong>
                    {{
                      item.actual_stock === ''
                        ? '-'
                        : item.actual_stock
                    }}

                    {{
                      getSelectedProduct(
                        item.product_id,
                      )?.unit
                    }}
                  </strong>
                </div>

                <div>
                  <span>
                    Perubahan Nilai Persediaan
                  </span>

                  <strong
                    :class="
                      quantityChangeClass(
                        calculateInventoryValueChange(
                          item,
                        ),
                      )
                    "
                  >
                    {{
                      item.actual_stock === ''
                        ? '-'
                        : formatCurrency(
                            calculateInventoryValueChange(
                              item,
                            ),
                          )
                    }}
                  </strong>
                </div>
              </div>
            </article>
          </div>

          <span
            v-if="firstError('items')"
            class="field-error items-error"
          >
            {{ firstError('items') }}
          </span>
        </section>
      </main>

      <aside class="adjustment-summary">
        <section class="summary-card">
          <h3>
            Ringkasan Penyesuaian
          </h3>

          <div class="summary-row">
            <span>Alasan</span>

            <strong>
              {{ selectedReasonLabel }}
            </strong>
          </div>

          <div class="summary-row">
            <span>
              Produk Disesuaikan
            </span>

            <strong>
              {{ validChangedItems.length }}
            </strong>
          </div>

          <div class="summary-row">
            <span>
              Total Perubahan Stok
            </span>

            <strong
              :class="
                quantityChangeClass(
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
          </div>

          <div class="summary-total">
            <span>
              Perubahan Nilai Persediaan
            </span>

            <strong
              :class="
                quantityChangeClass(
                  totalInventoryValueChange,
                )
              "
            >
              {{
                formatCurrency(
                  totalInventoryValueChange,
                )
              }}
            </strong>
          </div>

          <div class="inventory-information">
            Stok produk akan diubah sesuai stok
            fisik. Average cost produk tetap dan
            riwayat perubahan akan tercatat
            otomatis.
          </div>

          <button
            type="submit"
            class="submit-button"
            :disabled="
              isSubmitting ||
              products.length === 0
            "
          >
            {{
              isSubmitting
                ? 'Menyimpan Penyesuaian...'
                : 'Simpan Penyesuaian'
            }}
          </button>

          <button
            type="button"
            class="cancel-button"
            :disabled="isSubmitting"
            @click="goBack"
          >
            Batal
          </button>
        </section>
      </aside>
    </form>
  </section>
</template>

<style scoped>
.adjustment-create-page {
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
  max-width: 720px;
  margin: 9px 0 0;
  color: #64748b;
  line-height: 1.6;
}

.alert-error {
  margin-bottom: 18px;
  padding: 13px 15px;
  border: 1px solid #fecaca;
  border-radius: 11px;
  background: #fef2f2;
  color: #b91c1c;
}

.loading-card,
.form-card,
.summary-card {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
  box-shadow:
    0 10px 30px rgb(15 23 42 / 4%);
}

.loading-card {
  padding: 60px 24px;
  color: #64748b;
  text-align: center;
}

.adjustment-layout {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    330px;
  gap: 22px;
  align-items: start;
}

.main-content {
  min-width: 0;
  display: grid;
  gap: 20px;
}

.form-card {
  padding: 22px;
}

.card-header {
  margin-bottom: 20px;
}

.card-header h3,
.summary-card h3 {
  margin: 0;
  color: #0f172a;
  font-size: 18px;
}

.card-header p {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 13px;
}

.items-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
}

.information-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.full-column {
  grid-column: 1 / -1;
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
.form-group select,
.form-group textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  outline: none;
  background: white;
  color: #0f172a;
  font: inherit;
}

.form-group input,
.form-group select,
.readonly-value {
  height: 44px;
  padding: 0 12px;
}

.form-group textarea {
  padding: 12px;
  resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.form-group input:disabled {
  cursor: not-allowed;
  background: #f1f5f9;
}

.readonly-value {
  display: flex;
  align-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f1f5f9;
  color: #334155;
  font-weight: 700;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
  line-height: 1.4;
}

.add-item-button {
  flex: 0 0 auto;
  padding: 10px 14px;
  border: 0;
  border-radius: 10px;
  background: #d1fae5;
  color: #047857;
  font-weight: 700;
}

.add-item-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.items-wrapper {
  display: grid;
  gap: 16px;
}

.adjustment-item {
  padding: 17px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #f8fafc;
}

.item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.item-header strong {
  color: #0f172a;
  font-size: 14px;
}

.remove-item-button {
  padding: 7px 10px;
  border: 0;
  border-radius: 8px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 11px;
  font-weight: 700;
}

.remove-item-button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.item-grid {
  display: grid;
  grid-template-columns:
    minmax(250px, 1fr)
    150px
    150px;
  gap: 14px;
}

.calculation-preview {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-top: 15px;
}

.calculation-preview div {
  min-width: 0;
  padding: 11px;
  border-radius: 10px;
  background: white;
}

.calculation-preview span,
.calculation-preview strong {
  display: block;
}

.calculation-preview span {
  color: #64748b;
  font-size: 10px;
}

.calculation-preview strong {
  margin-top: 5px;
  color: #0f172a;
  font-size: 12px;
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

.empty-products {
  padding: 30px;
  border-radius: 12px;
  background: #fef3c7;
  color: #92400e;
  text-align: center;
}

.items-error {
  display: block;
  margin-top: 12px;
}

.adjustment-summary {
  position: sticky;
  top: 20px;
}

.summary-card {
  padding: 22px;
}

.summary-card h3 {
  padding-bottom: 17px;
  border-bottom: 1px solid #e2e8f0;
}

.summary-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 13px 0;
  border-bottom: 1px solid #f1f5f9;
}

.summary-row span {
  color: #64748b;
  font-size: 13px;
}

.summary-row strong {
  color: #0f172a;
  font-size: 13px;
  text-align: right;
}

.summary-total {
  margin: 18px 0;
  padding: 15px;
  border-radius: 12px;
  background: #f8fafc;
}

.summary-total span,
.summary-total strong {
  display: block;
}

.summary-total span {
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.summary-total strong {
  margin-top: 6px;
  font-size: 22px;
}

.inventory-information {
  margin-bottom: 17px;
  padding: 12px;
  border-radius: 10px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 11px;
  line-height: 1.6;
}

.submit-button,
.cancel-button {
  width: 100%;
  height: 44px;
  border: 0;
  border-radius: 10px;
  font-weight: 700;
}

.submit-button {
  background: #047857;
  color: white;
}

.cancel-button {
  margin-top: 9px;
  background: #e2e8f0;
  color: #334155;
}

.submit-button:disabled,
.cancel-button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 1100px) {
  .adjustment-layout {
    grid-template-columns: 1fr;
  }

  .adjustment-summary {
    position: static;
  }
}

@media (max-width: 760px) {
  .information-grid,
  .item-grid,
  .calculation-preview {
    grid-template-columns: 1fr;
  }

  .full-column {
    grid-column: auto;
  }

  .items-header {
    align-items: stretch;
    flex-direction: column;
  }

  .add-item-button {
    width: 100%;
  }

  .form-card,
  .summary-card {
    padding: 18px;
  }
}
</style>