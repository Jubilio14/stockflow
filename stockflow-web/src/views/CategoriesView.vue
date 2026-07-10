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
  createCategory,
  getCategories,
  toggleCategoryStatus,
  updateCategory,
} from '@/services/categoryService'

import type {
  CategoryItem,
  CategoryPaginationMeta,
  CategoryValidationErrors,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from '@/types/category'

interface ApiErrorResponse {
  message?: string
  errors?: CategoryValidationErrors
}

const categories = ref<CategoryItem[]>([])
const pagination =
  ref<CategoryPaginationMeta | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const filters = reactive({
  search: '',
  status: '' as '' | 'active' | 'inactive',
  page: 1,
  per_page: 10,
})

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const editingCategoryId = ref<number | null>(null)

const modalErrorMessage = ref('')
const formErrors =
  ref<CategoryValidationErrors>({})

const categoryForm =
  reactive<CreateCategoryPayload>({
    name: '',
    description: '',
    is_active: true,
  })

const isEditMode = computed(() => {
  return editingCategoryId.value !== null
})

const modalTitle = computed(() => {
  return isEditMode.value
    ? 'Edit Kategori'
    : 'Tambah Kategori'
})

async function loadCategories(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getCategories(filters)

    categories.value = response.data
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
          'Anda tidak memiliki izin untuk mengakses kategori.'
        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil kategori.'
  } finally {
    isLoading.value = false
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1
  await loadCategories()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.status = ''
  filters.page = 1

  await loadCategories()
}

async function changePage(page: number): Promise<void> {
  if (
    page < 1 ||
    page > (pagination.value?.last_page ?? 1) ||
    page === filters.page
  ) {
    return
  }

  filters.page = page
  await loadCategories()
}

function resetCategoryForm(): void {
  categoryForm.name = ''
  categoryForm.description = ''
  categoryForm.is_active = true

  editingCategoryId.value = null
  formErrors.value = {}
  modalErrorMessage.value = ''
}

function openCreateModal(): void {
  resetCategoryForm()
  isModalOpen.value = true
}

function openEditModal(
  category: CategoryItem,
): void {
  editingCategoryId.value = category.id

  categoryForm.name = category.name
  categoryForm.description =
    category.description ?? ''
  categoryForm.is_active = category.is_active

  formErrors.value = {}
  modalErrorMessage.value = ''
  isModalOpen.value = true
}

function closeModal(): void {
  if (isSubmitting.value) {
    return
  }

  isModalOpen.value = false
  resetCategoryForm()
}

function validateForm(): boolean {
  const errors: CategoryValidationErrors = {}

  if (!categoryForm.name.trim()) {
    errors.name = [
      'Nama kategori wajib diisi.',
    ]
  }

  if (categoryForm.name.trim().length > 255) {
    errors.name = [
      'Nama kategori maksimal 255 karakter.',
    ]
  }

  if (categoryForm.description.length > 1000) {
    errors.description = [
      'Deskripsi maksimal 1000 karakter.',
    ]
  }

  formErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitCategory(): Promise<void> {
  if (!validateForm()) {
    return
  }

  isSubmitting.value = true
  modalErrorMessage.value = ''

  try {
    let response

    if (
      isEditMode.value &&
      editingCategoryId.value !== null
    ) {
      const payload: UpdateCategoryPayload = {
        name: categoryForm.name.trim(),
        description:
          categoryForm.description.trim(),
      }

      response = await updateCategory(
        editingCategoryId.value,
        payload,
      )
    } else {
      const payload: CreateCategoryPayload = {
        name: categoryForm.name.trim(),
        description:
          categoryForm.description.trim(),
        is_active: categoryForm.is_active,
      }

      response = await createCategory(payload)
    }

    isModalOpen.value = false
    resetCategoryForm()

    toast.success(response.message)

    await loadCategories()
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
          'Data kategori tidak valid.'
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
        'Kategori tidak dapat disimpan.'
      return
    }

    modalErrorMessage.value =
      data.message ??
      'Terjadi kesalahan saat menyimpan kategori.'
  } finally {
    isSubmitting.value = false
  }
}

async function confirmToggleStatus(
  category: CategoryItem,
): Promise<void> {
  const willActivate = !category.is_active

  const result = await Swal.fire({
    title: willActivate
      ? 'Aktifkan kategori?'
      : 'Nonaktifkan kategori?',

    text: willActivate
      ? `${category.name} dapat digunakan kembali pada produk.`
      : `${category.name} tidak dapat digunakan untuk produk baru.`,

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
      await toggleCategoryStatus(category.id)

    Swal.close()

    toast.success(response.message)

    await loadCategories()
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
      'Status kategori gagal diperbarui.',
    )
  }
}

function formatDate(
  date: string | null,
): string {
  if (!date) {
    return '-'
  }

  return new Date(date).toLocaleDateString(
    'id-ID',
    {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    },
  )
}

function handleEscape(
  event: KeyboardEvent,
): void {
  if (
    event.key === 'Escape' &&
    isModalOpen.value
  ) {
    closeModal()
  }
}

watch(isModalOpen, (isOpen) => {
  document.body.style.overflow =
    isOpen ? 'hidden' : ''
})

onMounted(() => {
  loadCategories()

  window.addEventListener(
    'keydown',
    handleEscape,
  )
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''

  window.removeEventListener(
    'keydown',
    handleEscape,
  )
})
</script>

<template>
  <section class="categories-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">Master Data</p>
        <h2>Kategori Produk</h2>
        <p class="subtitle">
          Kelola kategori yang digunakan untuk
          mengelompokkan produk StockFlow.
        </p>
      </div>

      <button
        type="button"
        class="primary-button"
        @click="openCreateModal"
      >
        Tambah Kategori
      </button>
    </header>

    <section class="filter-card">
      <form
        class="filter-grid"
        @submit.prevent="applyFilters"
      >
        <div class="form-group">
          <label for="category-search">
            Pencarian
          </label>

          <input
            id="category-search"
            v-model="filters.search"
            type="search"
            placeholder="Cari nama atau deskripsi..."
          />
        </div>

        <div class="form-group">
          <label for="category-status">
            Status
          </label>

          <select
            id="category-status"
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
        Memuat daftar kategori...
      </div>

      <div
        v-else-if="categories.length === 0"
        class="state-message"
      >
        Belum ada kategori yang sesuai.
      </div>

      <div v-else class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Kategori</th>
              <th>Deskripsi</th>
              <th>Status</th>
              <th>Dibuat</th>
              <th class="action-column">
                Aksi
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="category in categories"
              :key="category.id"
            >
              <td>
                <strong class="category-name">
                  {{ category.name }}
                </strong>
              </td>

              <td class="description-cell">
                {{
                  category.description ||
                  'Tidak ada deskripsi'
                }}
              </td>

              <td>
                <span
                  :class="[
                    'status-badge',
                    category.is_active
                      ? 'active'
                      : 'inactive',
                  ]"
                >
                  {{
                    category.is_active
                      ? 'Aktif'
                      : 'Tidak aktif'
                  }}
                </span>
              </td>

              <td>
                {{
                  formatDate(
                    category.created_at,
                  )
                }}
              </td>

              <td class="action-cell">
                <details class="action-menu">
                  <summary
                    aria-label="Buka menu aksi"
                  >
                    ⋮
                  </summary>

                  <div class="action-dropdown">
                    <button
                      type="button"
                      @click="
                        openEditModal(category)
                      "
                    >
                      Edit Kategori
                    </button>

                    <button
                      type="button"
                      :class="{
                        'danger-action':
                          category.is_active,
                        'success-action':
                          !category.is_active,
                      }"
                      @click="
                        confirmToggleStatus(
                          category,
                        )
                      "
                    >
                      {{
                        category.is_active
                          ? 'Nonaktifkan'
                          : 'Aktifkan'
                      }}
                    </button>
                  </div>
                </details>
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
          kategori
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
        class="modal-backdrop"
        @click.self="closeModal"
      >
        <section
          class="modal-card"
          role="dialog"
          aria-modal="true"
          aria-labelledby="category-modal-title"
        >
          <header class="modal-header">
            <div>
              <p class="eyebrow">
                Master Data
              </p>

              <h2 id="category-modal-title">
                {{ modalTitle }}
              </h2>

              <p>
                {{
                  isEditMode
                    ? 'Perbarui informasi kategori.'
                    : 'Tambahkan kelompok produk baru.'
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
            class="category-form"
            novalidate
            @submit.prevent="submitCategory"
          >
            <div
              v-if="modalErrorMessage"
              class="alert-error"
            >
              {{ modalErrorMessage }}
            </div>

            <div class="form-group">
              <label for="category-name">
                Nama Kategori
              </label>

              <input
                id="category-name"
                v-model="categoryForm.name"
                type="text"
                maxlength="255"
                placeholder="Contoh: Minuman"
              />

              <span
                v-if="formErrors.name?.[0]"
                class="field-error"
              >
                {{ formErrors.name[0] }}
              </span>
            </div>

            <div class="form-group">
              <label for="category-description">
                Deskripsi
              </label>

              <textarea
                id="category-description"
                v-model="
                  categoryForm.description
                "
                maxlength="1000"
                rows="4"
                placeholder="Penjelasan singkat kategori..."
              ></textarea>

              <div class="textarea-information">
                <span
                  v-if="
                    formErrors.description?.[0]
                  "
                  class="field-error"
                >
                  {{
                    formErrors.description[0]
                  }}
                </span>

                <small>
                  {{
                    categoryForm.description
                      .length
                  }}/1000
                </small>
              </div>
            </div>

            <label
              v-if="!isEditMode"
              class="status-checkbox"
            >
              <input
                v-model="
                  categoryForm.is_active
                "
                type="checkbox"
              />

              <span>
                <strong>Kategori aktif</strong>
                <small>
                  Kategori dapat langsung
                  digunakan pada produk.
                </small>
              </span>
            </label>

            <div
              v-else
              class="form-information"
            >
              Status kategori diubah melalui
              menu aksi pada tabel.
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
                      : 'Simpan Kategori'
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
.categories-page {
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
.pagination button,
.cancel-button,
.submit-button {
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
  box-shadow: 0 10px 30px rgb(15 23 42 / 4%);
}

.filter-card {
  margin-bottom: 20px;
  padding: 20px;
}

.filter-grid {
  display: grid;
  grid-template-columns:
    minmax(240px, 1fr)
    190px
    auto;
  gap: 14px;
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
.form-group select,
.form-group textarea {
  width: 100%;
  max-width: 100%;
  padding: 0 13px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  outline: none;
  background: white;
  color: #0f172a;
}

.form-group input,
.form-group select {
  height: 44px;
}

.form-group textarea {
  padding-top: 12px;
  padding-bottom: 12px;
  resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
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
  padding: 0 16px;
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
  font-size: 13px;
}

.table-card {
  overflow: visible;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 760px;
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
}

.category-name {
  color: #0f172a;
}

.description-cell {
  max-width: 380px;
  color: #64748b;
  line-height: 1.5;
}

.status-badge {
  display: inline-flex;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.status-badge.active {
  background: #dcfce7;
  color: #15803d;
}

.status-badge.inactive {
  background: #fee2e2;
  color: #b91c1c;
}

.state-message {
  padding: 60px 24px;
  color: #64748b;
  text-align: center;
}

.action-column,
.action-cell {
  width: 90px;
  text-align: right;
}

.action-menu {
  position: relative;
  display: inline-block;
}

.action-menu summary {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: #f1f5f9;
  color: #475569;
  cursor: pointer;
  list-style: none;
  font-size: 22px;
}

.action-menu summary::-webkit-details-marker {
  display: none;
}

.action-dropdown {
  position: absolute;
  z-index: 40;
  top: calc(100% + 6px);
  right: 0;
  width: 170px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 11px;
  background: white;
  box-shadow:
    0 15px 35px rgb(15 23 42 / 16%);
}

.action-dropdown button {
  width: 100%;
  padding: 11px 13px;
  border: 0;
  background: white;
  color: #334155;
  text-align: left;
  font-size: 13px;
  font-weight: 650;
}

.action-dropdown button:hover {
  background: #f8fafc;
}

.danger-action {
  color: #dc2626 !important;
}

.success-action {
  color: #047857 !important;
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
  width: min(100%, 590px);
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

.category-form {
  display: grid;
  gap: 18px;
  padding: 23px;
  overflow-y: auto;
}

.textarea-information {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.textarea-information small {
  margin-left: auto;
  color: #94a3b8;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
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
  padding: 11px 13px;
  border-radius: 10px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 12px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 19px;
  border-top: 1px solid #e2e8f0;
}

.cancel-button,
.submit-button {
  min-width: 120px;
  height: 44px;
  padding: 0 17px;
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

@media (max-width: 760px) {
  .page-header {
    flex-direction: column;
  }

  .primary-button {
    width: 100%;
  }

  .filter-grid {
    grid-template-columns: 1fr;
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

@media (max-width: 520px) {
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
  .category-form {
    padding: 19px;
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
</style>