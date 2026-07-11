<script setup lang="ts">
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  computed,
  nextTick,
  onMounted,
  reactive,
  ref,
} from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'

import {
  getCurrentCashSession,
} from '@/services/cashSessionService'

import {
  getAvailablePromotions,
} from '@/services/promotionService'

import {
  createSale,
  getPosProducts,
} from '@/services/saleService'

import type {
  CashSessionItem,
} from '@/types/cashSession'

import type {
  PromotionItem,
} from '@/types/promotion'

import type {
  CreateSalePayload,
  PosProduct,
  PosProductFilters,
  PosProductPaginationMeta,
  SaleValidationErrors,
} from '@/types/sale'

interface ApiErrorResponse {
  message?: string
  errors?: SaleValidationErrors
}

interface PosCartItem {
  product: PosProduct
  quantity: number
}

const router = useRouter()

const searchInput =
  ref<HTMLInputElement | null>(null)

const currentSession =
  ref<CashSessionItem | null>(null)

const products =
  ref<PosProduct[]>([])

const productPagination =
  ref<PosProductPaginationMeta | null>(
    null,
  )

const promotions =
  ref<PromotionItem[]>([])

const cart =
  ref<PosCartItem[]>([])

const selectedPromotionId =
  ref<number | ''>('')

const amountPaid = ref('')
const notes = ref('')

const isInitializing = ref(false)
const isLoadingProducts = ref(false)
const isSubmitting = ref(false)

const pageErrorMessage = ref('')

const formErrors =
  ref<SaleValidationErrors>({})

const productFilters =
  reactive<PosProductFilters>({
    search: '',
    category_id: '',
    page: 1,
    per_page: 20,
  })

const selectedPromotion = computed(() => {
  if (selectedPromotionId.value === '') {
    return null
  }

  return (
    promotions.value.find(
      (promotion) =>
        promotion.id ===
        selectedPromotionId.value,
    ) ?? null
  )
})

const totalQuantity = computed(() => {
  return cart.value.reduce(
    (total, item) =>
      total + item.quantity,
    0,
  )
})

const subtotal = computed(() => {
  return roundMoney(
    cart.value.reduce(
      (total, item) => {
        return (
          total +
          item.product.selling_price *
            item.quantity
        )
      },
      0,
    ),
  )
})

const promotionWarning = computed(() => {
  const promotion =
    selectedPromotion.value

  if (!promotion) {
    return null
  }

  if (
    subtotal.value <
    promotion.minimum_purchase
  ) {
    return (
      `Minimal pembelian promo ` +
      `${promotion.code} adalah ` +
      `${formatCurrency(
        promotion.minimum_purchase,
      )}.`
    )
  }

  return null
})

const discountAmount = computed(() => {
  const promotion =
    selectedPromotion.value

  if (
    !promotion ||
    promotionWarning.value
  ) {
    return 0
  }

  let discount = 0

  if (
    promotion.discount_type ===
    'percentage'
  ) {
    discount =
      subtotal.value *
      (
        promotion.discount_value /
        100
      )

    if (
      promotion.maximum_discount !==
      null
    ) {
      discount = Math.min(
        discount,
        promotion.maximum_discount,
      )
    }
  } else {
    discount =
      promotion.discount_value
  }

  return roundMoney(
    Math.min(
      discount,
      subtotal.value,
    ),
  )
})

const totalAmount = computed(() => {
  return roundMoney(
    subtotal.value -
    discountAmount.value,
  )
})

const amountPaidNumber = computed(() => {
  const value = Number(amountPaid.value)

  return Number.isFinite(value)
    ? value
    : 0
})

const changeAmount = computed(() => {
  if (
    amountPaidNumber.value <
    totalAmount.value
  ) {
    return 0
  }

  return roundMoney(
    amountPaidNumber.value -
    totalAmount.value,
  )
})

const paymentShortage = computed(() => {
  if (
    amountPaid.value === '' ||
    amountPaidNumber.value >=
      totalAmount.value
  ) {
    return 0
  }

  return roundMoney(
    totalAmount.value -
    amountPaidNumber.value,
  )
})

const paymentSuggestions = computed(() => {
  if (totalAmount.value <= 0) {
    return []
  }

  const roundedTenThousand =
    Math.ceil(
      totalAmount.value / 10_000,
    ) * 10_000

  const roundedFiftyThousand =
    Math.ceil(
      totalAmount.value / 50_000,
    ) * 50_000

  const suggestions = [
    totalAmount.value,
    roundedTenThousand,
    roundedFiftyThousand,
    50_000,
    100_000,
    200_000,
  ]

  return Array.from(
    new Set(suggestions),
  )
    .filter(
      (value) =>
        value >= totalAmount.value,
    )
    .sort(
      (first, second) =>
        first - second,
    )
    .slice(0, 4)
})

const canCheckout = computed(() => {
  return (
    cart.value.length > 0 &&
    totalAmount.value > 0 &&
    amountPaid.value !== '' &&
    amountPaidNumber.value >=
      totalAmount.value &&
    !promotionWarning.value &&
    !isSubmitting.value
  )
})

async function initializePos():
  Promise<void> {
  isInitializing.value = true
  pageErrorMessage.value = ''

  try {
    const sessionResponse =
      await getCurrentCashSession()

    const session =
      sessionResponse.session

    if (
      !session ||
      !session.can_use_pos
    ) {
      toast.warning(
        'Buka sesi kasir milik Anda sebelum menggunakan POS.',
      )

      await router.replace({
        name: 'cashier.session',
      })

      return
    }

    currentSession.value = session

    await Promise.all([
      loadProducts(),
      loadPromotions(),
    ])

    await focusSearch()
  } catch (error: unknown) {
    if (
      axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      if (!error.response) {
        pageErrorMessage.value =
          'Tidak dapat terhubung ke server Laravel.'

        return
      }

      if (
        error.response.status === 401
      ) {
        pageErrorMessage.value =
          'Session login sudah tidak valid.'

        return
      }

      if (
        error.response.status === 403
      ) {
        pageErrorMessage.value =
          error.response.data.message ??
          'Anda tidak memiliki akses ke POS.'

        return
      }
    }

    pageErrorMessage.value =
      'Halaman POS gagal disiapkan.'
  } finally {
    isInitializing.value = false
  }
}

async function loadProducts():
  Promise<void> {
  isLoadingProducts.value = true

  try {
    const response =
      await getPosProducts(
        productFilters,
      )

    products.value =
      response.data

    productPagination.value =
      response.meta
  } catch (error: unknown) {
    if (
      axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      pageErrorMessage.value =
        error.response?.data.message ??
        'Produk POS gagal dimuat.'
    } else {
      pageErrorMessage.value =
        'Produk POS gagal dimuat.'
    }
  } finally {
    isLoadingProducts.value = false
  }
}

async function loadPromotions():
  Promise<void> {
  try {
    promotions.value =
      await getAvailablePromotions()
  } catch (error: unknown) {
    if (
      axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      pageErrorMessage.value =
        error.response?.data.message ??
        'Promo aktif gagal dimuat.'
    } else {
      pageErrorMessage.value =
        'Promo aktif gagal dimuat.'
    }
  }
}

async function submitProductSearch():
  Promise<void> {
  productFilters.page = 1

  await loadProducts()

  const searchTerm =
    productFilters.search
      .trim()
      .toLowerCase()

  if (!searchTerm) {
    return
  }

  const exactProduct =
    products.value.find(
      (product) => {
        const barcode =
          product.barcode
            ?.toLowerCase()

        const sku =
          product.sku.toLowerCase()

        return (
          barcode === searchTerm ||
          sku === searchTerm
        )
      },
    )

  if (exactProduct) {
    addProductToCart(
      exactProduct,
    )

    productFilters.search = ''

    await loadProducts()
    await focusSearch()
  }
}

async function resetProductSearch():
  Promise<void> {
  productFilters.search = ''
  productFilters.page = 1

  await loadProducts()
  await focusSearch()
}

async function changeProductPage(
  page: number,
): Promise<void> {
  const lastPage =
    productPagination.value
      ?.last_page ?? 1

  if (
    page < 1 ||
    page > lastPage ||
    page === productFilters.page
  ) {
    return
  }

  productFilters.page = page

  await loadProducts()
}

function addProductToCart(
  product: PosProduct,
): void {
  clearItemErrors()

  const existingItem =
    cart.value.find(
      (item) =>
        item.product.id ===
        product.id,
    )

  if (existingItem) {
    if (
      existingItem.quantity >=
      product.current_stock
    ) {
      toast.warning(
        `Stok ${product.name} hanya ${product.current_stock} ${product.unit}.`,
      )

      return
    }

    existingItem.quantity += 1

    return
  }

  cart.value.push({
    product,
    quantity: 1,
  })
}

function incrementQuantity(
  item: PosCartItem,
): void {
  clearItemErrors()

  if (
    item.quantity >=
    item.product.current_stock
  ) {
    toast.warning(
      `Stok ${item.product.name} hanya ${item.product.current_stock} ${item.product.unit}.`,
    )

    return
  }

  item.quantity += 1
}

function decrementQuantity(
  item: PosCartItem,
): void {
  clearItemErrors()

  if (item.quantity <= 1) {
    removeCartItem(item)

    return
  }

  item.quantity -= 1
}

function handleQuantityInput(
  item: PosCartItem,
  event: Event,
): void {
  const input =
    event.target as HTMLInputElement

  const quantity =
    Number(input.value)

  if (
    !Number.isInteger(quantity) ||
    quantity < 1
  ) {
    item.quantity = 1

    return
  }

  item.quantity = Math.min(
    quantity,
    item.product.current_stock,
  )
}

function removeCartItem(
  item: PosCartItem,
): void {
  cart.value =
    cart.value.filter(
      (cartItem) =>
        cartItem.product.id !==
        item.product.id,
    )

  clearItemErrors()
}

function clearCart(): void {
  cart.value = []
  selectedPromotionId.value = ''
  amountPaid.value = ''
  notes.value = ''
  formErrors.value = {}
  pageErrorMessage.value = ''
}

function applyPaymentSuggestion(
  value: number,
): void {
  amountPaid.value =
    String(value)

  clearFieldError(
    'amount_paid',
  )
}

function validateSale(): boolean {
  const errors:
    SaleValidationErrors = {}

  if (cart.value.length === 0) {
    errors.items = [
      'Keranjang belanja masih kosong.',
    ]
  }

  if (promotionWarning.value) {
    errors.promotion_id = [
      promotionWarning.value,
    ]
  }

  if (amountPaid.value === '') {
    errors.amount_paid = [
      'Uang diterima wajib diisi.',
    ]
  } else if (
    !Number.isFinite(
      amountPaidNumber.value,
    ) ||
    amountPaidNumber.value < 0
  ) {
    errors.amount_paid = [
      'Uang diterima tidak valid.',
    ]
  } else if (
    amountPaidNumber.value <
    totalAmount.value
  ) {
    errors.amount_paid = [
      `Uang diterima kurang ${formatCurrency(
        paymentShortage.value,
      )}.`,
    ]
  }

  formErrors.value = errors

  return (
    Object.keys(errors).length === 0
  )
}

function buildPayload():
  CreateSalePayload {
  return {
    promotion_id:
      selectedPromotionId.value === ''
        ? null
        : selectedPromotionId.value,

    payment_method: 'cash',

    amount_paid:
      amountPaidNumber.value,

    notes:
      notes.value.trim() ||
      null,

    items:
      cart.value.map(
        (item) => ({
          product_id:
            item.product.id,

          quantity:
            item.quantity,
        }),
      ),
  }
}

async function submitSale():
  Promise<void> {
  if (!validateSale()) {
    pageErrorMessage.value =
      'Periksa kembali transaksi yang ditandai.'

    return
  }

  const confirmation =
    await Swal.fire({
      title: 'Proses pembayaran?',

      html: `
        <div style="text-align:left; line-height:1.8">
          <div>
            Jumlah barang:
            <strong>${totalQuantity.value}</strong>
          </div>
          <div>
            Total bayar:
            <strong>${formatCurrency(
              totalAmount.value,
            )}</strong>
          </div>
          <div>
            Uang diterima:
            <strong>${formatCurrency(
              amountPaidNumber.value,
            )}</strong>
          </div>
          <div>
            Kembalian:
            <strong>${formatCurrency(
              changeAmount.value,
            )}</strong>
          </div>
        </div>
      `,

      icon: 'question',

      showCancelButton: true,

      confirmButtonText:
        'Ya, selesaikan transaksi',

      cancelButtonText:
        'Periksa kembali',

      reverseButtons: true,
    })

  if (!confirmation.isConfirmed) {
    return
  }

  isSubmitting.value = true
  pageErrorMessage.value = ''
  formErrors.value = {}

  try {
    const response =
      await createSale(
        buildPayload(),
      )

    toast.success(
      response.message,
      {
        description:
          response.sale.sale_number,
      },
    )

    const completedSale =
      response.sale

    clearCart()

    await Promise.all([
      loadProducts(),
      loadPromotions(),
    ])

    await Swal.fire({
      title:
        'Transaksi berhasil',

      html: `
        <div style="line-height:1.8">
          <div>
            ${completedSale.sale_number}
          </div>
          <div style="font-size:13px; color:#64748b">
            Total pembayaran
          </div>
          <div style="font-size:24px; font-weight:800">
            ${formatCurrency(
              completedSale.total_amount,
            )}
          </div>
          <div style="margin-top:12px; font-size:13px; color:#64748b">
            Kembalian
          </div>
          <div style="font-size:24px; font-weight:800; color:#047857">
            ${formatCurrency(
              completedSale.change_amount,
            )}
          </div>
        </div>
      `,

      icon: 'success',

      confirmButtonText:
        'Transaksi Baru',
    })

    await focusSearch()
  } catch (error: unknown) {
    handleSaleError(error)
  } finally {
    isSubmitting.value = false
  }
}

function handleSaleError(
  error: unknown,
): void {
  if (
    !axios.isAxiosError<ApiErrorResponse>(
      error,
    )
  ) {
    pageErrorMessage.value =
      'Transaksi penjualan gagal disimpan.'

    return
  }

  if (!error.response) {
    pageErrorMessage.value =
      'Tidak dapat terhubung ke server Laravel.'

    return
  }

  const data =
    error.response.data

  if (
    error.response.status === 422
  ) {
    formErrors.value =
      data.errors ?? {}

    pageErrorMessage.value =
      data.errors?.session?.[0] ??
      data.message ??
      'Periksa kembali transaksi.'

    const sessionError =
    data.errors?.session?.[0]

    if (sessionError) {
    toast.error(sessionError)
    }

    return
  }

  if (
    error.response.status === 403
  ) {
    pageErrorMessage.value =
      data.message ??
      'Anda tidak memiliki akses untuk melakukan transaksi.'

    return
  }

  pageErrorMessage.value =
    data.message ??
    'Transaksi penjualan gagal disimpan.'
}

function firstError(
  field: string,
): string | null {
  return (
    formErrors.value[field]?.[0] ??
    null
  )
}

function itemError(
  index: number,
): string | null {
  return (
    firstError(
      `items.${index}.quantity`,
    )
    ??
    firstError(
      `items.${index}.product_id`,
    )
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

function clearItemErrors(): void {
  const nextErrors = {
    ...formErrors.value,
  }

  Object.keys(nextErrors)
    .filter(
      (key) =>
        key === 'items' ||
        key.startsWith('items.'),
    )
    .forEach((key) => {
      delete nextErrors[key]
    })

  formErrors.value = nextErrors
}

async function focusSearch():
  Promise<void> {
  await nextTick()

  searchInput.value?.focus()
}

function openSessionPage(): void {
  router.push({
    name: 'cashier.session',
  })
}

function roundMoney(
  value: number,
): number {
  return Math.round(
    (
      value +
      Number.EPSILON
    ) * 100,
  ) / 100
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

function productInitial(
  productName: string,
): string {
  return productName
    .charAt(0)
    .toUpperCase()
}

onMounted(() => {
  initializePos()
})
</script>

<template>
  <div class="pos-page">
    <header class="pos-header">
      <div class="brand">
        <div class="brand-icon">
          S
        </div>

        <div>
          <strong>StockFlow POS</strong>

          <span>
            {{
              currentSession
                ?.session_number ??
              'Memeriksa sesi...'
            }}
          </span>
        </div>
      </div>

      <div class="session-information">
        <div>
          <span>Kasir</span>

          <strong>
            {{
              currentSession
                ?.cashier.name ?? '-'
            }}
          </strong>
        </div>

        <div>
          <span>Dibuka</span>

          <strong>
            {{
              formatDateTime(
                currentSession
                  ?.opened_at ?? null,
              )
            }}
          </strong>
        </div>

        <button
          type="button"
          class="session-button"
          @click="openSessionPage"
        >
          Sesi Kasir
        </button>
      </div>
    </header>

    <div
      v-if="pageErrorMessage"
      class="alert-error"
    >
      {{ pageErrorMessage }}
    </div>

    <div
      v-if="isInitializing"
      class="initializing-state"
    >
      Menyiapkan StockFlow POS...
    </div>

    <main
      v-else
      class="pos-layout"
    >
      <section class="product-section">
        <header class="product-header">
          <div>
            <h1>Pilih Produk</h1>

            <p>
              Cari berdasarkan nama, SKU,
              atau pindai barcode.
            </p>
          </div>
        </header>

        <form
          class="search-form"
          @submit.prevent="
            submitProductSearch
          "
        >
          <input
            ref="searchInput"
            v-model="
              productFilters.search
            "
            type="search"
            placeholder="Cari produk / scan barcode..."
          />

          <button
            type="submit"
            class="search-button"
          >
            Cari
          </button>

          <button
            v-if="
              productFilters.search
            "
            type="button"
            class="reset-search-button"
            @click="
              resetProductSearch
            "
          >
            Reset
          </button>
        </form>

        <div
          v-if="isLoadingProducts"
          class="product-state"
        >
          Memuat produk...
        </div>

        <div
          v-else-if="
            products.length === 0
          "
          class="product-state"
        >
          Tidak ada produk aktif dengan stok
          dan harga jual yang tersedia.
        </div>

        <div
          v-else
          class="product-grid"
        >
          <button
            v-for="product in products"
            :key="product.id"
            type="button"
            class="product-card"
            @click="
              addProductToCart(
                product,
              )
            "
          >
            <img
              v-if="product.image_url"
              :src="product.image_url"
              :alt="product.name"
              class="product-image"
            />

            <div
              v-else
              class="product-placeholder"
            >
              {{
                productInitial(
                  product.name,
                )
              }}
            </div>

            <div class="product-information">
              <small>
                {{
                  product.category.name
                }}
              </small>

              <strong>
                {{ product.name }}
              </strong>

              <span>
                {{ product.sku }}
              </span>

              <div class="product-bottom">
                <b>
                  {{
                    formatCurrency(
                      product.selling_price,
                    )
                  }}
                </b>

                <span>
                  Stok
                  {{ product.current_stock }}
                  {{ product.unit }}
                </span>
              </div>
            </div>
          </button>
        </div>

        <footer
          v-if="
            productPagination &&
            productPagination.total > 0
          "
          class="product-pagination"
        >
          <span>
            {{
              productPagination.from
            }}
            –
            {{
              productPagination.to
            }}
            dari
            {{
              productPagination.total
            }}
            produk
          </span>

          <div>
            <button
              type="button"
              :disabled="
                productPagination
                  .current_page === 1
              "
              @click="
                changeProductPage(
                  productPagination
                    .current_page - 1,
                )
              "
            >
              ‹
            </button>

            <strong>
              {{
                productPagination
                  .current_page
              }}
              /
              {{
                productPagination
                  .last_page
              }}
            </strong>

            <button
              type="button"
              :disabled="
                productPagination
                  .current_page ===
                productPagination
                  .last_page
              "
              @click="
                changeProductPage(
                  productPagination
                    .current_page + 1,
                )
              "
            >
              ›
            </button>
          </div>
        </footer>
      </section>

      <aside class="cart-panel">
        <header class="cart-header">
          <div>
            <h2>Keranjang</h2>

            <span>
              {{ totalQuantity }}
              barang
            </span>
          </div>

          <button
            v-if="cart.length > 0"
            type="button"
            class="clear-cart-button"
            @click="clearCart"
          >
            Kosongkan
          </button>
        </header>

        <div
          v-if="cart.length === 0"
          class="empty-cart"
        >
          <div>🛒</div>

          <strong>
            Keranjang masih kosong
          </strong>

          <p>
            Klik produk di sebelah kiri atau
            pindai barcode.
          </p>
        </div>

        <div
          v-else
          class="cart-items"
        >
          <article
            v-for="(
              item,
              index
            ) in cart"
            :key="item.product.id"
            class="cart-item"
          >
            <div class="cart-item-main">
              <div>
                <strong>
                  {{ item.product.name }}
                </strong>

                <span>
                  {{
                    formatCurrency(
                      item.product
                        .selling_price,
                    )
                  }}
                  / {{ item.product.unit }}
                </span>
              </div>

              <button
                type="button"
                class="remove-item-button"
                @click="
                  removeCartItem(
                    item,
                  )
                "
              >
                ×
              </button>
            </div>

            <div class="cart-item-bottom">
              <div class="quantity-control">
                <button
                  type="button"
                  @click="
                    decrementQuantity(
                      item,
                    )
                  "
                >
                  −
                </button>

                <input
                  :value="item.quantity"
                  type="number"
                  min="1"
                  :max="
                    item.product
                      .current_stock
                  "
                  @change="
                    handleQuantityInput(
                      item,
                      $event,
                    )
                  "
                />

                <button
                  type="button"
                  :disabled="
                    item.quantity >=
                    item.product
                      .current_stock
                  "
                  @click="
                    incrementQuantity(
                      item,
                    )
                  "
                >
                  +
                </button>
              </div>

              <strong>
                {{
                  formatCurrency(
                    item.product
                      .selling_price *
                    item.quantity,
                  )
                }}
              </strong>
            </div>

            <span
              v-if="itemError(index)"
              class="field-error"
            >
              {{ itemError(index) }}
            </span>
          </article>
        </div>

        <section class="checkout-section">
          <div class="form-group">
            <label for="pos-promotion">
              Promo
            </label>

            <select
              id="pos-promotion"
              v-model="
                selectedPromotionId
              "
              @change="
                clearFieldError(
                  'promotion_id',
                )
              "
            >
              <option value="">
                Tanpa promo
              </option>

              <option
                v-for="
                  promotion in promotions
                "
                :key="promotion.id"
                :value="promotion.id"
                :disabled="
                  subtotal <
                  promotion
                    .minimum_purchase
                "
              >
                {{ promotion.code }} —
                {{ promotion.name }}
              </option>
            </select>

            <span
              v-if="promotionWarning"
              class="field-error"
            >
              {{ promotionWarning }}
            </span>

            <span
              v-else-if="
                firstError(
                  'promotion_id',
                )
              "
              class="field-error"
            >
              {{
                firstError(
                  'promotion_id',
                )
              }}
            </span>

            <small
              v-if="selectedPromotion"
              class="promotion-description"
            >
              {{
                selectedPromotion
                  .discount_type ===
                'percentage'
                  ? `${selectedPromotion.discount_value}%`
                  : formatCurrency(
                      selectedPromotion
                        .discount_value,
                    )
              }}
              diskon
            </small>
          </div>

          <div class="totals">
            <div>
              <span>Subtotal</span>

              <strong>
                {{
                  formatCurrency(
                    subtotal,
                  )
                }}
              </strong>
            </div>

            <div>
              <span>Diskon</span>

              <strong class="discount-text">
                −
                {{
                  formatCurrency(
                    discountAmount,
                  )
                }}
              </strong>
            </div>

            <div class="grand-total">
              <span>Total Bayar</span>

              <strong>
                {{
                  formatCurrency(
                    totalAmount,
                  )
                }}
              </strong>
            </div>
          </div>

          <div class="payment-method">
            <span>Metode Pembayaran</span>

            <button
              type="button"
              class="active"
            >
              Tunai / Cash
            </button>

            <small>
              QRIS, transfer, dan debit akan
              ditambahkan pada pengembangan
              berikutnya.
            </small>
          </div>

          <div class="form-group">
            <label for="amount-paid">
              Uang Diterima
            </label>

            <div class="currency-input">
              <span>Rp</span>

              <input
                id="amount-paid"
                v-model="amountPaid"
                type="number"
                min="0"
                step="0.01"
                placeholder="Masukkan uang pelanggan"
                @input="
                  clearFieldError(
                    'amount_paid',
                  )
                "
              />
            </div>

            <div
              v-if="
                paymentSuggestions
                  .length > 0
              "
              class="payment-suggestions"
            >
              <button
                v-for="
                  suggestion in
                  paymentSuggestions
                "
                :key="suggestion"
                type="button"
                @click="
                  applyPaymentSuggestion(
                    suggestion,
                  )
                "
              >
                {{
                  suggestion ===
                  totalAmount
                    ? 'Uang Pas'
                    : formatCurrency(
                        suggestion,
                      )
                }}
              </button>
            </div>

            <span
              v-if="
                firstError(
                  'amount_paid',
                )
              "
              class="field-error"
            >
              {{
                firstError(
                  'amount_paid',
                )
              }}
            </span>

            <span
              v-else-if="
                paymentShortage > 0
              "
              class="field-error"
            >
              Uang masih kurang
              {{
                formatCurrency(
                  paymentShortage,
                )
              }}.
            </span>
          </div>

          <div
            :class="[
              'change-card',
              amountPaidNumber >=
                totalAmount &&
              totalAmount > 0
                ? 'ready'
                : '',
            ]"
          >
            <span>Kembalian</span>

            <strong>
              {{
                formatCurrency(
                  changeAmount,
                )
              }}
            </strong>
          </div>

          <div class="form-group">
            <label for="sale-notes">
              Catatan Transaksi
            </label>

            <textarea
              id="sale-notes"
              v-model="notes"
              rows="2"
              maxlength="1000"
              placeholder="Opsional"
            />
          </div>

          <span
            v-if="firstError('items')"
            class="field-error"
          >
            {{ firstError('items') }}
          </span>

          <button
            type="button"
            class="checkout-button"
            :disabled="!canCheckout"
            @click="submitSale"
          >
            {{
              isSubmitting
                ? 'Memproses Transaksi...'
                : `Bayar ${formatCurrency(
                    totalAmount,
                  )}`
            }}
          </button>
        </section>
      </aside>
    </main>
  </div>
</template>

<style scoped>
.pos-page {
  min-height: 100vh;
  padding: 18px;
  background: #f1f5f9;
}

.pos-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;

  margin-bottom: 18px;
  padding: 14px 18px;

  border: 1px solid #e2e8f0;
  border-radius: 16px;

  background: white;
}

.brand {
  display: flex;
  align-items: center;
  gap: 11px;
}

.brand-icon {
  width: 43px;
  height: 43px;

  display: grid;
  place-items: center;

  border-radius: 12px;
  background: #047857;
  color: white;

  font-size: 19px;
  font-weight: 800;
}

.brand strong,
.brand span {
  display: block;
}

.brand strong {
  color: #0f172a;
}

.brand span {
  margin-top: 3px;
  color: #64748b;
  font-size: 11px;
}

.session-information {
  display: flex;
  align-items: center;
  gap: 25px;
}

.session-information span,
.session-information strong {
  display: block;
}

.session-information span {
  color: #64748b;
  font-size: 10px;
}

.session-information strong {
  margin-top: 3px;
  color: #0f172a;
  font-size: 12px;
}

.session-button {
  height: 40px;
  padding: 0 14px;

  border: 0;
  border-radius: 9px;

  background: #e2e8f0;
  color: #334155;

  font-weight: 700;
}

.alert-error {
  margin-bottom: 15px;
  padding: 13px 15px;

  border: 1px solid #fecaca;
  border-radius: 11px;

  background: #fef2f2;
  color: #b91c1c;
}

.initializing-state {
  min-height: 500px;

  display: grid;
  place-items: center;

  color: #64748b;
}

.pos-layout {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    410px;
  gap: 18px;
  align-items: start;
}

.product-section,
.cart-panel {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
}

.product-section {
  min-width: 0;
  padding: 20px;
}

.product-header h1 {
  margin: 0;
  color: #0f172a;
  font-size: 22px;
}

.product-header p {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 13px;
}

.search-form {
  display: flex;
  gap: 9px;
  margin: 18px 0;
}

.search-form input {
  width: 100%;
  height: 46px;
  padding: 0 14px;

  border: 1px solid #cbd5e1;
  border-radius: 11px;

  outline: none;
  font: inherit;
}

.search-form input:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.search-button,
.reset-search-button {
  padding: 0 16px;
  border: 0;
  border-radius: 10px;
  font-weight: 700;
}

.search-button {
  background: #047857;
  color: white;
}

.reset-search-button {
  background: #e2e8f0;
  color: #334155;
}

.product-state {
  padding: 70px 20px;
  color: #64748b;
  text-align: center;
}

.product-grid {
  display: grid;
  grid-template-columns:
    repeat(
      auto-fill,
      minmax(165px, 1fr)
    );
  gap: 13px;
}

.product-card {
  min-width: 0;
  padding: 0;

  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 13px;

  background: white;
  text-align: left;

  transition:
    transform 0.15s,
    border-color 0.15s,
    box-shadow 0.15s;
}

.product-card:hover {
  transform: translateY(-2px);
  border-color: #6ee7b7;
  box-shadow:
    0 10px 25px rgb(15 23 42 / 8%);
}

.product-image,
.product-placeholder {
  width: 100%;
  height: 125px;
}

.product-image {
  object-fit: cover;
}

.product-placeholder {
  display: grid;
  place-items: center;

  background: #d1fae5;
  color: #047857;

  font-size: 30px;
  font-weight: 800;
}

.product-information {
  padding: 12px;
}

.product-information small,
.product-information strong,
.product-information > span {
  display: block;
}

.product-information small {
  color: #047857;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
}

.product-information strong {
  min-height: 38px;
  margin-top: 5px;

  color: #0f172a;
  font-size: 13px;
  line-height: 1.45;
}

.product-information > span {
  margin-top: 4px;
  color: #94a3b8;
  font-size: 10px;
}

.product-bottom {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;

  margin-top: 11px;
}

.product-bottom b {
  color: #047857;
  font-size: 13px;
}

.product-bottom span {
  color: #64748b;
  font-size: 9px;
  text-align: right;
}

.product-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;

  margin-top: 19px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;

  color: #64748b;
  font-size: 12px;
}

.product-pagination div {
  display: flex;
  align-items: center;
  gap: 10px;
}

.product-pagination button {
  width: 35px;
  height: 35px;

  border: 0;
  border-radius: 9px;

  background: #e2e8f0;
  color: #334155;

  font-size: 19px;
}

.product-pagination button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.cart-panel {
  position: sticky;
  top: 18px;
  max-height: calc(100vh - 36px);
  overflow-y: auto;
}

.cart-header {
  position: sticky;
  top: 0;
  z-index: 3;

  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 18px;
  border-bottom: 1px solid #e2e8f0;

  background: white;
}

.cart-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 19px;
}

.cart-header span {
  display: block;
  margin-top: 3px;
  color: #64748b;
  font-size: 11px;
}

.clear-cart-button {
  padding: 7px 10px;
  border: 0;
  border-radius: 8px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 11px;
  font-weight: 700;
}

.empty-cart {
  display: grid;
  justify-items: center;
  padding: 50px 20px;
  text-align: center;
}

.empty-cart div {
  font-size: 38px;
}

.empty-cart strong {
  margin-top: 13px;
  color: #0f172a;
}

.empty-cart p {
  max-width: 250px;
  margin: 6px 0 0;
  color: #64748b;
  font-size: 12px;
  line-height: 1.5;
}

.cart-items {
  max-height: 310px;
  overflow-y: auto;
  padding: 5px 18px;
}

.cart-item {
  padding: 13px 0;
  border-bottom: 1px solid #e2e8f0;
}

.cart-item-main,
.cart-item-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 13px;
}

.cart-item-main strong,
.cart-item-main span {
  display: block;
}

.cart-item-main strong {
  color: #0f172a;
  font-size: 13px;
}

.cart-item-main span {
  margin-top: 4px;
  color: #64748b;
  font-size: 10px;
}

.remove-item-button {
  width: 29px;
  height: 29px;

  border: 0;
  border-radius: 8px;

  background: #fee2e2;
  color: #b91c1c;

  font-size: 17px;
}

.cart-item-bottom {
  margin-top: 10px;
}

.cart-item-bottom > strong {
  color: #0f172a;
  font-size: 13px;
}

.quantity-control {
  display: flex;
  align-items: center;
}

.quantity-control button,
.quantity-control input {
  width: 35px;
  height: 33px;
  border: 1px solid #cbd5e1;
  text-align: center;
}

.quantity-control button {
  background: #f8fafc;
  color: #334155;
  font-size: 16px;
  font-weight: 700;
}

.quantity-control button:first-child {
  border-radius: 8px 0 0 8px;
}

.quantity-control button:last-child {
  border-radius: 0 8px 8px 0;
}

.quantity-control button:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.quantity-control input {
  border-right: 0;
  border-left: 0;
  outline: none;
}

.checkout-section {
  padding: 18px;
  border-top: 1px solid #e2e8f0;
}

.form-group {
  display: grid;
  gap: 7px;
  margin-bottom: 15px;
}

.form-group label {
  color: #475569;
  font-size: 12px;
  font-weight: 700;
}

.form-group select,
.form-group input,
.form-group textarea {
  width: 100%;

  border: 1px solid #cbd5e1;
  border-radius: 10px;

  outline: none;
  background: white;
  color: #0f172a;
  font: inherit;
}

.form-group select,
.form-group input {
  height: 43px;
  padding: 0 11px;
}

.form-group textarea {
  padding: 10px;
  resize: vertical;
}

.form-group select:focus,
.form-group input:focus,
.form-group textarea:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.promotion-description {
  color: #047857;
  font-size: 10px;
  font-weight: 700;
}

.totals {
  display: grid;
  gap: 9px;

  margin-bottom: 16px;
  padding: 14px;

  border-radius: 11px;
  background: #f8fafc;
}

.totals > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.totals span {
  color: #64748b;
  font-size: 12px;
}

.totals strong {
  color: #0f172a;
  font-size: 13px;
}

.discount-text {
  color: #dc2626 !important;
}

.grand-total {
  margin-top: 3px;
  padding-top: 11px;
  border-top: 1px solid #cbd5e1;
}

.grand-total span {
  color: #0f172a;
  font-weight: 700;
}

.grand-total strong {
  color: #047857;
  font-size: 21px;
}

.payment-method {
  margin-bottom: 15px;
}

.payment-method > span {
  display: block;
  margin-bottom: 7px;
  color: #475569;
  font-size: 12px;
  font-weight: 700;
}

.payment-method button {
  width: 100%;
  height: 43px;

  border: 1px solid #059669;
  border-radius: 10px;

  background: #d1fae5;
  color: #047857;

  font-weight: 750;
}

.payment-method small {
  display: block;
  margin-top: 6px;
  color: #94a3b8;
  font-size: 9px;
  line-height: 1.4;
}

.currency-input {
  position: relative;
}

.currency-input > span {
  position: absolute;
  top: 50%;
  left: 12px;
  z-index: 1;
  transform: translateY(-50%);

  color: #64748b;
  font-weight: 700;
}

.currency-input input {
  padding-left: 39px;
}

.payment-suggestions {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 6px;
}

.payment-suggestions button {
  min-height: 34px;

  border: 1px solid #cbd5e1;
  border-radius: 8px;

  background: white;
  color: #334155;

  font-size: 10px;
  font-weight: 700;
}

.payment-suggestions button:hover {
  border-color: #059669;
  color: #047857;
}

.change-card {
  margin-bottom: 15px;
  padding: 13px;

  border-radius: 11px;
  background: #f1f5f9;
}

.change-card span,
.change-card strong {
  display: block;
}

.change-card span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.change-card strong {
  margin-top: 5px;
  color: #64748b;
  font-size: 22px;
}

.change-card.ready {
  background: #dcfce7;
}

.change-card.ready span,
.change-card.ready strong {
  color: #15803d;
}

.field-error {
  color: #dc2626;
  font-size: 11px;
  line-height: 1.4;
}

.checkout-button {
  width: 100%;
  min-height: 48px;

  border: 0;
  border-radius: 11px;

  background: #047857;
  color: white;

  font-size: 14px;
  font-weight: 800;
}

.checkout-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

@media (max-width: 1050px) {
  .pos-layout {
    grid-template-columns: 1fr;
  }

  .cart-panel {
    position: static;
    max-height: none;
  }
}

@media (max-width: 700px) {
  .pos-page {
    padding: 10px;
  }

  .pos-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .session-information {
    width: 100%;
    flex-wrap: wrap;
  }

  .session-button {
    margin-left: auto;
  }

  .product-grid {
    grid-template-columns:
      repeat(
        2,
        minmax(0, 1fr)
      );
  }

  .search-form {
    display: grid;
    grid-template-columns: 1fr auto;
  }

  .reset-search-button {
    grid-column: 1 / -1;
    height: 40px;
  }
}

@media (max-width: 430px) {
  .product-grid {
    grid-template-columns: 1fr;
  }

  .product-image,
  .product-placeholder {
    height: 155px;
  }
}
</style>