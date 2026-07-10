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
  createSupplier,
  getSuppliers,
  toggleSupplierStatus,
  updateSupplier,
} from '@/services/supplierService'

import type {
  SupplierFilters,
  SupplierItem,
  SupplierPaginationMeta,
  SupplierValidationErrors,
} from '@/types/supplier'

interface ApiErrorResponse {
  message?: string
  errors?: SupplierValidationErrors
}

interface SupplierFormState {
  code: string
  name: string
  contact_person: string
  phone: string
  email: string
  address: string
  is_active: boolean
}

const suppliers = ref<SupplierItem[]>([])

const pagination =
  ref<SupplierPaginationMeta | null>(null)

const isLoading = ref(false)
const errorMessage = ref('')

const filters = reactive<SupplierFilters>({
  search: '',
  status: '',
  page: 1,
  per_page: 10,
})

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const editingSupplierId = ref<number | null>(null)

const modalErrorMessage = ref('')

const formErrors =
  ref<SupplierValidationErrors>({})

const supplierForm =
  reactive<SupplierFormState>({
    code: '',
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    is_active: true,
  })

const openActionSupplierId =
  ref<number | null>(null)

const actionMenuPosition = reactive({
  top: 0,
  left: 0,
})

const isEditMode = computed(() => {
  return editingSupplierId.value !== null
})

const modalTitle = computed(() => {
  return isEditMode.value
    ? 'Edit Supplier'
    : 'Tambah Supplier'
})

const activeActionSupplier = computed(() => {
  return (
    suppliers.value.find(
      (supplier) =>
        supplier.id ===
        openActionSupplierId.value,
    ) ?? null
  )
})

async function loadSuppliers(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response =
      await getSuppliers(filters)

    suppliers.value = response.data
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
          'Anda tidak memiliki akses ke supplier.'

        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil data supplier.'
  } finally {
    isLoading.value = false
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1
  await loadSuppliers()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.status = ''
  filters.page = 1

  await loadSuppliers()
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
  await loadSuppliers()
}

function resetSupplierForm(): void {
  supplierForm.code = ''
  supplierForm.name = ''
  supplierForm.contact_person = ''
  supplierForm.phone = ''
  supplierForm.email = ''
  supplierForm.address = ''
  supplierForm.is_active = true

  editingSupplierId.value = null
  modalErrorMessage.value = ''
  formErrors.value = {}
}

function openCreateModal(): void {
  closeActionMenu()
  resetSupplierForm()

  isModalOpen.value = true
}

function openEditModal(
  supplier: SupplierItem,
): void {
  closeActionMenu()
  resetSupplierForm()

  editingSupplierId.value = supplier.id

  supplierForm.code = supplier.code
  supplierForm.name = supplier.name

  supplierForm.contact_person =
    supplier.contact_person ?? ''

  supplierForm.phone =
    supplier.phone ?? ''

  supplierForm.email =
    supplier.email ?? ''

  supplierForm.address =
    supplier.address ?? ''

  isModalOpen.value = true
}

function closeModal(): void {
  if (isSubmitting.value) {
    return
  }

  isModalOpen.value = false
  resetSupplierForm()
}

function validateSupplierForm(): boolean {
  const errors: SupplierValidationErrors = {}

  if (!supplierForm.code.trim()) {
    errors.code = [
      'Kode supplier wajib diisi.',
    ]
  }

  if (!supplierForm.name.trim()) {
    errors.name = [
      'Nama supplier wajib diisi.',
    ]
  }

  if (
    supplierForm.email.trim() &&
    !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
      supplierForm.email.trim(),
    )
  ) {
    errors.email = [
      'Format email tidak valid.',
    ]
  }

  formErrors.value = errors

  return Object.keys(errors).length === 0
}

function nullableString(
  value: string,
): string | null {
  const normalizedValue = value.trim()

  return normalizedValue === ''
    ? null
    : normalizedValue
}

async function submitSupplier(): Promise<void> {
  if (!validateSupplierForm()) {
    return
  }

  isSubmitting.value = true
  modalErrorMessage.value = ''
  formErrors.value = {}

  try {
    const supplierName =
      supplierForm.name.trim()

    const basePayload = {
      code: supplierForm.code
        .trim()
        .toUpperCase(),

      name: supplierName,

      contact_person: nullableString(
        supplierForm.contact_person,
      ),

      phone: nullableString(
        supplierForm.phone,
      ),

      email: nullableString(
        supplierForm.email,
      )?.toLowerCase() ?? null,

      address: nullableString(
        supplierForm.address,
      ),
    }

    let response

    if (
      isEditMode.value &&
      editingSupplierId.value !== null
    ) {
      response = await updateSupplier(
        editingSupplierId.value,
        basePayload,
      )
    } else {
      response = await createSupplier({
        ...basePayload,
        is_active: supplierForm.is_active,
      })
    }

    isModalOpen.value = false
    resetSupplierForm()

    toast.success(response.message, {
      description: `${supplierName} berhasil disimpan.`,
    })

    filters.page = 1
    await loadSuppliers()
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

      modalErrorMessage.value =
        'Periksa kembali data supplier yang ditandai.'

      return
    }

    modalErrorMessage.value =
      data.message ??
      'Supplier gagal disimpan.'
  } finally {
    isSubmitting.value = false
  }
}

async function confirmToggleStatus(
  supplier: SupplierItem,
): Promise<void> {
  closeActionMenu()

  const willActivate = !supplier.is_active

  const result = await Swal.fire({
    title: willActivate
      ? 'Aktifkan supplier?'
      : 'Nonaktifkan supplier?',

    text: willActivate
      ? `${supplier.name} dapat digunakan kembali pada transaksi pembelian.`
      : `${supplier.name} tidak dapat dipilih untuk transaksi pembelian baru.`,

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
      allowEscapeKey: false,
      allowOutsideClick: false,

      didOpen: () => {
        Swal.showLoading()
      },
    })

    const response =
      await toggleSupplierStatus(
        supplier.id,
      )

    Swal.close()

    toast.success(response.message)

    await loadSuppliers()
  } catch (error: unknown) {
    Swal.close()

    if (
      axios.isAxiosError<ApiErrorResponse>(error) &&
      error.response?.data.message
    ) {
      toast.error(
        error.response.data.message,
      )

      return
    }

    toast.error(
      'Status supplier gagal diperbarui.',
    )
  }
}

function closeActionMenu(): void {
  openActionSupplierId.value = null
}

function toggleActionMenu(
  supplier: SupplierItem,
  event: MouseEvent,
): void {
  if (
    openActionSupplierId.value === supplier.id
  ) {
    closeActionMenu()
    return
  }

  const button =
    event.currentTarget as HTMLElement

  const buttonPosition =
    button.getBoundingClientRect()

  const menuWidth = 180
  const menuHeight = 96
  const screenPadding = 12
  const menuGap = 6

  let top =
    buttonPosition.bottom + menuGap

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

  openActionSupplierId.value = supplier.id
}

function supplierInitial(
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

  await loadSuppliers()
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''

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
</script>

<template>
  <section class="suppliers-page">
    <header class="page-header">
      <div>
        <p class="eyebrow">
          Master Data
        </p>

        <h2>Supplier</h2>

        <p class="subtitle">
          Kelola supplier yang digunakan
          dalam transaksi pembelian barang.
        </p>
      </div>

      <button
        type="button"
        class="primary-button"
        @click="openCreateModal"
      >
        Tambah Supplier
      </button>
    </header>

    <section class="filter-card">
      <form
        class="filter-grid"
        @submit.prevent="applyFilters"
      >
        <div class="form-group">
          <label for="supplier-search">
            Pencarian
          </label>

          <input
            id="supplier-search"
            v-model="filters.search"
            type="search"
            placeholder="Cari kode, nama, kontak, telepon..."
          />
        </div>

        <div class="form-group">
          <label for="supplier-status">
            Status
          </label>

          <select
            id="supplier-status"
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
        Memuat data supplier...
      </div>

      <div
        v-else-if="suppliers.length === 0"
        class="state-message"
      >
        Belum ada supplier yang sesuai.
      </div>

      <div
        v-else
        class="table-wrapper"
      >
        <table>
          <thead>
            <tr>
              <th>Supplier</th>
              <th>Contact Person</th>
              <th>Kontak</th>
              <th>Alamat</th>
              <th>Status</th>
              <th class="action-column">
                Aksi
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="supplier in suppliers"
              :key="supplier.id"
            >
              <td>
                <div class="supplier-cell">
                  <div class="supplier-avatar">
                    {{
                      supplierInitial(
                        supplier.name,
                      )
                    }}
                  </div>

                  <div class="supplier-information">
                    <strong>
                      {{ supplier.name }}
                    </strong>

                    <span>
                      {{ supplier.code }}
                    </span>
                  </div>
                </div>
              </td>

              <td>
                {{
                  supplier.contact_person ??
                  '-'
                }}
              </td>

              <td>
                <div class="contact-information">
                  <span>
                    {{ supplier.phone ?? '-' }}
                  </span>

                  <small>
                    {{ supplier.email ?? '-' }}
                  </small>
                </div>
              </td>

              <td>
                <span class="address-text">
                  {{ supplier.address ?? '-' }}
                </span>
              </td>

              <td>
                <span
                  :class="[
                    'status-badge',
                    supplier.is_active
                      ? 'active'
                      : 'inactive',
                  ]"
                >
                  {{
                    supplier.is_active
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
                      openActionSupplierId ===
                      supplier.id,
                  }"
                  aria-label="Buka menu aksi supplier"
                  @click.stop="
                    toggleActionMenu(
                      supplier,
                      $event,
                    )
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
          supplier
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
        v-if="activeActionSupplier"
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
            openEditModal(
              activeActionSupplier,
            )
          "
        >
          Edit Supplier
        </button>

        <button
          type="button"
          :class="{
            'danger-action':
              activeActionSupplier.is_active,

            'success-action':
              !activeActionSupplier.is_active,
          }"
          @click="
            confirmToggleStatus(
              activeActionSupplier,
            )
          "
        >
          {{
            activeActionSupplier.is_active
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
          aria-labelledby="supplier-modal-title"
        >
          <header class="modal-header">
            <div>
              <p class="eyebrow">
                Master Data
              </p>

              <h2 id="supplier-modal-title">
                {{ modalTitle }}
              </h2>

              <p>
                {{
                  isEditMode
                    ? 'Perbarui informasi supplier.'
                    : 'Tambahkan supplier baru.'
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
            class="supplier-form"
            novalidate
            @submit.prevent="submitSupplier"
          >
            <div
              v-if="modalErrorMessage"
              class="alert-error"
            >
              {{ modalErrorMessage }}
            </div>

            <div class="supplier-form-grid">
              <div class="form-group">
                <label for="supplier-code">
                  Kode Supplier
                </label>

                <input
                  id="supplier-code"
                  v-model="supplierForm.code"
                  type="text"
                  maxlength="50"
                  class="uppercase-input"
                  placeholder="Contoh: SUP-001"
                />

                <span
                  v-if="formErrors.code?.[0]"
                  class="field-error"
                >
                  {{ formErrors.code[0] }}
                </span>
              </div>

              <div class="form-group">
                <label for="supplier-name">
                  Nama Supplier
                </label>

                <input
                  id="supplier-name"
                  v-model="supplierForm.name"
                  type="text"
                  maxlength="255"
                  placeholder="PT Sumber Makmur"
                />

                <span
                  v-if="formErrors.name?.[0]"
                  class="field-error"
                >
                  {{ formErrors.name[0] }}
                </span>
              </div>

              <div class="form-group">
                <label for="contact-person">
                  Contact Person
                </label>

                <input
                  id="contact-person"
                  v-model="
                    supplierForm.contact_person
                  "
                  type="text"
                  maxlength="255"
                  placeholder="Contoh: Budi Santoso"
                />

                <span
                  v-if="
                    formErrors.contact_person?.[0]
                  "
                  class="field-error"
                >
                  {{
                    formErrors.contact_person[0]
                  }}
                </span>
              </div>

              <div class="form-group">
                <label for="supplier-phone">
                  Nomor Telepon
                </label>

                <input
                  id="supplier-phone"
                  v-model="supplierForm.phone"
                  type="tel"
                  maxlength="30"
                  placeholder="081234567890"
                />

                <span
                  v-if="formErrors.phone?.[0]"
                  class="field-error"
                >
                  {{ formErrors.phone[0] }}
                </span>
              </div>

              <div class="form-group full-column">
                <label for="supplier-email">
                  Email
                </label>

                <input
                  id="supplier-email"
                  v-model="supplierForm.email"
                  type="email"
                  maxlength="255"
                  placeholder="sales@supplier.com"
                />

                <span
                  v-if="formErrors.email?.[0]"
                  class="field-error"
                >
                  {{ formErrors.email[0] }}
                </span>
              </div>

              <div class="form-group full-column">
                <label for="supplier-address">
                  Alamat
                </label>

                <textarea
                  id="supplier-address"
                  v-model="supplierForm.address"
                  rows="4"
                  maxlength="1000"
                  placeholder="Masukkan alamat supplier..."
                />

                <span
                  v-if="formErrors.address?.[0]"
                  class="field-error"
                >
                  {{ formErrors.address[0] }}
                </span>
              </div>

              <label
                v-if="!isEditMode"
                class="status-checkbox full-column"
              >
                <input
                  v-model="
                    supplierForm.is_active
                  "
                  type="checkbox"
                />

                <span>
                  <strong>
                    Supplier aktif
                  </strong>

                  <small>
                    Supplier dapat digunakan
                    untuk transaksi pembelian.
                  </small>
                </span>
              </label>

              <div
                v-else
                class="form-information full-column"
              >
                Status supplier diubah melalui
                menu aksi pada tabel.
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
                      : 'Simpan Supplier'
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
.suppliers-page {
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
    180px
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
}

.table-card {
  overflow: hidden;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 980px;
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

.supplier-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.supplier-avatar {
  width: 44px;
  height: 44px;
  flex: 0 0 auto;

  display: grid;
  place-items: center;

  border-radius: 12px;
  background: #d1fae5;
  color: #047857;

  font-size: 18px;
  font-weight: 800;
}

.supplier-information strong,
.supplier-information span {
  display: block;
}

.supplier-information strong {
  color: #0f172a;
}

.supplier-information span {
  margin-top: 4px;
  color: #64748b;
  font-size: 12px;
}

.contact-information span,
.contact-information small {
  display: block;
}

.contact-information small {
  margin-top: 4px;
  color: #64748b;
}

.address-text {
  display: block;
  max-width: 260px;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-badge {
  display: inline-flex;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
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

.action-column,
.action-cell {
  width: 80px;
  text-align: right;
}

.action-button {
  width: 38px;
  height: 38px;

  display: inline-grid;
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
  width: min(100%, 720px);
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

.supplier-form {
  padding: 23px;
  overflow-y: auto;
}

.supplier-form-grid {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.full-column {
  grid-column: 1 / -1;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
  line-height: 1.4;
}

.uppercase-input {
  text-transform: uppercase;
}

.status-checkbox {
  display: flex;
  align-items: center;
  gap: 11px;

  padding: 12px 14px;

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

@media (max-width: 760px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }

  .page-header {
    flex-direction: column;
  }

  .primary-button {
    width: 100%;
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
  .supplier-form {
    padding: 19px;
  }

  .supplier-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .full-column {
    grid-column: auto;
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