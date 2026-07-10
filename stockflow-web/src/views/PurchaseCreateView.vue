<script setup lang="ts">
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  computed,
  onMounted,
  reactive,
  ref,
} from 'vue'
import {
  useRouter,
} from 'vue-router'
import { toast } from 'vue-sonner'

import { getProducts } from '@/services/productService'
import {
  createPurchase,
} from '@/services/purchaseService'
import { getSuppliers } from '@/services/supplierService'

import type { ProductItem } from '@/types/product'
import type {
  CreatePurchasePayload,
  PurchaseValidationErrors,
} from '@/types/purchase'
import type { SupplierItem } from '@/types/supplier'

interface ApiErrorResponse {
  message?: string
  errors?: PurchaseValidationErrors
}

interface PurchaseFormItem {
  key: number
  product_id: number | ''
  quantity: string
  unit_cost: string
}

interface PurchaseFormState {
  supplier_id: number | ''
  invoice_number: string
  purchase_date: string
  notes: string
  items: PurchaseFormItem[]
}

const router = useRouter()

const suppliers = ref<SupplierItem[]>([])
const products = ref<ProductItem[]>([])

const isLoadingOptions = ref(false)
const isSubmitting = ref(false)

const errorMessage = ref('')

const formErrors =
  ref<PurchaseValidationErrors>({})

let itemSequence = 0

function createEmptyItem(): PurchaseFormItem {
  itemSequence += 1

  return {
    key: itemSequence,
    product_id: '',
    quantity: '1',
    unit_cost: '',
  }
}

function getLocalDateString(): string {
  const date = new Date()

  const year = date.getFullYear()

  const month = String(
    date.getMonth() + 1,
  ).padStart(2, '0')

  const day = String(
    date.getDate(),
  ).padStart(2, '0')

  return `${year}-${month}-${day}`
}

const maximumPurchaseDate =
  getLocalDateString()

const purchaseForm =
  reactive<PurchaseFormState>({
    supplier_id: '',
    invoice_number: '',
    purchase_date: maximumPurchaseDate,
    notes: '',
    items: [
      createEmptyItem(),
    ],
  })

const selectedSupplier = computed(() => {
  return (
    suppliers.value.find(
      (supplier) =>
        supplier.id ===
        purchaseForm.supplier_id,
    ) ?? null
  )
})

const totalAmount = computed(() => {
  return purchaseForm.items.reduce(
    (total, item) => {
      const quantity =
        Number(item.quantity)

      const unitCost =
        Number(item.unit_cost)

      if (
        !Number.isFinite(quantity) ||
        !Number.isFinite(unitCost)
      ) {
        return total
      }

      return total + quantity * unitCost
    },
    0,
  )
})

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
  currentItem: PurchaseFormItem,
): ProductItem[] {
  const selectedProductIds =
    purchaseForm.items
      .filter(
        (item) =>
          item.key !== currentItem.key &&
          item.product_id !== '',
      )
      .map(
        (item) =>
          Number(item.product_id),
      )

  return products.value.filter(
    (product) =>
      product.id === currentItem.product_id ||
      !selectedProductIds.includes(product.id),
  )
}

function calculateSubtotal(
  item: PurchaseFormItem,
): number {
  const quantity =
    Number(item.quantity)

  const unitCost =
    Number(item.unit_cost)

  if (
    !Number.isFinite(quantity) ||
    !Number.isFinite(unitCost) ||
    quantity <= 0 ||
    unitCost <= 0
  ) {
    return 0
  }

  return quantity * unitCost
}

function calculateEstimatedAverageCost(
  item: PurchaseFormItem,
): number | null {
  const product =
    getSelectedProduct(item.product_id)

  if (!product) {
    return null
  }

  const quantity =
    Number(item.quantity)

  const unitCost =
    Number(item.unit_cost)

  if (
    !Number.isInteger(quantity) ||
    quantity <= 0 ||
    !Number.isFinite(unitCost) ||
    unitCost <= 0
  ) {
    return null
  }

  const stockBefore =
    Number(product.current_stock)

  const averageCostBefore =
    Number(product.average_cost)

  const stockAfter =
    stockBefore + quantity

  if (stockAfter <= 0) {
    return null
  }

  const oldStockValue =
    stockBefore * averageCostBefore

  const newPurchaseValue =
    quantity * unitCost

  return (
    oldStockValue +
    newPurchaseValue
  ) / stockAfter
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

async function loadOptions(): Promise<void> {
  isLoadingOptions.value = true
  errorMessage.value = ''

  try {
    const [
      supplierResponse,
      productResponse,
    ] = await Promise.all([
      getSuppliers({
        search: '',
        status: 'active',
        page: 1,
        per_page: 100,
      }),

      getProducts({
        search: '',
        category_id: '',
        status: 'active',
        stock_status: '',
        page: 1,
        per_page: 100,
      }),
    ])

    suppliers.value =
      supplierResponse.data

    products.value =
      productResponse.data
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
          'Anda tidak memiliki akses untuk mencatat pembelian.'

        return
      }
    }

    errorMessage.value =
      'Pilihan supplier atau produk gagal dimuat.'
  } finally {
    isLoadingOptions.value = false
  }
}

function addPurchaseItem(): void {
  if (
    products.value.length > 0 &&
    purchaseForm.items.length >=
      products.value.length
  ) {
    toast.warning(
      'Semua produk aktif sudah dipilih.',
    )

    return
  }

  purchaseForm.items.push(
    createEmptyItem(),
  )
}

function removePurchaseItem(
  index: number,
): void {
  if (purchaseForm.items.length === 1) {
    return
  }

  purchaseForm.items.splice(index, 1)

  formErrors.value = {}
}

function handleProductChange(
  index: number,
): void {
  clearItemErrors(index)
}

function validatePurchaseForm(): boolean {
  const errors: PurchaseValidationErrors = {}

  if (purchaseForm.supplier_id === '') {
    errors.supplier_id = [
      'Supplier wajib dipilih.',
    ]
  }

  if (!purchaseForm.purchase_date) {
    errors.purchase_date = [
      'Tanggal pembelian wajib diisi.',
    ]
  } else if (
    purchaseForm.purchase_date >
    maximumPurchaseDate
  ) {
    errors.purchase_date = [
      'Tanggal pembelian tidak boleh melebihi hari ini.',
    ]
  }

  if (purchaseForm.items.length === 0) {
    errors.items = [
      'Minimal harus ada satu produk.',
    ]
  }

  const selectedProductIds: number[] = []

  purchaseForm.items.forEach(
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

        selectedProductIds.push(productId)
      }

      const quantity =
        Number(item.quantity)

      if (!item.quantity) {
        errors[
          `items.${index}.quantity`
        ] = [
          'Jumlah wajib diisi.',
        ]
      } else if (
        !Number.isInteger(quantity) ||
        quantity < 1
      ) {
        errors[
          `items.${index}.quantity`
        ] = [
          'Jumlah harus berupa bilangan bulat minimal 1.',
        ]
      }

      const unitCost =
        Number(item.unit_cost)

      if (!item.unit_cost) {
        errors[
          `items.${index}.unit_cost`
        ] = [
          'Harga beli wajib diisi.',
        ]
      } else if (
        !Number.isFinite(unitCost) ||
        unitCost <= 0
      ) {
        errors[
          `items.${index}.unit_cost`
        ] = [
          'Harga beli harus lebih dari nol.',
        ]
      }
    },
  )

  formErrors.value = errors

  return Object.keys(errors).length === 0
}

function buildPayload(): CreatePurchasePayload {
  return {
    supplier_id: Number(
      purchaseForm.supplier_id,
    ),

    invoice_number:
      purchaseForm.invoice_number.trim() ||
      null,

    purchase_date:
      purchaseForm.purchase_date,

    notes:
      purchaseForm.notes.trim() ||
      null,

    items: purchaseForm.items.map(
      (item) => ({
        product_id: Number(
          item.product_id,
        ),

        quantity: Number(
          item.quantity,
        ),

        unit_cost: Number(
          item.unit_cost,
        ),
      }),
    ),
  }
}

async function submitPurchase(): Promise<void> {
  if (!validatePurchaseForm()) {
    errorMessage.value =
      'Periksa kembali data pembelian yang ditandai.'

    return
  }

  errorMessage.value = ''

  const confirmation =
    await Swal.fire({
      title: 'Simpan pembelian?',

      text:
        `Pembelian dari ${
          selectedSupplier.value?.name ??
          'supplier'
        } dengan total ${
          formatCurrency(
            totalAmount.value,
          )
        } akan menambah stok produk.`,

      icon: 'question',

      showCancelButton: true,

      confirmButtonText:
        'Ya, simpan pembelian',

      cancelButtonText: 'Batal',

      reverseButtons: true,
    })

  if (!confirmation.isConfirmed) {
    return
  }

  isSubmitting.value = true

  try {
    const response =
      await createPurchase(
        buildPayload(),
      )

    toast.success(response.message, {
      description:
        response.purchase.purchase_number,
    })

    await router.push({
      name: 'purchases.show',

      params: {
        id: response.purchase.id,
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
        'Periksa kembali data pembelian yang ditandai.'

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
        'Anda tidak memiliki akses untuk mencatat pembelian.'

      return
    }

    errorMessage.value =
      data.message ??
      'Pembelian gagal disimpan.'
  } finally {
    isSubmitting.value = false
  }
}

function goBack(): void {
  router.push({
    name: 'purchases',
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
  loadOptions()
})
</script>

<template>
  <section class="purchase-create-page">
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

        <h2>Catat Pembelian</h2>

        <p class="subtitle">
          Catat barang masuk dari supplier.
          Stok dan average cost akan diperbarui
          otomatis setelah transaksi disimpan.
        </p>
      </div>
    </header>

    <div
      v-if="errorMessage"
      class="alert-error"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="isLoadingOptions"
      class="loading-card"
    >
      Memuat pilihan supplier dan produk...
    </div>

    <form
      v-else
      class="purchase-layout"
      novalidate
      @submit.prevent="submitPurchase"
    >
      <main class="main-content">
        <section class="form-card">
          <header class="card-header">
            <div>
              <h3>Informasi Pembelian</h3>

              <p>
                Masukkan supplier, tanggal,
                dan informasi nota pembelian.
              </p>
            </div>
          </header>

          <div class="purchase-information-grid">
            <div class="form-group">
              <label for="purchase-supplier">
                Supplier
              </label>

              <select
                id="purchase-supplier"
                v-model="
                  purchaseForm.supplier_id
                "
                @change="
                  clearFieldError(
                    'supplier_id',
                  )
                "
              >
                <option
                  value=""
                  disabled
                >
                  Pilih supplier
                </option>

                <option
                  v-for="supplier in suppliers"
                  :key="supplier.id"
                  :value="supplier.id"
                >
                  {{ supplier.code }} —
                  {{ supplier.name }}
                </option>
              </select>

              <span
                v-if="
                  firstError('supplier_id')
                "
                class="field-error"
              >
                {{
                  firstError('supplier_id')
                }}
              </span>
            </div>

            <div class="form-group">
              <label for="purchase-date">
                Tanggal Pembelian
              </label>

              <input
                id="purchase-date"
                v-model="
                  purchaseForm.purchase_date
                "
                type="date"
                :max="maximumPurchaseDate"
                @input="
                  clearFieldError(
                    'purchase_date',
                  )
                "
              />

              <span
                v-if="
                  firstError(
                    'purchase_date',
                  )
                "
                class="field-error"
              >
                {{
                  firstError(
                    'purchase_date',
                  )
                }}
              </span>
            </div>

            <div class="form-group full-column">
              <label for="invoice-number">
                Nomor Invoice Supplier
              </label>

              <input
                id="invoice-number"
                v-model="
                  purchaseForm.invoice_number
                "
                type="text"
                maxlength="100"
                placeholder="Opsional, contoh: INV-SM-001"
              />

              <small class="field-information">
                Nomor nota atau invoice yang
                diberikan supplier.
              </small>

              <span
                v-if="
                  firstError(
                    'invoice_number',
                  )
                "
                class="field-error"
              >
                {{
                  firstError(
                    'invoice_number',
                  )
                }}
              </span>
            </div>

            <div class="form-group full-column">
              <label for="purchase-notes">
                Catatan
              </label>

              <textarea
                id="purchase-notes"
                v-model="
                  purchaseForm.notes
                "
                rows="3"
                maxlength="1000"
                placeholder="Catatan tambahan pembelian..."
              />

              <span
                v-if="firstError('notes')"
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
              <h3>Barang Pembelian</h3>

              <p>
                Pilih produk, jumlah barang,
                dan harga beli per satuan.
              </p>
            </div>

            <button
              type="button"
              class="add-item-button"
              :disabled="
                products.length === 0 ||
                purchaseForm.items.length >=
                  products.length
              "
              @click="addPurchaseItem"
            >
              + Tambah Produk
            </button>
          </header>

          <div
            v-if="products.length === 0"
            class="empty-products"
          >
            Belum ada produk aktif. Tambahkan
            atau aktifkan produk terlebih dahulu.
          </div>

          <div
            v-else
            class="items-wrapper"
          >
            <article
              v-for="(
                item,
                index
              ) in purchaseForm.items"
              :key="item.key"
              class="purchase-item"
            >
              <header class="item-number">
                <strong>
                  Produk {{ index + 1 }}
                </strong>

                <button
                  type="button"
                  class="remove-item-button"
                  :disabled="
                    purchaseForm.items.length ===
                    1
                  "
                  @click="
                    removePurchaseItem(index)
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
                      handleProductChange(index)
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
                  <label
                    :for="`quantity-${item.key}`"
                  >
                    Jumlah
                  </label>

                  <input
                    :id="`quantity-${item.key}`"
                    v-model="item.quantity"
                    type="number"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    @input="
                      clearItemErrors(index)
                    "
                  />

                  <span
                    v-if="
                      firstError(
                        `items.${index}.quantity`,
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        `items.${index}.quantity`,
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label
                    :for="`unit-cost-${item.key}`"
                  >
                    Harga Beli/Satuan
                  </label>

                  <input
                    :id="`unit-cost-${item.key}`"
                    v-model="item.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="Contoh: 3000"
                    @input="
                      clearItemErrors(index)
                    "
                  />

                  <span
                    v-if="
                      firstError(
                        `items.${index}.unit_cost`,
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        `items.${index}.unit_cost`,
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
                  <span>Stok Saat Ini</span>

                  <strong>
                    {{
                      getSelectedProduct(
                        item.product_id,
                      )?.current_stock
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
                    Average Cost Saat Ini
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
                  <span>Subtotal</span>

                  <strong>
                    {{
                      formatCurrency(
                        calculateSubtotal(
                          item,
                        ),
                      )
                    }}
                  </strong>
                </div>

                <div>
                  <span>
                    Estimasi Average Cost Baru
                  </span>

                  <strong>
                    {{
                      calculateEstimatedAverageCost(
                        item,
                      ) === null
                        ? '-'
                        : formatCurrency(
                            calculateEstimatedAverageCost(
                              item,
                            ) ?? 0,
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

      <aside class="purchase-summary">
        <section class="summary-card">
          <h3>Ringkasan Pembelian</h3>

          <div class="summary-row">
            <span>Supplier</span>

            <strong>
              {{
                selectedSupplier?.name ??
                'Belum dipilih'
              }}
            </strong>
          </div>

          <div class="summary-row">
            <span>Jumlah Produk</span>

            <strong>
              {{ purchaseForm.items.length }}
            </strong>
          </div>

          <div class="summary-row">
            <span>Total Kuantitas</span>

            <strong>
              {{
                purchaseForm.items.reduce(
                  (
                    total,
                    item,
                  ) =>
                    total +
                    (Number(
                      item.quantity,
                    ) || 0),
                  0,
                )
              }}
            </strong>
          </div>

          <div class="summary-total">
            <span>Total Pembelian</span>

            <strong>
              {{
                formatCurrency(
                  totalAmount,
                )
              }}
            </strong>
          </div>

          <div class="inventory-information">
            Setelah disimpan, stok produk akan
            langsung bertambah dan weighted
            average cost akan dihitung ulang.
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
                ? 'Menyimpan Pembelian...'
                : 'Simpan Pembelian'
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
.purchase-create-page {
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
  max-width: 700px;
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

.purchase-layout {
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

.purchase-information-grid {
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
.form-group select {
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

.field-error {
  color: #dc2626;
  font-size: 12px;
  line-height: 1.4;
}

.field-information {
  color: #94a3b8;
  font-size: 11px;
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

.purchase-item {
  padding: 17px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #f8fafc;
}

.item-number {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.item-number strong {
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
    120px
    180px;
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

.purchase-summary {
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
  background: #ecfdf5;
}

.summary-total span,
.summary-total strong {
  display: block;
}

.summary-total span {
  color: #047857;
  font-size: 12px;
  font-weight: 700;
}

.summary-total strong {
  margin-top: 6px;
  color: #065f46;
  font-size: 23px;
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
  .purchase-layout {
    grid-template-columns: 1fr;
  }

  .purchase-summary {
    position: static;
  }

  .item-grid {
    grid-template-columns:
      minmax(0, 1fr)
      120px
      180px;
  }
}

@media (max-width: 760px) {
  .purchase-information-grid,
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