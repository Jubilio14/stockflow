import api from '@/services/api'

import type {
  CreateSalePayload,
  PaginatedPosProductsResponse,
  PosProductFilters,
  SaleMutationResponse,
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