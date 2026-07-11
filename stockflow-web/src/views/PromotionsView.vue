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

import {
  createPromotion,
  getPromotions,
  togglePromotionStatus,
  updatePromotion,
} from '@/services/promotionService'

import type {
  PromotionDiscountType,
  PromotionFilters,
  PromotionItem,
  PromotionPaginationMeta,
  PromotionStatus,
  PromotionValidationErrors,
  SavePromotionPayload,
} from '@/types/promotion'

interface ApiErrorResponse {
  message?: string
  errors?: PromotionValidationErrors
}

interface PromotionFormState {
  id: number | null

  name: string
  code: string

  discount_type:
    | ''
    | PromotionDiscountType

  discount_value: string
  minimum_purchase: string
  maximum_discount: string

  starts_at: string
  ends_at: string

  is_active: boolean
}

const promotions =
  ref<PromotionItem[]>([])

const pagination =
  ref<PromotionPaginationMeta | null>(
    null,
  )

const isLoading = ref(false)
const isSaving = ref(false)

const togglingPromotionId =
  ref<number | null>(null)

const isModalOpen = ref(false)

const pageErrorMessage = ref('')
const modalErrorMessage = ref('')

const formErrors =
  ref<PromotionValidationErrors>({})

const filters =
  reactive<PromotionFilters>({
    search: '',
    discount_type: '',
    status: '',
    page: 1,
    per_page: 10,
  })

const promotionForm =
  reactive<PromotionFormState>({
    id: null,

    name: '',
    code: '',

    discount_type: '',
    discount_value: '',

    minimum_purchase: '0',
    maximum_discount: '',

    starts_at: '',
    ends_at: '',

    is_active: true,
  })

const isEditing = computed(() => {
  return promotionForm.id !== null
})

const modalTitle = computed(() => {
  return isEditing.value
    ? 'Edit Promo'
    : 'Tambah Promo'
})

const modalDescription = computed(() => {
  return isEditing.value
    ? 'Perbarui informasi dan aturan promo.'
    : 'Buat promo yang dapat digunakan pada transaksi POS.'
})

async function loadPromotions(): Promise<void> {
  isLoading.value = true
  pageErrorMessage.value = ''

  try {
    const response =
      await getPromotions(filters)

    promotions.value =
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
        pageErrorMessage.value =
          'Tidak dapat terhubung ke server Laravel.'

        return
      }

      if (error.response.status === 401) {
        pageErrorMessage.value =
          'Session login sudah tidak valid.'

        return
      }

      if (error.response.status === 403) {
        pageErrorMessage.value =
          error.response.data.message ??
          'Anda tidak memiliki akses ke pengelolaan promo.'

        return
      }

      if (error.response.status === 422) {
        pageErrorMessage.value =
          error.response.data.message ??
          'Filter promo tidak valid.'

        return
      }
    }

    pageErrorMessage.value =
      'Terjadi kesalahan saat mengambil daftar promo.'
  } finally {
    isLoading.value = false
  }
}

function resetPromotionForm(): void {
  promotionForm.id = null

  promotionForm.name = ''
  promotionForm.code = ''

  promotionForm.discount_type = ''
  promotionForm.discount_value = ''

  promotionForm.minimum_purchase = '0'
  promotionForm.maximum_discount = ''

  promotionForm.starts_at = ''
  promotionForm.ends_at = ''

  promotionForm.is_active = true

  formErrors.value = {}
  modalErrorMessage.value = ''
}

function openCreateModal(): void {
  resetPromotionForm()

  const now = new Date()

  const oneWeekLater = new Date(now)

  oneWeekLater.setDate(
    oneWeekLater.getDate() + 7,
  )

  promotionForm.starts_at =
    toDateTimeLocal(now)

  promotionForm.ends_at =
    toDateTimeLocal(oneWeekLater)

  isModalOpen.value = true
}

function openEditModal(
  promotion: PromotionItem,
): void {
  resetPromotionForm()

  promotionForm.id =
    promotion.id

  promotionForm.name =
    promotion.name

  promotionForm.code =
    promotion.code

  promotionForm.discount_type =
    promotion.discount_type

  promotionForm.discount_value =
    String(promotion.discount_value)

  promotionForm.minimum_purchase =
    String(promotion.minimum_purchase)

  promotionForm.maximum_discount =
    promotion.maximum_discount === null
      ? ''
      : String(
          promotion.maximum_discount,
        )

  promotionForm.starts_at =
    isoToDateTimeLocal(
      promotion.starts_at,
    )

  promotionForm.ends_at =
    isoToDateTimeLocal(
      promotion.ends_at,
    )

  promotionForm.is_active =
    promotion.is_active

  isModalOpen.value = true
}

function closeModal(): void {
  if (isSaving.value) {
    return
  }

  isModalOpen.value = false

  resetPromotionForm()
}

function normalizePromotionCode(): void {
  promotionForm.code =
    promotionForm.code
      .toUpperCase()
      .replace(/\s+/g, '')
}

function handleDiscountTypeChange(): void {
  clearFieldError('discount_type')
  clearFieldError('discount_value')
  clearFieldError('maximum_discount')

  if (
    promotionForm.discount_type ===
    'fixed'
  ) {
    promotionForm.maximum_discount = ''
  }
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

function validatePromotionForm(): boolean {
  const errors:
    PromotionValidationErrors = {}

  const name =
    promotionForm.name.trim()

  const code =
    promotionForm.code.trim()

  if (!name) {
    errors.name = [
      'Nama promo wajib diisi.',
    ]
  }

  if (!code) {
    errors.code = [
      'Kode promo wajib diisi.',
    ]
  } else if (
    !/^[A-Z0-9_-]+$/.test(code)
  ) {
    errors.code = [
      'Kode promo hanya boleh berisi huruf, angka, tanda hubung, dan underscore.',
    ]
  }

  if (
    promotionForm.discount_type === ''
  ) {
    errors.discount_type = [
      'Jenis diskon wajib dipilih.',
    ]
  }

  const discountValue =
    Number(
      promotionForm.discount_value,
    )

  if (
    !promotionForm.discount_value
  ) {
    errors.discount_value = [
      'Nilai diskon wajib diisi.',
    ]
  } else if (
    !Number.isFinite(discountValue) ||
    discountValue <= 0
  ) {
    errors.discount_value = [
      'Nilai diskon harus lebih dari nol.',
    ]
  } else if (
    promotionForm.discount_type ===
      'percentage' &&
    discountValue > 100
  ) {
    errors.discount_value = [
      'Diskon persentase tidak boleh melebihi 100%.',
    ]
  }

  const minimumPurchase =
    Number(
      promotionForm.minimum_purchase,
    )

  if (
    promotionForm.minimum_purchase === ''
  ) {
    errors.minimum_purchase = [
      'Minimal pembelian wajib diisi.',
    ]
  } else if (
    !Number.isFinite(minimumPurchase) ||
    minimumPurchase < 0
  ) {
    errors.minimum_purchase = [
      'Minimal pembelian tidak boleh negatif.',
    ]
  }

  if (
    promotionForm.discount_type ===
      'percentage' &&
    promotionForm.maximum_discount !== ''
  ) {
    const maximumDiscount =
      Number(
        promotionForm.maximum_discount,
      )

    if (
      !Number.isFinite(maximumDiscount) ||
      maximumDiscount <= 0
    ) {
      errors.maximum_discount = [
        'Maksimal diskon harus lebih dari nol.',
      ]
    }
  }

  if (!promotionForm.starts_at) {
    errors.starts_at = [
      'Waktu mulai promo wajib diisi.',
    ]
  }

  if (!promotionForm.ends_at) {
    errors.ends_at = [
      'Waktu berakhir promo wajib diisi.',
    ]
  } else if (
    promotionForm.starts_at &&
    promotionForm.ends_at <=
      promotionForm.starts_at
  ) {
    errors.ends_at = [
      'Waktu berakhir harus setelah waktu mulai.',
    ]
  }

  formErrors.value = errors

  return (
    Object.keys(errors).length === 0
  )
}

function buildPayload():
  SavePromotionPayload {
  return {
    name:
      promotionForm.name.trim(),

    code:
      promotionForm.code
        .trim()
        .toUpperCase(),

    discount_type:
      promotionForm.discount_type as
        PromotionDiscountType,

    discount_value:
      Number(
        promotionForm.discount_value,
      ),

    minimum_purchase:
      Number(
        promotionForm.minimum_purchase,
      ),

    maximum_discount:
      promotionForm.discount_type ===
        'percentage' &&
      promotionForm.maximum_discount !== ''
        ? Number(
            promotionForm.maximum_discount,
          )
        : null,

    starts_at:
      promotionForm.starts_at,

    ends_at:
      promotionForm.ends_at,

    is_active:
      promotionForm.is_active,
  }
}

async function savePromotion(): Promise<void> {
  if (!validatePromotionForm()) {
    modalErrorMessage.value =
      'Periksa kembali data promo yang ditandai.'

    return
  }

  isSaving.value = true
  modalErrorMessage.value = ''

  try {
    const payload =
      buildPayload()

    const response =
      promotionForm.id === null
        ? await createPromotion(
            payload,
          )
        : await updatePromotion(
            promotionForm.id,
            payload,
          )

    toast.success(response.message, {
      description:
        response.promotion.code,
    })

    isModalOpen.value = false

    resetPromotionForm()

    await loadPromotions()
  } catch (error: unknown) {
    if (
      !axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      modalErrorMessage.value =
        'Terjadi kesalahan yang tidak dikenali.'

      return
    }

    if (!error.response) {
      modalErrorMessage.value =
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

      modalErrorMessage.value =
        'Periksa kembali data promo yang ditandai.'

      return
    }

    if (status === 401) {
      modalErrorMessage.value =
        'Session login sudah tidak valid.'

      return
    }

    if (status === 403) {
      modalErrorMessage.value =
        data.message ??
        'Anda tidak memiliki akses untuk menyimpan promo.'

      return
    }

    modalErrorMessage.value =
      data.message ??
      'Promo gagal disimpan.'
  } finally {
    isSaving.value = false
  }
}

async function confirmToggleStatus(
  promotion: PromotionItem,
): Promise<void> {
  const nextStatus =
    !promotion.is_active

  const confirmation =
    await Swal.fire({
      title: nextStatus
        ? 'Aktifkan promo?'
        : 'Nonaktifkan promo?',

      text: nextStatus
        ? `${promotion.name} akan diizinkan digunakan sesuai periode promonya.`
        : `${promotion.name} tidak dapat digunakan pada transaksi POS.`,

      icon: nextStatus
        ? 'question'
        : 'warning',

      showCancelButton: true,

      confirmButtonText: nextStatus
        ? 'Ya, aktifkan'
        : 'Ya, nonaktifkan',

      cancelButtonText: 'Batal',

      reverseButtons: true,
    })

  if (!confirmation.isConfirmed) {
    return
  }

  togglingPromotionId.value =
    promotion.id

  pageErrorMessage.value = ''

  try {
    const response =
      await togglePromotionStatus(
        promotion.id,
      )

    toast.success(response.message, {
      description:
        response.promotion.code,
    })

    await loadPromotions()
  } catch (error: unknown) {
    if (
      axios.isAxiosError<ApiErrorResponse>(
        error,
      )
    ) {
      pageErrorMessage.value =
        error.response?.data.message ??
        'Status promo gagal diperbarui.'
    } else {
      pageErrorMessage.value =
        'Status promo gagal diperbarui.'
    }
  } finally {
    togglingPromotionId.value =
      null
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1

  await loadPromotions()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.discount_type = ''
  filters.status = ''
  filters.page = 1

  await loadPromotions()
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

  await loadPromotions()
}

function discountTypeLabel(
  type: PromotionDiscountType,
): string {
  return type === 'percentage'
    ? 'Persentase'
    : 'Nominal'
}

function promotionStatusLabel(
  status: PromotionStatus,
): string {
  const labels: Record<
    PromotionStatus,
    string
  > = {
    active: 'Aktif',
    upcoming: 'Akan Datang',
    expired: 'Berakhir',
    inactive: 'Nonaktif',
  }

  return labels[status]
}

function formatDiscountValue(
  promotion: PromotionItem,
): string {
  if (
    promotion.discount_type ===
    'percentage'
  ) {
    return `${formatNumber(
      promotion.discount_value,
    )}%`
  }

  return formatCurrency(
    promotion.discount_value,
  )
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

function formatNumber(
  value: number,
): string {
  return new Intl.NumberFormat(
    'id-ID',
    {
      maximumFractionDigits: 2,
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

function toDateTimeLocal(
  date: Date,
): string {
  const year =
    date.getFullYear()

  const month = String(
    date.getMonth() + 1,
  ).padStart(2, '0')

  const day = String(
    date.getDate(),
  ).padStart(2, '0')

  const hour = String(
    date.getHours(),
  ).padStart(2, '0')

  const minute = String(
    date.getMinutes(),
  ).padStart(2, '0')

  return (
    `${year}-${month}-${day}` +
    `T${hour}:${minute}`
  )
}

function isoToDateTimeLocal(
  value: string,
): string {
  return toDateTimeLocal(
    new Date(value),
  )
}

function handleEscapeKey(
  event: KeyboardEvent,
): void {
  if (
    event.key === 'Escape' &&
    isModalOpen.value &&
    !isSaving.value
  ) {
    closeModal()
  }
}

watch(isModalOpen, (isOpen) => {
  document.body.style.overflow =
    isOpen ? 'hidden' : ''
})

onMounted(() => {
  window.addEventListener(
    'keydown',
    handleEscapeKey,
  )

  loadPromotions()
})

onBeforeUnmount(() => {
  window.removeEventListener(
    'keydown',
    handleEscapeKey,
  )

  document.body.style.overflow = ''
})
</script>

<template>
  <section class="promotions-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Sales
        </p>

        <h2>Promo & Diskon</h2>

        <p class="subtitle">
          Atur promo transaksi yang dapat
          dipilih kasir saat menjalankan POS.
        </p>
      </div>

      <button
        type="button"
        class="primary-button"
        @click="openCreateModal"
      >
        + Tambah Promo
      </button>
    </header>

    <section class="filter-card">
      <form
        class="filter-grid"
        @submit.prevent="applyFilters"
      >
        <div class="form-group search-field">
          <label for="promotion-search">
            Pencarian
          </label>

          <input
            id="promotion-search"
            v-model="filters.search"
            type="search"
            placeholder="Cari nama atau kode promo..."
          />
        </div>

        <div class="form-group">
          <label for="promotion-type">
            Jenis Diskon
          </label>

          <select
            id="promotion-type"
            v-model="
              filters.discount_type
            "
          >
            <option value="">
              Semua jenis
            </option>

            <option value="percentage">
              Persentase
            </option>

            <option value="fixed">
              Nominal tetap
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="promotion-status">
            Status
          </label>

          <select
            id="promotion-status"
            v-model="filters.status"
          >
            <option value="">
              Semua status
            </option>

            <option value="active">
              Aktif
            </option>

            <option value="upcoming">
              Akan datang
            </option>

            <option value="expired">
              Berakhir
            </option>

            <option value="inactive">
              Nonaktif
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
      v-if="pageErrorMessage"
      class="alert-error"
    >
      {{ pageErrorMessage }}
    </div>

    <section class="table-card">
      <div
        v-if="isLoading"
        class="state-message"
      >
        Memuat daftar promo...
      </div>

      <div
        v-else-if="
          promotions.length === 0
        "
        class="empty-state"
      >
        <div class="empty-icon">
          %
        </div>

        <h3>
          Belum ada promo
        </h3>

        <p>
          Tambahkan promo untuk memberikan
          potongan harga pada transaksi POS.
        </p>

        <button
          type="button"
          class="primary-button"
          @click="openCreateModal"
        >
          Tambah Promo
        </button>
      </div>

      <div
        v-else
        class="table-wrapper"
      >
        <table>
          <thead>
            <tr>
              <th>Promo</th>
              <th>Jenis</th>
              <th>Nilai Diskon</th>
              <th>Minimal Belanja</th>
              <th>Maksimal Diskon</th>
              <th>Periode</th>
              <th>Status</th>
              <th>Dibuat Oleh</th>
              <th class="action-column">
                Aksi
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="
                promotion in promotions
              "
              :key="promotion.id"
            >
              <td>
                <div class="promotion-information">
                  <strong>
                    {{ promotion.name }}
                  </strong>

                  <span>
                    {{ promotion.code }}
                  </span>
                </div>
              </td>

              <td>
                <span class="type-badge">
                  {{
                    discountTypeLabel(
                      promotion.discount_type,
                    )
                  }}
                </span>
              </td>

              <td>
                <strong class="discount-value">
                  {{
                    formatDiscountValue(
                      promotion,
                    )
                  }}
                </strong>
              </td>

              <td>
                {{
                  formatCurrency(
                    promotion.minimum_purchase,
                  )
                }}
              </td>

              <td>
                {{
                  promotion.maximum_discount ===
                  null
                    ? '-'
                    : formatCurrency(
                        promotion.maximum_discount,
                      )
                }}
              </td>

              <td>
                <div class="period-information">
                  <span>
                    {{
                      formatDateTime(
                        promotion.starts_at,
                      )
                    }}
                  </span>

                  <small>
                    sampai
                  </small>

                  <span>
                    {{
                      formatDateTime(
                        promotion.ends_at,
                      )
                    }}
                  </span>
                </div>
              </td>

              <td>
                <span
                  :class="[
                    'status-badge',
                    promotion.status,
                  ]"
                >
                  {{
                    promotionStatusLabel(
                      promotion.status,
                    )
                  }}
                </span>
              </td>

              <td>
                {{
                  promotion.creator.name
                }}
              </td>

              <td class="action-cell">
                <div class="action-buttons">
                  <button
                    type="button"
                    class="edit-button"
                    @click="
                      openEditModal(
                        promotion,
                      )
                    "
                  >
                    Edit
                  </button>

                  <button
                    type="button"
                    :class="[
                      'toggle-button',
                      promotion.is_active
                        ? 'deactivate'
                        : 'activate',
                    ]"
                    :disabled="
                      togglingPromotionId ===
                      promotion.id
                    "
                    @click="
                      confirmToggleStatus(
                        promotion,
                      )
                    "
                  >
                    {{
                      togglingPromotionId ===
                      promotion.id
                        ? 'Memproses...'
                        : promotion.is_active
                          ? 'Nonaktifkan'
                          : 'Aktifkan'
                    }}
                  </button>
                </div>
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
          promo
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
        v-if="isModalOpen"
        class="modal-overlay"
        @click.self="closeModal"
      >
        <section
          class="modal-card"
          role="dialog"
          aria-modal="true"
          :aria-label="modalTitle"
        >
          <header class="modal-header">
            <div>
              <h3>
                {{ modalTitle }}
              </h3>

              <p>
                {{ modalDescription }}
              </p>
            </div>

            <button
              type="button"
              class="modal-close"
              :disabled="isSaving"
              aria-label="Tutup modal"
              @click="closeModal"
            >
              ×
            </button>
          </header>

          <form
            novalidate
            @submit.prevent="savePromotion"
          >
            <div class="modal-body">
              <div
                v-if="modalErrorMessage"
                class="alert-error"
              >
                {{ modalErrorMessage }}
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label for="promotion-name">
                    Nama Promo
                  </label>

                  <input
                    id="promotion-name"
                    v-model="
                      promotionForm.name
                    "
                    type="text"
                    maxlength="100"
                    placeholder="Contoh: Promo Kemerdekaan"
                    @input="
                      clearFieldError(
                        'name',
                      )
                    "
                  />

                  <span
                    v-if="
                      firstError('name')
                    "
                    class="field-error"
                  >
                    {{ firstError('name') }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="promotion-code">
                    Kode Promo
                  </label>

                  <input
                    id="promotion-code"
                    v-model="
                      promotionForm.code
                    "
                    type="text"
                    maxlength="50"
                    placeholder="Contoh: MERDEKA17"
                    @input="
                      normalizePromotionCode();
                      clearFieldError(
                        'code',
                      )
                    "
                  />

                  <span
                    v-if="
                      firstError('code')
                    "
                    class="field-error"
                  >
                    {{ firstError('code') }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="discount-type">
                    Jenis Diskon
                  </label>

                  <select
                    id="discount-type"
                    v-model="
                      promotionForm.discount_type
                    "
                    @change="
                      handleDiscountTypeChange
                    "
                  >
                    <option
                      value=""
                      disabled
                    >
                      Pilih jenis diskon
                    </option>

                    <option value="percentage">
                      Persentase
                    </option>

                    <option value="fixed">
                      Nominal tetap
                    </option>
                  </select>

                  <span
                    v-if="
                      firstError(
                        'discount_type',
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        'discount_type',
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="discount-value">
                    {{
                      promotionForm.discount_type ===
                      'percentage'
                        ? 'Persentase Diskon'
                        : 'Nilai Diskon'
                    }}
                  </label>

                  <div class="input-addon-wrapper">
                    <span
                      v-if="
                        promotionForm.discount_type ===
                        'fixed'
                      "
                      class="input-addon prefix"
                    >
                      Rp
                    </span>

                    <input
                      id="discount-value"
                      v-model="
                        promotionForm.discount_value
                      "
                      type="number"
                      min="0.01"
                      step="0.01"
                      :max="
                        promotionForm.discount_type ===
                        'percentage'
                          ? 100
                          : undefined
                      "
                      placeholder="0"
                      :class="{
                        'with-prefix':
                          promotionForm.discount_type ===
                          'fixed',
                        'with-suffix':
                          promotionForm.discount_type ===
                          'percentage',
                      }"
                      @input="
                        clearFieldError(
                          'discount_value',
                        )
                      "
                    />

                    <span
                      v-if="
                        promotionForm.discount_type ===
                        'percentage'
                      "
                      class="input-addon suffix"
                    >
                      %
                    </span>
                  </div>

                  <span
                    v-if="
                      firstError(
                        'discount_value',
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        'discount_value',
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="minimum-purchase">
                    Minimal Pembelian
                  </label>

                  <div class="input-addon-wrapper">
                    <span class="input-addon prefix">
                      Rp
                    </span>

                    <input
                      id="minimum-purchase"
                      v-model="
                        promotionForm.minimum_purchase
                      "
                      type="number"
                      min="0"
                      step="0.01"
                      class="with-prefix"
                      placeholder="0"
                      @input="
                        clearFieldError(
                          'minimum_purchase',
                        )
                      "
                    />
                  </div>

                  <span
                    v-if="
                      firstError(
                        'minimum_purchase',
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        'minimum_purchase',
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="maximum-discount">
                    Maksimal Diskon
                  </label>

                  <div class="input-addon-wrapper">
                    <span class="input-addon prefix">
                      Rp
                    </span>

                    <input
                      id="maximum-discount"
                      v-model="
                        promotionForm.maximum_discount
                      "
                      type="number"
                      min="0.01"
                      step="0.01"
                      class="with-prefix"
                      placeholder="Opsional"
                      :disabled="
                        promotionForm.discount_type !==
                        'percentage'
                      "
                      @input="
                        clearFieldError(
                          'maximum_discount',
                        )
                      "
                    />
                  </div>

                  <small class="field-information">
                    Hanya berlaku untuk diskon
                    persentase.
                  </small>

                  <span
                    v-if="
                      firstError(
                        'maximum_discount',
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        'maximum_discount',
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="promotion-start">
                    Mulai Berlaku
                  </label>

                  <input
                    id="promotion-start"
                    v-model="
                      promotionForm.starts_at
                    "
                    type="datetime-local"
                    @input="
                      clearFieldError(
                        'starts_at',
                      )
                    "
                  />

                  <span
                    v-if="
                      firstError(
                        'starts_at',
                      )
                    "
                    class="field-error"
                  >
                    {{
                      firstError(
                        'starts_at',
                      )
                    }}
                  </span>
                </div>

                <div class="form-group">
                  <label for="promotion-end">
                    Berakhir
                  </label>

                  <input
                    id="promotion-end"
                    v-model="
                      promotionForm.ends_at
                    "
                    type="datetime-local"
                    :min="
                      promotionForm.starts_at ||
                      undefined
                    "
                    @input="
                      clearFieldError(
                        'ends_at',
                      )
                    "
                  />

                  <span
                    v-if="
                      firstError('ends_at')
                    "
                    class="field-error"
                  >
                    {{
                      firstError('ends_at')
                    }}
                  </span>
                </div>

                <div class="form-group full-column">
                  <label class="switch-row">
                    <span>
                      <strong>
                        Aktifkan Promo
                      </strong>

                      <small>
                        Promo tetap mengikuti
                        periode mulai dan berakhir.
                      </small>
                    </span>

                    <input
                      v-model="
                        promotionForm.is_active
                      "
                      type="checkbox"
                      class="switch-input"
                    />

                    <span class="switch-visual" />
                  </label>
                </div>
              </div>

              <section class="promotion-preview">
                <h4>
                  Ringkasan Promo
                </h4>

                <div class="preview-grid">
                  <div>
                    <span>Kode</span>

                    <strong>
                      {{
                        promotionForm.code ||
                        '-'
                      }}
                    </strong>
                  </div>

                  <div>
                    <span>Diskon</span>

                    <strong>
                      {{
                        promotionForm.discount_type ===
                          'percentage' &&
                        promotionForm.discount_value
                          ? `${promotionForm.discount_value}%`
                          : promotionForm.discount_type ===
                              'fixed' &&
                            promotionForm.discount_value
                            ? formatCurrency(
                                Number(
                                  promotionForm.discount_value,
                                ),
                              )
                            : '-'
                      }}
                    </strong>
                  </div>

                  <div>
                    <span>Minimal Belanja</span>

                    <strong>
                      {{
                        formatCurrency(
                          Number(
                            promotionForm.minimum_purchase,
                          ) || 0,
                        )
                      }}
                    </strong>
                  </div>

                  <div>
                    <span>Status Manual</span>

                    <strong>
                      {{
                        promotionForm.is_active
                          ? 'Aktif'
                          : 'Nonaktif'
                      }}
                    </strong>
                  </div>
                </div>
              </section>
            </div>

            <footer class="modal-footer">
              <button
                type="button"
                class="cancel-button"
                :disabled="isSaving"
                @click="closeModal"
              >
                Batal
              </button>

              <button
                type="submit"
                class="save-button"
                :disabled="isSaving"
              >
                {{
                  isSaving
                    ? 'Menyimpan...'
                    : isEditing
                      ? 'Simpan Perubahan'
                      : 'Tambah Promo'
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
.promotions-page {
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
  line-height: 1.6;
}

.primary-button,
.filter-button,
.reset-button,
.edit-button,
.toggle-button,
.pagination button,
.cancel-button,
.save-button {
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
    minmax(260px, 1fr)
    180px
    180px
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
  font: inherit;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.form-group input:disabled {
  cursor: not-allowed;
  background: #f1f5f9;
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

.promotion-information strong,
.promotion-information span {
  display: block;
}

.promotion-information strong {
  color: #0f172a;
}

.promotion-information span {
  margin-top: 5px;
  color: #047857;
  font-size: 11px;
  font-weight: 750;
}

.type-badge,
.status-badge {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 750;
  white-space: nowrap;
}

.type-badge {
  background: #e0e7ff;
  color: #4338ca;
}

.status-badge.active {
  background: #dcfce7;
  color: #15803d;
}

.status-badge.upcoming {
  background: #dbeafe;
  color: #1d4ed8;
}

.status-badge.expired {
  background: #f1f5f9;
  color: #64748b;
}

.status-badge.inactive {
  background: #fee2e2;
  color: #b91c1c;
}

.discount-value {
  color: #047857;
  white-space: nowrap;
}

.period-information span,
.period-information small {
  display: block;
  white-space: nowrap;
}

.period-information small {
  margin: 3px 0;
  color: #94a3b8;
}

.action-column,
.action-cell {
  text-align: right;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 7px;
}

.edit-button,
.toggle-button {
  padding: 8px 11px;
  white-space: nowrap;
}

.edit-button {
  background: #e2e8f0;
  color: #334155;
}

.toggle-button.activate {
  background: #dcfce7;
  color: #15803d;
}

.toggle-button.deactivate {
  background: #fee2e2;
  color: #b91c1c;
}

.toggle-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
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
  font-size: 25px;
  font-weight: 800;
}

.empty-state h3 {
  margin: 17px 0 7px;
  color: #0f172a;
}

.empty-state p {
  max-width: 450px;
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

.field-error {
  color: #dc2626;
  font-size: 12px;
  line-height: 1.4;
}

.field-information {
  color: #94a3b8;
  font-size: 11px;
}

.full-column {
  grid-column: 1 / -1;
}

.input-addon-wrapper {
  position: relative;
}

.input-addon {
  position: absolute;
  top: 50%;
  z-index: 1;
  transform: translateY(-50%);
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.input-addon.prefix {
  left: 12px;
}

.input-addon.suffix {
  right: 12px;
}

.form-group input.with-prefix {
  padding-left: 38px;
}

.form-group input.with-suffix {
  padding-right: 38px;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 22px;
  overflow-y: auto;
  background: rgb(15 23 42 / 58%);
  backdrop-filter: blur(3px);
}

.modal-card {
  width: min(850px, 100%);
  max-height: calc(100vh - 44px);
  overflow-y: auto;
  border-radius: 18px;
  background: white;
  box-shadow:
    0 30px 80px rgb(15 23 42 / 28%);
}

.modal-header {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding: 21px 23px;
  border-bottom: 1px solid #e2e8f0;
  background: white;
}

.modal-header h3 {
  margin: 0;
  color: #0f172a;
  font-size: 20px;
}

.modal-header p {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 13px;
}

.modal-close {
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  border: 0;
  border-radius: 10px;
  background: #f1f5f9;
  color: #475569;
  font-size: 22px;
}

.modal-body {
  padding: 22px 23px;
}

.form-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 17px;
}

.switch-row {
  position: relative;
  display: flex !important;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 11px;
  background: #f8fafc;
}

.switch-row strong,
.switch-row small {
  display: block;
}

.switch-row strong {
  color: #0f172a;
}

.switch-row small {
  margin-top: 4px;
  color: #64748b;
  font-weight: 400;
}

.switch-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.switch-visual {
  position: relative;
  width: 46px;
  height: 26px;
  flex: 0 0 auto;
  border-radius: 999px;
  background: #cbd5e1;
  transition: 0.2s;
}

.switch-visual::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: white;
  box-shadow:
    0 2px 5px rgb(15 23 42 / 22%);
  transition: 0.2s;
}

.switch-input:checked +
.switch-visual {
  background: #059669;
}

.switch-input:checked +
.switch-visual::after {
  transform: translateX(20px);
}

.promotion-preview {
  margin-top: 20px;
  padding: 17px;
  border-radius: 13px;
  background: #ecfdf5;
}

.promotion-preview h4 {
  margin: 0 0 13px;
  color: #065f46;
  font-size: 14px;
}

.preview-grid {
  display: grid;
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.preview-grid div {
  min-width: 0;
  padding: 11px;
  border-radius: 9px;
  background: white;
}

.preview-grid span,
.preview-grid strong {
  display: block;
}

.preview-grid span {
  color: #64748b;
  font-size: 10px;
}

.preview-grid strong {
  margin-top: 5px;
  overflow-wrap: anywhere;
  color: #0f172a;
  font-size: 12px;
}

.modal-footer {
  position: sticky;
  bottom: 0;
  z-index: 2;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 17px 23px;
  border-top: 1px solid #e2e8f0;
  background: white;
}

.cancel-button,
.save-button {
  min-width: 130px;
  height: 43px;
  padding: 0 16px;
}

.cancel-button {
  background: #e2e8f0;
  color: #334155;
}

.save-button {
  background: #047857;
  color: white;
}

.cancel-button:disabled,
.save-button:disabled,
.modal-close:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

@media (max-width: 900px) {
  .filter-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }

  .search-field {
    grid-column: 1 / -1;
  }

  .preview-grid {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 650px) {
  .page-header {
    flex-direction: column;
  }

  .page-header .primary-button {
    width: 100%;
  }

  .filter-grid,
  .form-grid,
  .preview-grid {
    grid-template-columns: 1fr;
  }

  .search-field,
  .full-column {
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

  .modal-overlay {
    padding: 0;
    place-items: stretch;
  }

  .modal-card {
    width: 100%;
    max-height: 100vh;
    border-radius: 0;
  }

  .modal-footer {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .cancel-button,
  .save-button {
    min-width: 0;
  }
}
</style>