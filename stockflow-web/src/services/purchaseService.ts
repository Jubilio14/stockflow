import api from '@/services/api'

import type {
  CreatePurchasePayload,
  PaginatedPurchasesResponse,
  PurchaseFilters,
  PurchaseItemRecord,
  PurchaseMutationResponse,
  SinglePurchaseResponse,
} from '@/types/purchase'

function buildPurchaseParams(
  filters: PurchaseFilters,
) {
  return {
    search: filters.search || undefined,

    supplier_id:
      filters.supplier_id === ''
        ? undefined
        : filters.supplier_id,

    date_from:
      filters.date_from || undefined,

    date_to:
      filters.date_to || undefined,

    page: filters.page,
    per_page: filters.per_page,
  }
}

export async function getPurchases(
  filters: PurchaseFilters,
): Promise<PaginatedPurchasesResponse> {
  const response =
    await api.get<PaginatedPurchasesResponse>(
      '/api/purchases',
      {
        params: buildPurchaseParams(filters),
      },
    )

  return response.data
}

export async function createPurchase(
  payload: CreatePurchasePayload,
): Promise<PurchaseMutationResponse> {
  const response =
    await api.post<PurchaseMutationResponse>(
      '/api/purchases',
      payload,
    )

  return response.data
}

export async function getPurchase(
  purchaseId: number,
): Promise<PurchaseItemRecord> {
  const response =
    await api.get<SinglePurchaseResponse>(
      `/api/purchases/${purchaseId}`,
    )

  return response.data.data
}