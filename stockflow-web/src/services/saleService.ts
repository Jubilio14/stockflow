import api from '@/services/api'

import type {
  CreateSalePayload,
  PaginatedPosProductsResponse,
  PosProductFilters,
  SaleMutationResponse,
  PaginatedSalesResponse,
  SaleFilters,
  SaleRecord,
  SaleResourceResponse,
} from '@/types/sale'

function buildPosProductParams(
  filters: PosProductFilters,
) {
  return {
    search:
      filters.search || undefined,

    category_id:
      filters.category_id === ''
        ? undefined
        : filters.category_id,

    page:
      filters.page,

    per_page:
      filters.per_page,
  }
}

export async function getPosProducts(
  filters: PosProductFilters,
): Promise<PaginatedPosProductsResponse> {
  const response =
    await api.get<PaginatedPosProductsResponse>(
      '/api/pos/products',
      {
        params:
          buildPosProductParams(filters),
      },
    )

  return response.data
}

export async function createSale(
  payload: CreateSalePayload,
): Promise<SaleMutationResponse> {
  const response =
    await api.post<SaleMutationResponse>(
      '/api/sales',
      payload,
    )

  return response.data
}

function buildSaleParams(
  filters: SaleFilters,
) {
  return {
    search:
      filters.search.trim() ||
      undefined,

    payment_method:
      filters.payment_method ||
      undefined,

    cash_session_id:
      filters.cash_session_id === ''
        ? undefined
        : filters.cash_session_id,

    date_from:
      filters.date_from ||
      undefined,

    date_to:
      filters.date_to ||
      undefined,

    page:
      filters.page,

    per_page:
      filters.per_page,
  }
}

export async function getSales(
  filters: SaleFilters,
): Promise<PaginatedSalesResponse> {
  const response =
    await api.get<PaginatedSalesResponse>(
      '/api/sales',
      {
        params:
          buildSaleParams(filters),
      },
    )

  return response.data
}

export async function getSale(
  saleId: number,
): Promise<SaleRecord> {
  const response =
    await api.get<SaleResourceResponse>(
      `/api/sales/${saleId}`,
    )

  return response.data.data
}