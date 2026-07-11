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

import {
  closeCashSession,
  getCurrentCashSession,
  openCashSession,
} from '@/services/cashSessionService'

import type {
  CashSessionItem,
  CashSessionValidationErrors,
  CloseCashSessionPayload,
  OpenCashSessionPayload,
} from '@/types/cashSession'

interface ApiErrorResponse {
  message?: string
  errors?: CashSessionValidationErrors
}

interface OpenSessionForm {
  opening_cash: string
  opening_notes: string
}

interface CloseSessionForm {
  actual_closing_cash: string
  closing_notes: string
}

const router = useRouter()

const currentSession =
  ref<CashSessionItem | null>(null)

const lastClosedSession =
  ref<CashSessionItem | null>(null)

const registerInUse = ref(false)
const serverMessage = ref('')

const isLoading = ref(false)
const isOpening = ref(false)
const isClosing = ref(false)

const isCloseModalOpen = ref(false)

const pageErrorMessage = ref('')
const openFormErrors =
  ref<CashSessionValidationErrors>({})

const closeFormErrors =
  ref<CashSessionValidationErrors>({})

const openForm =
  reactive<OpenSessionForm>({
    opening_cash: '',
    opening_notes: '',
  })

const closeForm =
  reactive<CloseSessionForm>({
    actual_closing_cash: '',
    closing_notes: '',
  })

const isMySessionOpen = computed(() => {
  return (
    currentSession.value !== null &&
    currentSession.value.status === 'open' &&
    currentSession.value
      .is_owned_by_current_user
  )
})

const isOtherSessionOpen = computed(() => {
  return (
    currentSession.value !== null &&
    currentSession.value.status === 'open' &&
    !currentSession.value
      .is_owned_by_current_user
  )
})

async function loadCurrentSession():
  Promise<void> {
  isLoading.value = true
  pageErrorMessage.value = ''

  try {
    const response =
      await getCurrentCashSession()

    currentSession.value =
      response.session

    registerInUse.value =
      response.register_in_use

    serverMessage.value =
      response.message
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
          'Anda tidak memiliki akses ke sesi kasir.'

        return
      }
    }

    pageErrorMessage.value =
      'Status sesi kasir gagal dimuat.'
  } finally {
    isLoading.value = false
  }
}

function validateOpenForm(): boolean {
  const errors:
    CashSessionValidationErrors = {}

  const openingCash =
    Number(openForm.opening_cash)

  if (openForm.opening_cash === '') {
    errors.opening_cash = [
      'Modal awal wajib diisi.',
    ]
  } else if (
    !Number.isFinite(openingCash) ||
    openingCash < 0
  ) {
    errors.opening_cash = [
      'Modal awal tidak boleh negatif.',
    ]
  }

  openFormErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitOpenSession():
  Promise<void> {
  if (!validateOpenForm()) {
    return
  }

  const openingCash =
    Number(openForm.opening_cash)

  const confirmation =
    await Swal.fire({
      title: 'Buka sesi kasir?',

      html: `
        <div style="line-height:1.7">
          Modal awal:
          <strong>${formatCurrency(
            openingCash,
          )}</strong>
        </div>
      `,

      icon: 'question',

      showCancelButton: true,

      confirmButtonText:
        'Ya, buka kasir',

      cancelButtonText: 'Batal',

      reverseButtons: true,
    })

  if (!confirmation.isConfirmed) {
    return
  }

  isOpening.value = true
  pageErrorMessage.value = ''
  openFormErrors.value = {}

  try {
    const payload:
      OpenCashSessionPayload = {
        opening_cash:
          openingCash,

        opening_notes:
          openForm.opening_notes
            .trim() || null,
      }

    const response =
      await openCashSession(payload)

    currentSession.value =
      response.session

    registerInUse.value = true
    lastClosedSession.value = null

    openForm.opening_cash = ''
    openForm.opening_notes = ''

    toast.success(response.message, {
      description:
        response.session.session_number,
    })
  } catch (error: unknown) {
    handleOpenError(error)
  } finally {
    isOpening.value = false
  }
}

function handleOpenError(
  error: unknown,
): void {
  if (
    !axios.isAxiosError<ApiErrorResponse>(
      error,
    )
  ) {
    pageErrorMessage.value =
      'Sesi kasir gagal dibuka.'

    return
  }

  if (!error.response) {
    pageErrorMessage.value =
      'Tidak dapat terhubung ke server Laravel.'

    return
  }

  const data =
    error.response.data

  if (error.response.status === 422) {
    openFormErrors.value =
      data.errors ?? {}

    pageErrorMessage.value =
      data.errors?.session?.[0] ??
      'Periksa kembali data pembukaan kasir.'

    return
  }

  pageErrorMessage.value =
    data.message ??
    'Sesi kasir gagal dibuka.'
}

function openCloseModal(): void {
  closeForm.actual_closing_cash = ''
  closeForm.closing_notes = ''

  closeFormErrors.value = {}
  pageErrorMessage.value = ''

  isCloseModalOpen.value = true
}

function closeCloseModal(): void {
  if (isClosing.value) {
    return
  }

  isCloseModalOpen.value = false
}

function validateCloseForm(): boolean {
  const errors:
    CashSessionValidationErrors = {}

  const actualCash =
    Number(
      closeForm.actual_closing_cash,
    )

  if (
    closeForm.actual_closing_cash === ''
  ) {
    errors.actual_closing_cash = [
      'Jumlah uang fisik wajib diisi.',
    ]
  } else if (
    !Number.isFinite(actualCash) ||
    actualCash < 0
  ) {
    errors.actual_closing_cash = [
      'Jumlah uang fisik tidak boleh negatif.',
    ]
  }

  closeFormErrors.value = errors

  return Object.keys(errors).length === 0
}

async function submitCloseSession():
  Promise<void> {
  if (
    !currentSession.value ||
    !validateCloseForm()
  ) {
    return
  }

  const actualCash =
    Number(
      closeForm.actual_closing_cash,
    )

  const confirmation =
    await Swal.fire({
      title: 'Tutup sesi kasir?',

      text:
        'Pastikan seluruh uang fisik di laci sudah dihitung dengan benar.',

      icon: 'warning',

      showCancelButton: true,

      confirmButtonText:
        'Ya, tutup sesi',

      cancelButtonText:
        'Hitung kembali',

      reverseButtons: true,
    })

  if (!confirmation.isConfirmed) {
    return
  }

  isClosing.value = true
  closeFormErrors.value = {}

  try {
    const payload:
      CloseCashSessionPayload = {
        actual_closing_cash:
          actualCash,

        closing_notes:
          closeForm.closing_notes
            .trim() || null,
      }

    const response =
      await closeCashSession(
        currentSession.value.id,
        payload,
      )

    lastClosedSession.value =
      response.session

    currentSession.value = null
    registerInUse.value = false

    isCloseModalOpen.value = false

    toast.success(response.message, {
      description:
        response.session.session_number,
    })
  } catch (error: unknown) {
    handleCloseError(error)
  } finally {
    isClosing.value = false
  }
}

function handleCloseError(
  error: unknown,
): void {
  if (
    !axios.isAxiosError<ApiErrorResponse>(
      error,
    )
  ) {
    pageErrorMessage.value =
      'Sesi kasir gagal ditutup.'

    return
  }

  if (!error.response) {
    pageErrorMessage.value =
      'Tidak dapat terhubung ke server Laravel.'

    return
  }

  const data =
    error.response.data

  if (error.response.status === 422) {
    closeFormErrors.value =
      data.errors ?? {}

    pageErrorMessage.value =
      data.errors?.session?.[0] ??
      'Periksa kembali data penutupan kasir.'

    return
  }

  if (error.response.status === 403) {
    pageErrorMessage.value =
      data.message ??
      'Anda tidak memiliki izin menutup sesi ini.'

    return
  }

  pageErrorMessage.value =
    data.message ??
    'Sesi kasir gagal ditutup.'
}

function openPos(): void {
  if (
    !currentSession.value?.can_use_pos
  ) {
    return
  }

  router.push({
    name: 'pos',
  })
}

function firstOpenError(
  field: string,
): string | null {
  return (
    openFormErrors.value[field]?.[0] ??
    null
  )
}

function firstCloseError(
  field: string,
): string | null {
  return (
    closeFormErrors.value[field]?.[0] ??
    null
  )
}

function differenceLabel(
  session: CashSessionItem,
): string {
  if (
    session.difference_status ===
    'balanced'
  ) {
    return 'Uang sesuai'
  }

  if (
    session.difference_status ===
    'over'
  ) {
    return 'Uang lebih'
  }

  if (
    session.difference_status ===
    'short'
  ) {
    return 'Uang kurang'
  }

  return '-'
}

function differenceClass(
  session: CashSessionItem,
): string {
  if (
    session.difference_status ===
    'balanced'
  ) {
    return 'balanced'
  }

  if (
    session.difference_status ===
    'over'
  ) {
    return 'over'
  }

  if (
    session.difference_status ===
    'short'
  ) {
    return 'short'
  }

  return ''
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
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    },
  ).format(new Date(value))
}

onMounted(() => {
  loadCurrentSession()
})
</script>

<template>
  <section class="session-page">
    <header class="page-header">
      <p class="eyebrow">
        Cashier
      </p>

      <h1>Sesi Kasir</h1>

      <p>
        Buka sesi sebelum menjalankan POS dan
        tutup sesi setelah seluruh uang fisik
        selesai dihitung.
      </p>
    </header>

    <div
      v-if="pageErrorMessage"
      class="alert-error"
    >
      {{ pageErrorMessage }}
    </div>

    <div
      v-if="isLoading"
      class="state-card"
    >
      Memeriksa status meja kasir...
    </div>

    <section
      v-else-if="lastClosedSession"
      class="closing-result-card"
    >
      <div class="result-heading">
        <div>
          <span>Sesi berhasil ditutup</span>

          <h2>
            {{
              lastClosedSession
                .session_number
            }}
          </h2>

          <p>
            {{
              formatDateTime(
                lastClosedSession.closed_at,
              )
            }}
          </p>
        </div>

        <span
          :class="[
            'difference-badge',
            differenceClass(
              lastClosedSession,
            ),
          ]"
        >
          {{
            differenceLabel(
              lastClosedSession,
            )
          }}
        </span>
      </div>

      <div class="closing-summary">
        <article>
          <span>Modal Awal</span>

          <strong>
            {{
              formatCurrency(
                lastClosedSession
                  .opening_cash,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Penjualan Tunai</span>

          <strong>
            {{
              formatCurrency(
                lastClosedSession
                  .cash_sales_total ?? 0,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Uang Seharusnya</span>

          <strong>
            {{
              formatCurrency(
                lastClosedSession
                  .expected_closing_cash ??
                  0,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Uang Fisik</span>

          <strong>
            {{
              formatCurrency(
                lastClosedSession
                  .actual_closing_cash ??
                  0,
              )
            }}
          </strong>
        </article>
      </div>

      <div
        :class="[
          'difference-result',
          differenceClass(
            lastClosedSession,
          ),
        ]"
      >
        <span>Selisih Kas</span>

        <strong>
          {{
            formatCurrency(
              lastClosedSession
                .difference ?? 0,
            )
          }}
        </strong>
      </div>

      <button
        type="button"
        class="secondary-button"
        @click="
          lastClosedSession = null
        "
      >
        Kembali ke Halaman Sesi
      </button>
    </section>

    <section
      v-else-if="isMySessionOpen"
      class="active-session-card"
    >
      <div class="active-status">
        <span class="status-dot" />

        Sesi kasir sedang aktif
      </div>

      <div class="session-heading">
        <div>
          <span>Nomor Sesi</span>

          <h2>
            {{
              currentSession
                ?.session_number
            }}
          </h2>
        </div>

        <div class="cashier-information">
          <span>Kasir</span>

          <strong>
            {{
              currentSession
                ?.cashier.name
            }}
          </strong>
        </div>
      </div>

      <div class="session-information-grid">
        <article>
          <span>Dibuka Pada</span>

          <strong>
            {{
              formatDateTime(
                currentSession?.opened_at ??
                null,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Modal Awal</span>

          <strong>
            {{
              formatCurrency(
                currentSession
                  ?.opening_cash ?? 0,
              )
            }}
          </strong>
        </article>

        <article>
          <span>Status</span>

          <strong class="open-text">
            Open
          </strong>
        </article>
      </div>

      <div
        v-if="
          currentSession?.opening_notes
        "
        class="notes-card"
      >
        <span>Catatan Pembukaan</span>

        <p>
          {{
            currentSession.opening_notes
          }}
        </p>
      </div>

      <div class="session-notice">
        Uang seharusnya tidak ditampilkan
        sebelum sesi ditutup. Hitung seluruh
        uang fisik secara mandiri saat
        penutupan kasir.
      </div>

      <div class="session-actions">
        <button
          type="button"
          class="pos-button"
          :disabled="
            !currentSession?.can_use_pos
          "
          @click="openPos"
        >
          Masuk ke POS
        </button>

        <button
          type="button"
          class="close-session-button"
          :disabled="
            !currentSession?.can_close
          "
          @click="openCloseModal"
        >
          Tutup Kasir
        </button>
      </div>
    </section>

    <section
      v-else-if="isOtherSessionOpen"
      class="blocked-card"
    >
      <div class="blocked-icon">
        !
      </div>

      <h2>Meja kasir sedang digunakan</h2>

      <p>
        Sesi saat ini dibuka oleh
        <strong>
          {{ currentSession?.cashier.name }}
        </strong>.
      </p>

      <div class="blocked-information">
        <div>
          <span>Nomor Sesi</span>

          <strong>
            {{
              currentSession
                ?.session_number
            }}
          </strong>
        </div>

        <div>
          <span>Dibuka Pada</span>

          <strong>
            {{
              formatDateTime(
                currentSession?.opened_at ??
                null,
              )
            }}
          </strong>
        </div>
      </div>

      <button
        type="button"
        class="secondary-button"
        @click="loadCurrentSession"
      >
        Periksa Lagi
      </button>
    </section>

    <section
      v-else
      class="open-session-layout"
    >
      <div class="information-panel">
        <div class="register-icon">
          Rp
        </div>

        <h2>Buka Kasir</h2>

        <p>
          Masukkan modal awal berupa uang
          tunai yang benar-benar dimasukkan
          ke dalam laci kasir.
        </p>

        <div class="workflow">
          <div>
            <span>1</span>
            Masukkan modal awal
          </div>

          <div>
            <span>2</span>
            Jalankan transaksi POS
          </div>

          <div>
            <span>3</span>
            Hitung uang fisik
          </div>

          <div>
            <span>4</span>
            Tutup dan rekonsiliasi kas
          </div>
        </div>
      </div>

      <form
        class="open-session-form"
        novalidate
        @submit.prevent="
          submitOpenSession
        "
      >
        <h2>Modal Awal Kasir</h2>

        <p>
          Pastikan jumlah sesuai dengan uang
          fisik yang tersedia.
        </p>

        <div class="form-group">
          <label for="opening-cash">
            Modal Awal
          </label>

          <div class="currency-input">
            <span>Rp</span>

            <input
              id="opening-cash"
              v-model="
                openForm.opening_cash
              "
              type="number"
              min="0"
              step="0.01"
              placeholder="100000"
            />
          </div>

          <span
            v-if="
              firstOpenError(
                'opening_cash',
              )
            "
            class="field-error"
          >
            {{
              firstOpenError(
                'opening_cash',
              )
            }}
          </span>
        </div>

        <div class="form-group">
          <label for="opening-notes">
            Catatan Pembukaan
          </label>

          <textarea
            id="opening-notes"
            v-model="
              openForm.opening_notes
            "
            rows="4"
            maxlength="1000"
            placeholder="Opsional, contoh: Modal pecahan dari owner"
          />
        </div>

        <span
          v-if="
            firstOpenError('session')
          "
          class="field-error"
        >
          {{
            firstOpenError('session')
          }}
        </span>

        <button
          type="submit"
          class="open-button"
          :disabled="isOpening"
        >
          {{
            isOpening
              ? 'Membuka Kasir...'
              : 'Buka Kasir'
          }}
        </button>
      </form>
    </section>

    <Teleport to="body">
      <div
        v-if="isCloseModalOpen"
        class="modal-overlay"
        @click.self="closeCloseModal"
      >
        <section class="modal-card">
          <header class="modal-header">
            <div>
              <h2>Tutup Sesi Kasir</h2>

              <p>
                Hitung seluruh uang fisik di
                laci sebelum mengisi nominal.
              </p>
            </div>

            <button
              type="button"
              class="modal-close"
              :disabled="isClosing"
              @click="closeCloseModal"
            >
              ×
            </button>
          </header>

          <form
            @submit.prevent="
              submitCloseSession
            "
          >
            <div class="modal-body">
              <div class="blind-close-notice">
                Expected cash sengaja tidak
                ditampilkan sebelum sesi
                ditutup untuk menjaga
                akurasi perhitungan fisik.
              </div>

              <div class="form-group">
                <label for="actual-cash">
                  Total Uang Fisik
                </label>

                <div class="currency-input">
                  <span>Rp</span>

                  <input
                    id="actual-cash"
                    v-model="
                      closeForm
                        .actual_closing_cash
                    "
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Masukkan hasil hitung"
                  />
                </div>

                <span
                  v-if="
                    firstCloseError(
                      'actual_closing_cash',
                    )
                  "
                  class="field-error"
                >
                  {{
                    firstCloseError(
                      'actual_closing_cash',
                    )
                  }}
                </span>
              </div>

              <div class="form-group">
                <label for="closing-notes">
                  Catatan Penutupan
                </label>

                <textarea
                  id="closing-notes"
                  v-model="
                    closeForm.closing_notes
                  "
                  rows="4"
                  maxlength="1000"
                  placeholder="Opsional, jelaskan bila ada selisih atau kejadian khusus"
                />
              </div>

              <span
                v-if="
                  firstCloseError('session')
                "
                class="field-error"
              >
                {{
                  firstCloseError('session')
                }}
              </span>
            </div>

            <footer class="modal-footer">
              <button
                type="button"
                class="secondary-button"
                :disabled="isClosing"
                @click="closeCloseModal"
              >
                Batal
              </button>

              <button
                type="submit"
                class="confirm-close-button"
                :disabled="isClosing"
              >
                {{
                  isClosing
                    ? 'Menutup Sesi...'
                    : 'Tutup Sesi Kasir'
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
.session-page {
  width: 100%;
}

.page-header {
  margin-bottom: 25px;
}

.eyebrow {
  margin: 0 0 7px;
  color: #047857;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.page-header h1 {
  margin: 0;
  color: #0f172a;
  font-size: 30px;
}

.page-header p {
  max-width: 680px;
  margin: 9px 0 0;
  color: #64748b;
  line-height: 1.6;
}

.state-card,
.active-session-card,
.blocked-card,
.closing-result-card,
.information-panel,
.open-session-form {
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  background: white;
  box-shadow:
    0 14px 40px rgb(15 23 42 / 6%);
}

.alert-error {
  margin-bottom: 18px;
  padding: 14px 16px;
  border: 1px solid #fecaca;
  border-radius: 11px;
  background: #fef2f2;
  color: #b91c1c;
}

.state-card {
  padding: 70px 24px;
  color: #64748b;
  text-align: center;
}

.active-session-card,
.closing-result-card {
  padding: 26px;
}

.active-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  padding: 7px 11px;
  border-radius: 999px;

  background: #dcfce7;
  color: #15803d;

  font-size: 12px;
  font-weight: 750;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
}

.session-heading,
.result-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;

  margin-top: 22px;
  padding-bottom: 21px;
  border-bottom: 1px solid #e2e8f0;
}

.session-heading span,
.cashier-information span,
.result-heading span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.session-heading h2,
.result-heading h2 {
  margin: 5px 0 0;
  color: #0f172a;
  font-size: 23px;
}

.result-heading p {
  margin: 5px 0 0;
  color: #94a3b8;
  font-size: 12px;
}

.cashier-information {
  text-align: right;
}

.cashier-information strong {
  display: block;
  margin-top: 5px;
  color: #0f172a;
}

.session-information-grid,
.closing-summary {
  display: grid;
  grid-template-columns:
    repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-top: 20px;
}

.closing-summary {
  grid-template-columns:
    repeat(4, minmax(0, 1fr));
}

.session-information-grid article,
.closing-summary article {
  padding: 15px;
  border-radius: 12px;
  background: #f8fafc;
}

.session-information-grid span,
.session-information-grid strong,
.closing-summary span,
.closing-summary strong {
  display: block;
}

.session-information-grid span,
.closing-summary span {
  color: #64748b;
  font-size: 11px;
}

.session-information-grid strong,
.closing-summary strong {
  margin-top: 6px;
  color: #0f172a;
  font-size: 14px;
}

.open-text {
  color: #15803d !important;
}

.notes-card,
.session-notice {
  margin-top: 17px;
  padding: 14px;
  border-radius: 11px;
}

.notes-card {
  background: #f8fafc;
}

.notes-card span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
}

.notes-card p {
  margin: 6px 0 0;
  color: #334155;
  line-height: 1.6;
}

.session-notice {
  background: #eff6ff;
  color: #1e40af;
  font-size: 12px;
  line-height: 1.6;
}

.session-actions {
  display: flex;
  gap: 11px;
  margin-top: 22px;
}

.pos-button,
.close-session-button,
.open-button,
.secondary-button,
.confirm-close-button {
  min-height: 44px;
  border: 0;
  border-radius: 10px;
  font-weight: 750;
}

.pos-button {
  flex: 1;
  background: #047857;
  color: white;
}

.close-session-button {
  padding: 0 18px;
  background: #fee2e2;
  color: #b91c1c;
}

.pos-button:disabled,
.close-session-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.blocked-card {
  padding: 55px 25px;
  text-align: center;
}

.blocked-icon {
  width: 56px;
  height: 56px;

  display: grid;
  place-items: center;

  margin: 0 auto;
  border-radius: 16px;

  background: #fef3c7;
  color: #b45309;

  font-size: 24px;
  font-weight: 800;
}

.blocked-card h2 {
  margin: 17px 0 0;
  color: #0f172a;
}

.blocked-card > p {
  margin: 8px 0 20px;
  color: #64748b;
}

.blocked-information {
  display: grid;
  grid-template-columns:
    repeat(2, minmax(0, 1fr));
  gap: 12px;

  max-width: 570px;
  margin: 0 auto 20px;
}

.blocked-information div {
  padding: 14px;
  border-radius: 11px;
  background: #f8fafc;
  text-align: left;
}

.blocked-information span,
.blocked-information strong {
  display: block;
}

.blocked-information span {
  color: #64748b;
  font-size: 11px;
}

.blocked-information strong {
  margin-top: 5px;
  color: #0f172a;
}

.open-session-layout {
  display: grid;
  grid-template-columns:
    minmax(0, 1fr)
    minmax(350px, 440px);
  gap: 20px;
}

.information-panel,
.open-session-form {
  padding: 27px;
}

.information-panel {
  background:
    linear-gradient(
      145deg,
      #064e3b,
      #047857
    );
  color: white;
}

.register-icon {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  border-radius: 16px;
  background: rgb(255 255 255 / 14%);
  font-weight: 800;
}

.information-panel h2 {
  margin: 20px 0 0;
  font-size: 25px;
}

.information-panel > p {
  margin: 9px 0 0;
  color: rgb(255 255 255 / 75%);
  line-height: 1.7;
}

.workflow {
  display: grid;
  gap: 11px;
  margin-top: 27px;
}

.workflow div {
  display: flex;
  align-items: center;
  gap: 11px;
  color: rgb(255 255 255 / 88%);
  font-size: 13px;
}

.workflow span {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 8px;
  background: rgb(255 255 255 / 13%);
  font-weight: 800;
}

.open-session-form h2 {
  margin: 0;
  color: #0f172a;
  font-size: 21px;
}

.open-session-form > p {
  margin: 7px 0 22px;
  color: #64748b;
  line-height: 1.6;
}

.form-group {
  display: grid;
  gap: 7px;
  margin-bottom: 17px;
}

.form-group label {
  color: #475569;
  font-size: 13px;
  font-weight: 700;
}

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

.form-group input {
  height: 46px;
  padding: 0 12px;
}

.form-group textarea {
  padding: 12px;
  resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus {
  border-color: #059669;
  box-shadow:
    0 0 0 3px rgb(5 150 105 / 10%);
}

.currency-input {
  position: relative;
}

.currency-input span {
  position: absolute;
  top: 50%;
  left: 13px;
  z-index: 1;
  transform: translateY(-50%);
  color: #64748b;
  font-weight: 700;
}

.currency-input input {
  padding-left: 41px;
}

.field-error {
  color: #dc2626;
  font-size: 12px;
  line-height: 1.45;
}

.open-button {
  width: 100%;
  background: #047857;
  color: white;
}

.open-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.difference-badge {
  padding: 7px 11px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 750;
}

.difference-badge.balanced {
  background: #dcfce7;
  color: #15803d;
}

.difference-badge.over {
  background: #dbeafe;
  color: #1d4ed8;
}

.difference-badge.short {
  background: #fee2e2;
  color: #b91c1c;
}

.difference-result {
  margin: 18px 0;
  padding: 16px;
  border-radius: 12px;
}

.difference-result span,
.difference-result strong {
  display: block;
}

.difference-result span {
  font-size: 11px;
  font-weight: 700;
}

.difference-result strong {
  margin-top: 5px;
  font-size: 22px;
}

.difference-result.balanced {
  background: #dcfce7;
  color: #15803d;
}

.difference-result.over {
  background: #dbeafe;
  color: #1d4ed8;
}

.difference-result.short {
  background: #fee2e2;
  color: #b91c1c;
}

.secondary-button {
  padding: 0 16px;
  background: #e2e8f0;
  color: #334155;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;

  display: grid;
  place-items: center;

  padding: 20px;
  background: rgb(15 23 42 / 60%);
  backdrop-filter: blur(3px);
}

.modal-card {
  width: min(560px, 100%);
  border-radius: 18px;
  overflow: hidden;
  background: white;
  box-shadow:
    0 30px 80px rgb(15 23 42 / 30%);
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;

  padding: 21px 23px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
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
  border: 0;
  border-radius: 10px;
  background: #f1f5f9;
  color: #475569;
  font-size: 22px;
}

.modal-body {
  padding: 22px 23px;
}

.blind-close-notice {
  margin-bottom: 19px;
  padding: 13px;
  border-radius: 10px;
  background: #eff6ff;
  color: #1e40af;
  font-size: 12px;
  line-height: 1.6;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;

  padding: 17px 23px;
  border-top: 1px solid #e2e8f0;
}

.confirm-close-button {
  padding: 0 17px;
  background: #b91c1c;
  color: white;
}

.confirm-close-button:disabled,
.modal-close:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

@media (max-width: 850px) {
  .open-session-layout {
    grid-template-columns: 1fr;
  }

  .closing-summary {
    grid-template-columns:
      repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  .session-information-grid,
  .closing-summary,
  .blocked-information {
    grid-template-columns: 1fr;
  }

  .session-heading,
  .result-heading {
    flex-direction: column;
  }

  .cashier-information {
    text-align: left;
  }

  .session-actions {
    display: grid;
  }

  .close-session-button {
    min-height: 44px;
  }

  .active-session-card,
  .closing-result-card,
  .information-panel,
  .open-session-form {
    padding: 20px;
  }

  .modal-overlay {
    padding: 0;
    place-items: end stretch;
  }

  .modal-card {
    width: 100%;
    border-radius: 18px 18px 0 0;
  }

  .modal-footer {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
}
</style>