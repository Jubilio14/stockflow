<script setup lang="ts">
import axios from 'axios'
import Swal from 'sweetalert2'
import {
  computed,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue'
import { toast } from 'vue-sonner'

import { getCategories } from '@/services/categoryService'
import {
  createProduct,
  getProducts,
  toggleProductStatus,
  updateProduct,
} from '@/services/productService'

import type { CategoryItem } from '@/types/category'
import type {
  ProductFilters,
  ProductItem,
  ProductPaginationMeta,
  ProductValidationErrors,
} from '@/types/product'

interface ApiErrorResponse {
  message?: string
  errors?: ProductValidationErrors
}

interface ProductFormState {
  category_id: number | ''
  name: string
  sku: string
  barcode: string
  unit: string
  selling_price: string
  minimum_stock: string
  is_active: boolean
  image: File | null
}

const products = ref<ProductItem[]>([])
const categories = ref<CategoryItem[]>([])

const pagination =
  ref<ProductPaginationMeta | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const filters = reactive<ProductFilters>({
  search: '',
  category_id: '',
  status: '',
  stock_status: '',
  page: 1,
  per_page: 10,
})

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const editingProductId = ref<number | null>(null)

const modalErrorMessage = ref('')
const formErrors =
  ref<ProductValidationErrors>({})

const imageInput =
  ref<HTMLInputElement | null>(null)

const imagePreviewUrl = ref<string | null>(null)
const existingImageUrl = ref<string | null>(null)

const productForm = reactive<ProductFormState>({
  category_id: '',
  name: '',
  sku: '',
  barcode: '',
  unit: 'pcs',
  selling_price: '',
  minimum_stock: '0',
  is_active: true,
  image: null,
})

const isEditMode = computed(() => {
  return editingProductId.value !== null
})

const modalTitle = computed(() => {
  return isEditMode.value
    ? 'Edit Produk'
    : 'Tambah Produk'
})

const displayedImageUrl = computed(() => {
  return (
    imagePreviewUrl.value ??
    existingImageUrl.value
  )
})

async function loadProducts(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getProducts(filters)

    products.value = response.data
    pagination.value = response.meta
  } catch (error: unknown) {
    if (axios.isAxiosError<ApiErrorResponse>(error)) {
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
          'Anda tidak memiliki izin untuk mengakses produk.'
        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil daftar produk.'
  } finally {
    isLoading.value = false
  }
}

async function loadCategoryOptions(): Promise<void> {
  try {
    const response = await getCategories({
      search: '',
      status: 'active',
      page: 1,
      per_page: 100,
    })

    categories.value = response.data
  } catch {
    errorMessage.value =
      'Daftar kategori gagal dimuat.'
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1
  await loadProducts()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.category_id = ''
  filters.status = ''
  filters.stock_status = ''
  filters.page = 1

  await loadProducts()
}

async function changePage(
  page: number,
): Promise<void> {
  if (
    page < 1 ||
    page > (pagination.value?.last_page ?? 1) ||
    page === filters.page
  ) {
    return
  }

  filters.page = page
  await loadProducts()
}

function revokeImagePreview(): void {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = null
  }
}

function resetProductForm(): void {
  revokeImagePreview()

  productForm.category_id = ''
  productForm.name = ''
  productForm.sku = ''
  productForm.barcode = ''
  productForm.unit = 'pcs'
  productForm.selling_price = ''
  productForm.minimum_stock = '0'
  productForm.is_active = true
  productForm.image = null

  editingProductId.value = null
  existingImageUrl.value = null

  formErrors.value = {}
  modalErrorMessage.value = ''

  if (imageInput.value) {
    imageInput.value.value = ''
  }
}

function openCreateModal(): void {
  resetProductForm()
  isModalOpen.value = true
}

function ensureCurrentCategoryExists(
  product: ProductItem,
): void {
  const categoryAlreadyAvailable =
    categories.value.some(
      (category) =>
        category.id === product.category_id,
    )

  if (
    !categoryAlreadyAvailable &&
    product.category
  ) {
    categories.value.push(product.category)
  }
}

function openEditModal(
  product: ProductItem,
): void {
  closeActionMenu()
  resetProductForm()
  ensureCurrentCategoryExists(product)

  editingProductId.value = product.id

  productForm.category_id =
    product.category_id

  productForm.name = product.name
  productForm.sku = product.sku

  productForm.barcode =
    product.barcode ?? ''

  productForm.unit = product.unit

  productForm.selling_price =
    String(product.selling_price)

  productForm.minimum_stock =
    String(product.minimum_stock)

  existingImageUrl.value =
    product.image_url

  isModalOpen.value = true
}

function closeModal(): void {
  if (isSubmitting.value) {
    return
  }

  isModalOpen.value = false
  resetProductForm()
}

function handleImageChange(
  event: Event,
): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null

  revokeImagePreview()
  productForm.image = null

  if (!file) {
    return
  }

  const allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
  ]

  const maximumFileSize = 2 * 1024 * 1024

  if (!allowedTypes.includes(file.type)) {
    formErrors.value.image = [
      'Gambar harus berformat JPG, JPEG, PNG, atau WEBP.',
    ]

    input.value = ''
    return
  }

  if (file.size > maximumFileSize) {
    formErrors.value.image = [
      'Ukuran gambar maksimal 2 MB.',
    ]

    input.value = ''
    return
  }

  delete formErrors.value.image

  productForm.image = file

  imagePreviewUrl.value =
    URL.createObjectURL(file)
}

function clearSelectedImage(): void {
  revokeImagePreview()

  productForm.image = null

  if (imageInput.value) {
    imageInput.value.value = ''
  }

  delete formErrors.value.image
}

function validateProductForm(): boolean {
  const errors: ProductValidationErrors = {}

  if (productForm.category_id === '') {
    errors.category_id = [
      'Kategori wajib dipilih.',
    ]
  }

  if (!productForm.name.trim()) {
    errors.name = [
      'Nama produk wajib diisi.',
    ]
  }

  if (!productForm.sku.trim()) {
    errors.sku = [
      'SKU wajib diisi.',
    ]
  }

  if (!productForm.unit.trim()) {
    errors.unit = [
      'Satuan produk wajib diisi.',
    ]
  }

  if (productForm.selling_price === '') {
    errors.selling_price = [
      'Harga jual wajib diisi.',
    ]
  } else if (
    Number(productForm.selling_price) < 0
  ) {
    errors.selling_price = [
      'Harga jual tidak boleh negatif.',
    ]
  }

  if (productForm.minimum_stock === '') {
    errors.minimum_stock = [
      'Stok minimum wajib diisi.',
    ]
  } else if (
    !Number.isInteger(
      Number(productForm.minimum_stock),
    )
  ) {
    errors.minimum_stock = [
      'Stok minimum harus berupa bilangan bulat.',
    ]
  } else if (
    Number(productForm.minimum_stock) < 0
  ) {
    errors.minimum_stock = [
      'Stok minimum tidak boleh negatif.',
    ]
  }

  formErrors.value = errors

  return Object.keys(errors).length === 0
}

function buildProductFormData(): FormData {
  const formData = new FormData()

  formData.append(
    'category_id',
    String(productForm.category_id),
  )

  formData.append(
    'name',
    productForm.name.trim(),
  )

  formData.append(
    'sku',
    productForm.sku
      .trim()
      .toUpperCase(),
  )

  formData.append(
    'barcode',
    productForm.barcode.trim(),
  )

  formData.append(
    'unit',
    productForm.unit
      .trim()
      .toLowerCase(),
  )

  formData.append(
    'selling_price',
    productForm.selling_price,
  )

  formData.append(
    'minimum_stock',
    productForm.minimum_stock,
  )

  if (!isEditMode.value) {
    formData.append(
      'is_active',
      productForm.is_active ? '1' : '0',
    )
  }

  if (productForm.image) {
    formData.append(
      'image',
      productForm.image,
    )
  }

  return formData
}

async function submitProduct(): Promise<void> {
  if (!validateProductForm()) {
    return
  }

  isSubmitting.value = true
  modalErrorMessage.value = ''

  try {
    const productName =
      productForm.name.trim()

    const formData =
      buildProductFormData()

    let response

    if (
      isEditMode.value &&
      editingProductId.value !== null
    ) {
      response = await updateProduct(
        editingProductId.value,
        formData,
      )
    } else {
      response =
        await createProduct(formData)
    }

    isModalOpen.value = false
    resetProductForm()

    toast.success(response.message, {
      description: `${productName} berhasil disimpan.`,
    })

    filters.page = 1
    await loadProducts()
  } catch (error: unknown) {
    if (!axios.isAxiosError<ApiErrorResponse>(error)) {
      modalErrorMessage.value =
        'Terjadi kesalahan yang tidak dikenali.'
      return
    }

    if (!error.response) {
      modalErrorMessage.value =
        'Tidak dapat terhubung ke server Laravel.'
      return
    }

    const status = error.response.status
    const data = error.response.data

    if (status === 422) {
      formErrors.value = data.errors ?? {}

      if (
        Object.keys(formErrors.value).length === 0
      ) {
        modalErrorMessage.value =
          data.message ??
          'Data produk tidak valid.'
      }

      return
    }

    if (
      status === 401 ||
      status === 403 ||
      status === 409
    ) {
      modalErrorMessage.value =
        data.message ??
        'Produk tidak dapat disimpan.'
      return
    }

    modalErrorMessage.value =
      data.message ??
      'Terjadi kesalahan saat menyimpan produk.'
  } finally {
    isSubmitting.value = false
  }
}

async function confirmToggleStatus(
  product: ProductItem,
): Promise<void> {
  closeActionMenu()
  const willActivate = !product.is_active

  const result = await Swal.fire({
    title: willActivate
      ? 'Aktifkan produk?'
      : 'Nonaktifkan produk?',

    text: willActivate
      ? `${product.name} dapat digunakan kembali pada transaksi.`
      : `${product.name} tidak akan tersedia untuk transaksi baru.`,

    icon: 'warning',
    showCancelButton: true,

    confirmButtonText: willActivate
      ? 'Ya, aktifkan'
      : 'Ya, nonaktifkan',

    cancelButtonText: 'Batal',
    reverseButtons: true,
  })

  if (!result.isConfirmed) {
    return
  }

  try {
    Swal.fire({
      title: 'Memproses...',
      allowOutsideClick: false,
      allowEscapeKey: false,

      didOpen: () => {
        Swal.showLoading()
      },
    })

    const response =
      await toggleProductStatus(product.id)

    Swal.close()

    toast.success(response.message)

    await loadProducts()
  } catch (error: unknown) {
    Swal.close()

    if (
      axios.isAxiosError<ApiErrorResponse>(error) &&
      error.response?.data.message
    ) {
      toast.error(error.response.data.message)
      return
    }

    toast.error(
      'Status produk gagal diperbarui.',
    )
  }
}

function formatCurrency(
  value: number,
): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(value)
}

function stockStatusLabel(
  status: ProductItem['stock_status'],
): string {
  const labels: Record<
    ProductItem['stock_status'],
    string
  > = {
    available: 'Tersedia',
    low_stock: 'Stok Menipis',
    out_of_stock: 'Stok Habis',
  }

  return labels[status]
}

function productInitial(
  name: string,
): string {
  return name.charAt(0).toUpperCase()
}

function handleEscape(
  event: KeyboardEvent,
): void {
  if (event.key !== 'Escape') {
    return
  }

  if (isModalOpen.value) {
    closeModal()
    return
  }

  closeActionMenu()
}

watch(isModalOpen, (isOpen) => {
  document.body.style.overflow =
    isOpen ? 'hidden' : ''
})

onMounted(async () => {
  window.addEventListener(
    'keydown',
    handleEscape,
  )

  document.addEventListener(
    'click',
    closeActionMenu,
  )

  window.addEventListener(
    'resize',
    closeActionMenu,
  )

  window.addEventListener(
    'scroll',
    closeActionMenu,
    true,
  )

  await Promise.all([
    loadProducts(),
    loadCategoryOptions(),
  ])
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''

  revokeImagePreview()

  window.removeEventListener(
    'keydown',
    handleEscape,
  )

  document.removeEventListener(
  'click',
  closeActionMenu,
)

window.removeEventListener(
  'resize',
  closeActionMenu,
)

window.removeEventListener(
  'scroll',
  closeActionMenu,
  true,
)
})

const openActionProductId = ref<number | null>(null)

const actionMenuPosition = reactive({
  top: 0,
  left: 0,
})

const activeActionProduct = computed(() => {
  return (
    products.value.find(
      (product) =>
        product.id === openActionProductId.value,
    ) ?? null
  )
})

function closeActionMenu(): void {
  openActionProductId.value = null
}

function toggleActionMenu(
  product: ProductItem,
  event: MouseEvent,
): void {
  if (openActionProductId.value === product.id) {
    closeActionMenu()
    return
  }

  const button = event.currentTarget as HTMLElement
  const buttonPosition =
    button.getBoundingClientRect()

  const menuWidth = 180
  const menuHeight = 96
  const screenPadding = 12
  const menuGap = 6

  let top = buttonPosition.bottom + menuGap

  let left =
    buttonPosition.right - menuWidth

  if (
    top + menuHeight >
    window.innerHeight - screenPadding
  ) {
    top =
      buttonPosition.top -
      menuHeight -
      menuGap
  }

  left = Math.max(
    screenPadding,
    Math.min(
      left,
      window.innerWidth -
        menuWidth -
        screenPadding,
    ),
  )

  actionMenuPosition.top = top
  actionMenuPosition.left = left

  openActionProductId.value = product.id
}
</script>
<template>
  <section class="products-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">Master Data</p>
        <h2>Produk</h2>

        <p class="subtitle">
          Kelola produk, harga jual, kategori,
          dan batas minimum stok.
        </p>
      </div>

      <button
        type="button"
        class="primary-button"
        @click="openCreateModal"
      >
        Tambah Produk
      </button>
    </header>

    <section class="filter-card">
      <form
        class="filter-grid"
        @submit.prevent="applyFilters"
      >
        <div class="form-group search-field">
          <label for="product-search">
            Pencarian
          </label>

          <input
            id="product-search"
            v-model="filters.search"
            type="search"
            placeholder="Cari nama, SKU, atau barcode..."
          />
        </div>

        <div class="form-group">
          <label for="product-category">
            Kategori
          </label>

          <select
            id="product-category"
            v-model="filters.category_id"
          >
            <option value="">
              Semua kategori
            </option>

            <option
              v-for="category in categories"
              :key="category.id"
              :value="category.id"
            >
              {{ category.name }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="product-status">
            Status
          </label>

          <select
            id="product-status"
            v-model="filters.status"
          >
            <option value="">
              Semua status
            </option>

            <option value="active">
              Aktif
            </option>

            <option value="inactive">
              Tidak aktif
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="product-stock-status">
            Kondisi Stok
          </label>

          <select
            id="product-stock-status"
            v-model="filters.stock_status"
          >
            <option value="">
              Semua stok
            </option>

            <option value="available">
              Tersedia
            </option>

            <option value="low_stock">
              Stok menipis
            </option>

            <option value="out_of_stock">
              Stok habis
            </option>
          </select>
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
        Memuat daftar produk...
      </div>

      <div
        v-else-if="products.length === 0"
        class="state-message"
      >
        Belum ada produk yang sesuai.
      </div>

      <div v-else class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Produk</th>
              <th>Kategori</th>
              <th>Harga Jual</th>
              <th>Stok</th>
              <th>Status</th>
              <th class="action-column">
                Aksi
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="product in products"
              :key="product.id"
            >
              <td>
                <div class="product-cell">
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
                    {{ productInitial(product.name) }}
                  </div>

                  <div class="product-information">
                    <strong>{{ product.name }}</strong>

                    <span>
                      SKU: {{ product.sku }}
                    </span>

                    <small>
                      {{
                        product.barcode ||
                        'Tanpa barcode'
                      }}
                    </small>
                  </div>
                </div>
              </td>

              <td>
                {{
                  product.category?.name ??
                  'Tanpa kategori'
                }}
              </td>

              <td>
                <strong>
                  {{
                    formatCurrency(
                      product.selling_price,
                    )
                  }}
                </strong>

                <span class="unit-information">
                  per {{ product.unit }}
                </span>
              </td>

              <td>
                <strong>
                  {{ product.current_stock }}
                  {{ product.unit }}
                </strong>

                <span
                  :class="[
                    'stock-badge',
                    product.stock_status,
                  ]"
                >
                  {{
                    stockStatusLabel(
                      product.stock_status,
                    )
                  }}
                </span>

                <small class="minimum-stock">
                  Minimum:
                  {{ product.minimum_stock }}
                </small>
              </td>

              <td>
                <span
                  :class="[
                    'status-badge',
                    product.is_active
                      ? 'active'
                      : 'inactive',
                  ]"
                >
                  {{
                    product.is_active
                      ? 'Aktif'
                      : 'Tidak aktif'
                  }}
                </span>
              </td>

              <td class="action-cell">
                <button
                  type="button"
                  class="action-button"
                  :class="{
                    active:
                      openActionProductId === product.id,
                  }"
                  aria-label="Buka menu aksi produk"
                  @click.stop="
                    toggleActionMenu(product, $event)
                  "
                >
                  ⋮
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
          <strong>{{ pagination.from }}</strong>
          sampai
          <strong>{{ pagination.to }}</strong>
          dari
          <strong>{{ pagination.total }}</strong>
          produk
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
    <Teleport to="body">
  <div
    v-if="activeActionProduct"
    class="floating-action-menu"
    :style="{
      top: `${actionMenuPosition.top}px`,
      left: `${actionMenuPosition.left}px`,
    }"
    @click.stop
  >
    <button
      type="button"
      @click="
        openEditModal(activeActionProduct)
      "
    >
      Edit Produk
    </button>

    <button
      type="button"
      :class="{
        'danger-action':
          activeActionProduct.is_active,

        'success-action':
          !activeActionProduct.is_active,
      }"
      @click="
        confirmToggleStatus(
          activeActionProduct,
        )
      "
    >
      {{
        activeActionProduct.is_active
          ? 'Nonaktifkan'
          : 'Aktifkan'
      }}
    </button>
  </div>
</Teleport>
    <Teleport to="body">
      <div
        v-if="isModalOpen"
        class="modal-backdrop"
        @click.self="closeModal"
      >
        <section
          class="modal-card"
          role="dialog"
          aria-modal="true"
          aria-labelledby="product-modal-title"
        >
          <header class="modal-header">
            <div>
              <p class="eyebrow">
                Master Data
              </p>

              <h2 id="product-modal-title">
                {{ modalTitle }}
              </h2>

              <p>
                {{
                  isEditMode
                    ? 'Perbarui informasi produk.'
                    : 'Tambahkan produk baru ke StockFlow.'
                }}
              </p>
            </div>

            <button
              type="button"
              class="modal-close"
              :disabled="isSubmitting"
              aria-label="Tutup modal"
              @click="closeModal"
            >
              ×
            </button>
          </header>

          <form
            class="product-form"
            novalidate
            @submit.prevent="submitProduct"
          >
            <div
              v-if="modalErrorMessage"
              class="alert-error"
            >
              {{ modalErrorMessage }}
            </div>

            <div class="product-form-grid">
              <div class="form-group full-column">
                <label for="product-name">
                  Nama Produk
                </label>

                <input
                  id="product-name"
                  v-model="productForm.name"
                  type="text"
                  maxlength="255"
                  placeholder="Contoh: Aqua 600 ml"
                />

                <span
                  v-if="formErrors.name?.[0]"
                  class="field-error"
                >
                  {{ formErrors.name[0] }}
                </span>
              </div>

              <div class="form-group">
                <label for="product-category">
                  Kategori
                </label>

                <select
                  id="product-category"
                  v-model="
                    productForm.category_id
                  "
                >
                  <option value="" disabled>
                    Pilih kategori
                  </option>

                  <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                  >
                    {{ category.name }}
                  </option>
                </select>

                <span
                  v-if="
                    formErrors.category_id?.[0]
                  "
                  class="field-error"
                >
                  {{
                    formErrors.category_id[0]
                  }}
                </span>
              </div>

              <div class="form-group">
                <label for="product-unit">
                  Satuan
                </label>

                <select
                  id="product-unit"
                  v-model="productForm.unit"
                >
                  <option value="pcs">Pcs</option>
                  <option value="botol">
                    Botol
                  </option>
                  <option value="bungkus">
                    Bungkus
                  </option>
                  <option value="kotak">
                    Kotak
                  </option>
                  <option value="kaleng">
                    Kaleng
                  </option>
                  <option value="pack">Pack</option>
                </select>

                <span
                  v-if="formErrors.unit?.[0]"
                  class="field-error"
                >
                  {{ formErrors.unit[0] }}
                </span>
              </div>

              <div class="form-group">
                <label for="product-sku">
                  SKU
                </label>

                <input
                  id="product-sku"
                  v-model="productForm.sku"
                  type="text"
                  maxlength="100"
                  placeholder="MNM-AQUA-600"
                  class="uppercase-input"
                />

                <small class="field-information">
                  Kode internal unik produk.
                </small>

                <span
                  v-if="formErrors.sku?.[0]"
                  class="field-error"
                >
                  {{ formErrors.sku[0] }}
                </span>
              </div>

              <div class="form-group">
                <label for="product-barcode">
                  Barcode
                </label>

                <input
                  id="product-barcode"
                  v-model="productForm.barcode"
                  type="text"
                  maxlength="100"
                  placeholder="Opsional"
                />

                <small class="field-information">
                  Boleh kosong jika belum tersedia.
                </small>

                <span
                  v-if="
                    formErrors.barcode?.[0]
                  "
                  class="field-error"
                >
                  {{ formErrors.barcode[0] }}
                </span>
              </div>

              <div class="form-group">
                <label for="selling-price">
                  Harga Jual
                </label>

                <input
                  id="selling-price"
                  v-model="
                    productForm.selling_price
                  "
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  placeholder="Contoh: 4000"
                />

                <span
                  v-if="
                    formErrors.selling_price?.[0]
                  "
                  class="field-error"
                >
                  {{
                    formErrors.selling_price[0]
                  }}
                </span>
              </div>

              <div class="form-group">
                <label for="minimum-stock">
                  Stok Minimum
                </label>

                <input
                  id="minimum-stock"
                  v-model="
                    productForm.minimum_stock
                  "
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  placeholder="Contoh: 10"
                />

                <span
                  v-if="
                    formErrors.minimum_stock?.[0]
                  "
                  class="field-error"
                >
                  {{
                    formErrors.minimum_stock[0]
                  }}
                </span>
              </div>

              <div class="image-section full-column">
                <div class="image-preview">
                  <img
                    v-if="displayedImageUrl"
                    :src="displayedImageUrl"
                    alt="Preview produk"
                  />

                  <div
                    v-else
                    class="empty-image-preview"
                  >
                    {{
                      productForm.name
                        ? productInitial(
                            productForm.name,
                          )
                        : 'P'
                    }}
                  </div>
                </div>

                <div class="image-control">
                  <label for="product-image">
                    Gambar Produk
                  </label>

                  <input
                    id="product-image"
                    ref="imageInput"
                    type="file"
                    accept="
                      image/jpeg,
                      image/png,
                      image/webp
                    "
                    @change="handleImageChange"
                  />

                  <small>
                    JPG, PNG, atau WEBP. Maksimal
                    2 MB.
                  </small>

                  <button
                    v-if="productForm.image"
                    type="button"
                    class="remove-image-button"
                    @click="clearSelectedImage"
                  >
                    Batalkan gambar baru
                  </button>

                  <span
                    v-if="formErrors.image?.[0]"
                    class="field-error"
                  >
                    {{ formErrors.image[0] }}
                  </span>
                </div>
              </div>

              <label
                v-if="!isEditMode"
                class="
                  status-checkbox
                  full-column
                "
              >
                <input
                  v-model="
                    productForm.is_active
                  "
                  type="checkbox"
                />

                <span>
                  <strong>Produk aktif</strong>

                  <small>
                    Produk dapat digunakan setelah
                    stok tersedia.
                  </small>
                </span>
              </label>

              <div
                v-else
                class="
                  form-information
                  full-column
                "
              >
                Status produk diubah melalui menu
                aksi pada tabel. Stok dan harga pokok
                tidak dapat diubah dari form ini.
              </div>
            </div>

            <footer class="modal-actions">
              <button
                type="button"
                class="cancel-button"
                :disabled="isSubmitting"
                @click="closeModal"
              >
                Batal
              </button>

              <button
                type="submit"
                class="submit-button"
                :disabled="isSubmitting"
              >
                {{
                  isSubmitting
                    ? 'Menyimpan...'
                    : isEditMode
                      ? 'Simpan Perubahan'
                      : 'Simpan Produk'
                }}
              </button>
            </footer>
          </form>
        </section>
      </div>
    </Teleport>
  </section>
</template>
<style scoped>
.products-page {
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

.primary-button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.filter-card,
.table-card {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: white;
  box-shadow: 0 10px 30px rgb(15 23 42 / 4%);
}

.filter-card {
  margin-bottom: 20px;
  padding: 20px;
}

.filter-grid {
  display: grid;
  grid-template-columns:
    minmax(230px, 1fr)
    170px
    150px
    170px
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
  min-width: 950px;
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
  font-size: 14px;
  vertical-align: middle;
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.product-image,
.product-placeholder {
  width: 52px;
  height: 52px;
  flex: 0 0 auto;
  border-radius: 12px;
}

.product-image {
  object-fit: cover;
  border: 1px solid #e2e8f0;
}

.product-placeholder {
  display: grid;
  place-items: center;
  background: #d1fae5;
  color: #047857;
  font-size: 19px;
  font-weight: 800;
}

.product-information strong,
.product-information span,
.product-information small {
  display: block;
}

.product-information strong {
  color: #0f172a;
}

.product-information span {
  margin-top: 4px;
  color: #64748b;
  font-size: 12px;
}

.product-information small {
  margin-top: 2px;
  color: #94a3b8;
}

.unit-information,
.minimum-stock {
  display: block;
  margin-top: 4px;
  color: #94a3b8;
  font-size: 11px;
}

.stock-badge,
.status-badge {
  display: inline-flex;
  margin-top: 6px;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

.stock-badge.available {
  background: #dcfce7;
  color: #15803d;
}

.stock-badge.low_stock {
  background: #fef3c7;
  color: #b45309;
}

.stock-badge.out_of_stock {
  background: #fee2e2;
  color: #b91c1c;
}

.status-badge {
  margin-top: 0;
}

.status-badge.active {
  background: #dcfce7;
  color: #15803d;
}

.status-badge.inactive {
  background: #fee2e2;
  color: #b91c1c;
}

.action-column,
.action-cell {
  width: 80px;
  text-align: right;
}

.action-button {
  width: 38px;
  height: 38px;

  display: grid;
  place-items: center;

  padding: 0;
  border: 0;
  border-radius: 10px;

  background: #f1f5f9;
  color: #475569;

  font-size: 22px;
  line-height: 1;
}

.action-button:hover,
.action-button.active {
  background: #e2e8f0;
  color: #0f172a;
}

.floating-action-menu {
  position: fixed;
  z-index: 1500;

  width: 180px;
  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 11px;

  background: white;

  box-shadow:
    0 16px 40px rgb(15 23 42 / 18%);
}

.floating-action-menu button {
  width: 100%;
  padding: 12px 14px;

  border: 0;
  background: white;
  color: #334155;

  text-align: left;
  font-size: 13px;
  font-weight: 650;
}

.floating-action-menu button:hover {
  background: #f8fafc;
}

.floating-action-menu .danger-action {
  color: #dc2626;
}

.floating-action-menu .success-action {
  color: #047857;
}

.danger-action {
  color: #dc2626 !important;
}

.success-action {
  color: #047857 !important;
}

.state-message {
  padding: 60px 24px;
  color: #64748b;
  text-align: center;
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


.modal-backdrop {
  position: fixed;
  z-index: 1000;
  inset: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  padding: 22px;
  overflow-y: auto;

  background: rgb(15 23 42 / 58%);
  backdrop-filter: blur(4px);
}

.modal-card {
  width: min(100%, 760px);
  max-height: calc(100dvh - 44px);

  display: flex;
  flex-direction: column;

  overflow: hidden;

  border-radius: 20px;
  background: white;

  box-shadow:
    0 28px 80px rgb(15 23 42 / 28%);
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;

  padding: 23px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  margin: 0;
  color: #0f172a;
  font-size: 23px;
}

.modal-header p:not(.eyebrow) {
  margin: 7px 0 0;
  color: #64748b;
  font-size: 13px;
}

.modal-close {
  width: 38px;
  height: 38px;
  flex: 0 0 auto;

  border: 0;
  border-radius: 10px;

  background: #f1f5f9;
  color: #475569;

  font-size: 23px;
}

.product-form {
  padding: 23px;
  overflow-y: auto;
}

.product-form-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.full-column {
  grid-column: 1 / -1;
}

.product-form-grid .form-group {
  min-width: 0;
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

.uppercase-input {
  text-transform: uppercase;
}

.image-section {
  display: grid;
  grid-template-columns: 130px 1fr;
  gap: 18px;

  padding: 16px;

  border: 1px solid #e2e8f0;
  border-radius: 13px;
  background: #f8fafc;
}

.image-preview {
  width: 130px;
  height: 130px;

  overflow: hidden;

  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: white;
}

.image-preview img,
.empty-image-preview {
  width: 100%;
  height: 100%;
}

.image-preview img {
  object-fit: cover;
}

.empty-image-preview {
  display: grid;
  place-items: center;

  background: #d1fae5;
  color: #047857;

  font-size: 36px;
  font-weight: 800;
}

.image-control {
  min-width: 0;
  display: flex;
  align-items: flex-start;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
}

.image-control label {
  color: #334155;
  font-size: 13px;
  font-weight: 700;
}

.image-control input {
  width: 100%;
  max-width: 100%;

  padding: 9px;

  border: 1px solid #cbd5e1;
  border-radius: 10px;
  background: white;
}

.image-control small {
  color: #64748b;
  font-size: 11px;
}

.remove-image-button {
  padding: 7px 10px;

  border: 0;
  border-radius: 8px;

  background: #fee2e2;
  color: #b91c1c;

  font-size: 11px;
  font-weight: 700;
}

.status-checkbox {
  display: flex;
  align-items: center;
  gap: 11px;

  padding: 11px 13px;

  border: 1px solid #cbd5e1;
  border-radius: 10px;

  cursor: pointer;
}

.status-checkbox input {
  width: 18px;
  height: 18px;
}

.status-checkbox span,
.status-checkbox strong,
.status-checkbox small {
  display: block;
}

.status-checkbox strong {
  color: #334155;
  font-size: 13px;
}

.status-checkbox small {
  margin-top: 3px;
  color: #64748b;
  font-size: 11px;
}

.form-information {
  padding: 12px 14px;

  border-radius: 10px;
  background: #f1f5f9;
  color: #64748b;

  font-size: 12px;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;

  margin-top: 24px;
  padding-top: 19px;

  border-top: 1px solid #e2e8f0;
}

.cancel-button,
.submit-button {
  min-width: 125px;
  height: 44px;

  padding: 0 17px;
  border: 0;
  border-radius: 10px;

  font-weight: 700;
}

.cancel-button {
  background: #e2e8f0;
  color: #334155;
}

.submit-button {
  background: #047857;
  color: white;
}

.cancel-button:disabled,
.submit-button:disabled,
.modal-close:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

@media (max-width: 650px) {
  .modal-backdrop {
    align-items: flex-end;
    padding: 10px;
  }

  .modal-card {
    width: 100%;
    max-height: calc(100dvh - 20px);
    border-radius: 17px;
  }

  .modal-header,
  .product-form {
    padding: 19px;
  }

  .product-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .full-column {
    grid-column: auto;
  }

  .image-section {
    grid-template-columns: 1fr;
  }

  .image-preview {
    width: 100%;
    height: 190px;
  }

  .modal-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .cancel-button,
  .submit-button {
    width: 100%;
    min-width: 0;
  }
}

@media (max-width: 1100px) {
  .filter-grid {
    grid-template-columns: 1fr 1fr;
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