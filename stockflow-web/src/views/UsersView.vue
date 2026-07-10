<script setup lang="ts">
import axios from 'axios'
import { toast } from 'vue-sonner'
import {
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue'

import {
  createUser,
  getUsers,
} from '@/services/userService'

import type {
  CreateUserPayload,
  PaginatedUsersResponse,
  StaffRole,
  UserFilters,
  UserItem,
  UserValidationErrors,
} from '@/types/user'

interface ApiErrorResponse {
  message?: string
  errors?: UserValidationErrors
}

const users = ref<UserItem[]>([])
const pagination = ref<PaginatedUsersResponse['meta'] | null>(
  null,
)

const isLoading = ref(false)
const errorMessage = ref('')

const filters = reactive<UserFilters>({
  search: '',
  role: '',
  status: '',
  page: 1,
  per_page: 10,
})

const isCreateModalOpen = ref(false)
const isSubmitting = ref(false)
const createErrorMessage = ref('')
const formErrors = ref<UserValidationErrors>({})

const createForm = reactive<CreateUserPayload>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'cashier',
  is_active: true,
})

async function loadUsers(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getUsers(filters)

    users.value = response.data
    pagination.value = response.meta
  } catch (error: unknown) {
    if (axios.isAxiosError(error)) {
      if (error.response?.status === 401) {
        errorMessage.value =
          'Session login sudah tidak valid. Silakan login kembali.'
        return
      }

      if (error.response?.status === 403) {
        errorMessage.value =
          'Anda tidak memiliki izin untuk melihat daftar user.'
        return
      }

      if (!error.response) {
        errorMessage.value =
          'Tidak dapat terhubung ke server Laravel.'
        return
      }
    }

    errorMessage.value =
      'Terjadi kesalahan saat mengambil daftar user.'
  } finally {
    isLoading.value = false
  }
}

async function applyFilters(): Promise<void> {
  filters.page = 1
  await loadUsers()
}

async function resetFilters(): Promise<void> {
  filters.search = ''
  filters.role = ''
  filters.status = ''
  filters.page = 1

  await loadUsers()
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
  await loadUsers()
}

function roleLabel(role: UserItem['role']): string {
  const labels: Record<UserItem['role'], string> = {
    owner: 'Owner',
    admin: 'Admin',
    cashier: 'Cashier',
  }

  return labels[role]
}

function resetCreateForm(): void {
  createForm.name = ''
  createForm.email = ''
  createForm.password = ''
  createForm.password_confirmation = ''
  createForm.role = 'cashier'
  createForm.is_active = true

  formErrors.value = {}
  createErrorMessage.value = ''
}

function openCreateModal(): void {
  resetCreateForm()
  isCreateModalOpen.value = true
}

function closeCreateModal(): void {
  if (isSubmitting.value) {
    return
  }

  isCreateModalOpen.value = false
  resetCreateForm()
}

function validateCreateForm(): boolean {
  formErrors.value = {}
  createErrorMessage.value = ''

  const errors: UserValidationErrors = {}

  if (!createForm.name.trim()) {
    errors.name = ['Nama wajib diisi.']
  }

  if (!createForm.email.trim()) {
    errors.email = ['Email wajib diisi.']
  }

  if (!createForm.password) {
    errors.password = ['Password wajib diisi.']
  } else if (createForm.password.length < 8) {
    errors.password = [
      'Password minimal terdiri dari 8 karakter.',
    ]
  }

  if (!createForm.password_confirmation) {
    errors.password_confirmation = [
      'Konfirmasi password wajib diisi.',
    ]
  } else if (
    createForm.password !==
    createForm.password_confirmation
  ) {
    errors.password_confirmation = [
      'Konfirmasi password tidak sesuai.',
    ]
  }

  formErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitCreateUser(): Promise<void> {
  if (!validateCreateForm()) {
    return
  }

  isSubmitting.value = true
  createErrorMessage.value = ''

  try {
  const createdUserName = createForm.name.trim()
  const createdUserRole = createForm.role

  const response = await createUser({
    name: createdUserName,
    email: createForm.email.trim(),
    password: createForm.password,
    password_confirmation:
      createForm.password_confirmation,
    role: createdUserRole,
    is_active: createForm.is_active,
  })

  isCreateModalOpen.value = false
  resetCreateForm()

  toast.success(
    response.message || 'User berhasil ditambahkan.',
    {
      description: `${createdUserName} berhasil dibuat sebagai ${roleLabel(createdUserRole)}.`,
    },
  )

  filters.page = 1
  await loadUsers()
} catch (error: unknown) {
    if (!axios.isAxiosError<ApiErrorResponse>(error)) {
      createErrorMessage.value =
        'Terjadi kesalahan yang tidak dikenali.'
      return
    }

    if (!error.response) {
      createErrorMessage.value =
        'Tidak dapat terhubung ke server Laravel.'
      return
    }

    const status = error.response.status
    const data = error.response.data

    if (status === 422) {
      formErrors.value = data.errors ?? {}

      if (Object.keys(formErrors.value).length === 0) {
        createErrorMessage.value =
          data.message ?? 'Data user tidak valid.'
      }

      return
    }

    if (status === 401) {
      createErrorMessage.value =
        'Session login sudah tidak valid.'
      return
    }

    if (status === 403) {
      createErrorMessage.value =
        'Anda tidak memiliki izin untuk membuat user.'
      return
    }

    if (status === 419) {
      createErrorMessage.value =
        'Sesi keamanan sudah tidak valid. Muat ulang halaman.'
      return
    }

    if (status >= 500) {
      createErrorMessage.value =
        'Server mengalami masalah saat membuat user.'
      return
    }

    createErrorMessage.value =
      data.message ?? 'User gagal ditambahkan.'
  } finally {
    isSubmitting.value = false
  }
}

function handleEscape(event: KeyboardEvent): void {
  if (
    event.key === 'Escape' &&
    isCreateModalOpen.value
  ) {
    closeCreateModal()
  }
}

watch(isCreateModalOpen, (isOpen) => {
  document.body.style.overflow = isOpen ? 'hidden' : ''
})

onMounted(() => {
  loadUsers()
  window.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', handleEscape)
})
</script>

<template>
  <main class="users-page">
    <section class="page-container">
      <header class="page-header">
        <div>
          <p class="eyebrow">User Management</p>
          <h1>Kelola Pengguna</h1>
          <p class="subtitle">
            Kelola akun Owner, Admin, dan Cashier yang dapat
            mengakses StockFlow.
          </p>
        </div>

        <button
            type="button"
            class="primary-button"
            @click="openCreateModal"
        >
            Tambah User
        </button>
      </header>

      <section class="filter-card">
        <form class="filter-grid" @submit.prevent="applyFilters">
          <div class="form-group search-field">
            <label for="search">Pencarian</label>

            <input
              id="search"
              v-model="filters.search"
              type="search"
              placeholder="Cari nama atau email..."
            />
          </div>

          <div class="form-group">
            <label for="role">Role</label>

            <select id="role" v-model="filters.role">
              <option value="">Semua role</option>
              <option value="owner">Owner</option>
              <option value="admin">Admin</option>
              <option value="cashier">Cashier</option>
            </select>
          </div>

          <div class="form-group">
            <label for="status">Status</label>

            <select id="status" v-model="filters.status">
              <option value="">Semua status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Tidak aktif</option>
            </select>
          </div>

          <div class="filter-actions">
            <button type="submit" class="filter-button">
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

        <div v-if="errorMessage" class="alert-error">
            {{ errorMessage }}
        </div>

      <section class="table-card">
        <div v-if="isLoading" class="state-message">
          Memuat daftar pengguna...
        </div>

        <div
          v-else-if="users.length === 0"
          class="state-message"
        >
          Tidak ada pengguna yang sesuai dengan filter.
        </div>

        <div v-else class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Pengguna</th>
                <th>Role</th>
                <th>Status</th>
                <th>Tanggal Dibuat</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>
                  <div class="user-cell">
                    <div class="avatar">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>

                    <div>
                      <strong>{{ user.name }}</strong>
                      <span>{{ user.email }}</span>
                    </div>
                  </div>
                </td>

                <td>
                  <span :class="['role-badge', user.role]">
                    {{ roleLabel(user.role) }}
                  </span>
                </td>

                <td>
                  <span
                    :class="[
                      'status-badge',
                      user.is_active ? 'active' : 'inactive',
                    ]"
                  >
                    {{ user.is_active ? 'Aktif' : 'Tidak aktif' }}
                  </span>
                </td>

                <td>
                  {{
                    user.created_at
                      ? new Date(user.created_at).toLocaleDateString(
                          'id-ID',
                          {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                          },
                        )
                      : '-'
                  }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer
          v-if="pagination && pagination.total > 0"
          class="pagination"
        >
          <p>
            Menampilkan
            <strong>{{ pagination.from }}</strong>
            sampai
            <strong>{{ pagination.to }}</strong>
            dari
            <strong>{{ pagination.total }}</strong>
            pengguna
          </p>

          <div class="pagination-buttons">
            <button
              type="button"
              :disabled="pagination.current_page === 1"
              @click="changePage(pagination.current_page - 1)"
            >
              Sebelumnya
            </button>

            <span>
              Halaman {{ pagination.current_page }} dari
              {{ pagination.last_page }}
            </span>

            <button
              type="button"
              :disabled="
                pagination.current_page === pagination.last_page
              "
              @click="changePage(pagination.current_page + 1)"
            >
              Berikutnya
            </button>
          </div>
        </footer>
      </section>
    </section>
    <Teleport to="body">
        <div
            v-if="isCreateModalOpen"
            class="modal-backdrop"
            @click.self="closeCreateModal"
        >
            <section
            class="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="create-user-title"
            >
            <header class="modal-header">
                <div>
                <p class="eyebrow">Akun Pegawai</p>
                <h2 id="create-user-title">Tambah User</h2>
                <p>
                    Buat akun Admin atau Cashier untuk mengakses
                    StockFlow.
                </p>
                </div>

                <button
                type="button"
                class="modal-close"
                :disabled="isSubmitting"
                aria-label="Tutup modal"
                @click="closeCreateModal"
                >
                ×
                </button>
            </header>

            <form
                class="create-user-form"
                novalidate
                @submit.prevent="submitCreateUser"
            >
                <div v-if="createErrorMessage" class="alert-error">
                {{ createErrorMessage }}
                </div>

                <div class="modal-form-grid">
                <div class="form-group full-column">
                    <label for="create-name">Nama</label>

                    <input
                    id="create-name"
                    v-model="createForm.name"
                    type="text"
                    autocomplete="name"
                    placeholder="Contoh: Budi Santoso"
                    />

                    <span
                    v-if="formErrors.name?.[0]"
                    class="field-error"
                    >
                    {{ formErrors.name[0] }}
                    </span>
                </div>

                <div class="form-group full-column">
                    <label for="create-email">Email</label>

                    <input
                    id="create-email"
                    v-model="createForm.email"
                    type="email"
                    autocomplete="email"
                    placeholder="Contoh: kasir@stockflow.test"
                    />

                    <span
                    v-if="formErrors.email?.[0]"
                    class="field-error"
                    >
                    {{ formErrors.email[0] }}
                    </span>
                </div>

                <div class="form-group">
                    <label for="create-role">Role</label>

                    <select
                    id="create-role"
                    v-model="createForm.role"
                    >
                    <option value="admin">Admin</option>
                    <option value="cashier">Cashier</option>
                    </select>

                    <span
                    v-if="formErrors.role?.[0]"
                    class="field-error"
                    >
                    {{ formErrors.role[0] }}
                    </span>
                </div>

                <label class="status-checkbox">
                    <input
                    v-model="createForm.is_active"
                    type="checkbox"
                    />

                    <span>
                    <strong>Akun aktif</strong>
                    <small>
                        User dapat langsung login setelah dibuat.
                    </small>
                    </span>
                </label>

                <div class="form-group">
                    <label for="create-password">Password</label>

                    <input
                    id="create-password"
                    v-model="createForm.password"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    />

                    <span
                    v-if="formErrors.password?.[0]"
                    class="field-error"
                    >
                    {{ formErrors.password[0] }}
                    </span>
                </div>

                <div class="form-group">
                    <label for="create-password-confirmation">
                    Konfirmasi Password
                    </label>

                    <input
                    id="create-password-confirmation"
                    v-model="createForm.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Ulangi password"
                    />

                    <span
                    v-if="
                        formErrors.password_confirmation?.[0]
                    "
                    class="field-error"
                    >
                    {{
                        formErrors.password_confirmation[0]
                    }}
                    </span>
                </div>
                </div>

                <footer class="modal-actions">
                <button
                    type="button"
                    class="cancel-button"
                    :disabled="isSubmitting"
                    @click="closeCreateModal"
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
                        : 'Simpan User'
                    }}
                </button>
                </footer>
            </form>
            </section>
        </div>
        </Teleport>
  </main>
</template>

<style scoped>
.users-page {
  min-height: 100vh;
  padding: 40px 24px;
  background: #f1f5f9;
}

.page-container {
  width: min(100%, 1180px);
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 28px;
}

.eyebrow {
  margin: 0 0 8px;
  color: #047857;
  font-size: 13px;
  font-weight: 750;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

h1 {
  margin: 0;
  font-size: 32px;
}

.subtitle {
  margin: 10px 0 0;
  color: #64748b;
}

.primary-button,
.filter-button,
.reset-button,
.pagination button {
  border: 0;
  border-radius: 10px;
  font-weight: 650;
}

.primary-button {
  padding: 12px 18px;
  background: #047857;
  color: white;
}

.filter-card,
.table-card {
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  background: white;
  box-shadow: 0 12px 35px rgb(15 23 42 / 4%);
}

.filter-card {
  margin-bottom: 20px;
  padding: 20px;
}

.filter-grid {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) 180px 180px auto;
  gap: 14px;
  align-items: end;
}

.form-group {
  display: grid;
  gap: 7px;
}

.form-group label {
  color: #475569;
  font-size: 13px;
  font-weight: 650;
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
  box-shadow: 0 0 0 3px rgb(5 150 105 / 10%);
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
  margin-bottom: 20px;
  padding: 14px 16px;
  border: 1px solid #fecaca;
  border-radius: 12px;
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
  border-collapse: collapse;
}

th,
td {
  padding: 17px 20px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

th {
  background: #f8fafc;
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

td {
  color: #334155;
  font-size: 14px;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 40px;
  height: 40px;
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 12px;
  background: #d1fae5;
  color: #047857;
  font-weight: 800;
}

.user-cell strong,
.user-cell span {
  display: block;
}

.user-cell span {
  margin-top: 4px;
  color: #64748b;
  font-size: 13px;
}

.role-badge,
.status-badge {
  display: inline-flex;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.role-badge.owner {
  background: #ede9fe;
  color: #6d28d9;
}

.role-badge.admin {
  background: #dbeafe;
  color: #1d4ed8;
}

.role-badge.cashier {
  background: #fef3c7;
  color: #b45309;
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
  padding: 64px 24px;
  color: #64748b;
  text-align: center;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 18px 20px;
}

.pagination p {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.pagination-buttons {
  display: flex;
  align-items: center;
  gap: 12px;
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
  display: grid;
  place-items: center;
  padding: 20px;
  overflow-y: auto;
  background: rgb(15 23 42 / 55%);
  backdrop-filter: blur(3px);
}

.modal-card {
  width: min(100%, 640px);
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  border-radius: 20px;
  background: white;
  box-shadow: 0 28px 80px rgb(15 23 42 / 25%);
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  padding: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  margin: 0;
  font-size: 24px;
}

.modal-header p:not(.eyebrow) {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 14px;
}

.modal-close {
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  border: 0;
  border-radius: 10px;
  background: #f1f5f9;
  color: #475569;
  font-size: 24px;
  line-height: 1;
}

.create-user-form {
  padding: 24px;
}

.modal-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}

.full-column {
  grid-column: 1 / -1;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
}

.status-checkbox {
  min-height: 44px;
  display: flex;
  align-items: center;
  gap: 11px;
  align-self: end;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  cursor: pointer;
}

.status-checkbox input {
  width: 17px;
  height: 17px;
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
  margin-top: 2px;
  color: #64748b;
  font-size: 11px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 26px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.cancel-button,
.submit-button {
  min-width: 110px;
  height: 44px;
  padding: 0 18px;
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

@media (max-width: 640px) {
  .modal-backdrop {
    padding: 10px;
  }

  .modal-card {
    max-height: calc(100vh - 20px);
    border-radius: 16px;
  }

  .modal-header,
  .create-user-form {
    padding: 20px;
  }

  .modal-form-grid {
    grid-template-columns: 1fr;
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
  }
}

@media (max-width: 900px) {
  .filter-grid {
    grid-template-columns: 1fr 1fr;
  }

  .search-field {
    grid-column: 1 / -1;
  }
}

@media (max-width: 640px) {
  .users-page {
    padding: 24px 14px;
  }

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