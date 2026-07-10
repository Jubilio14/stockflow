import api from '@/services/api'

import type {
  PaginatedStockMovementsResponse,
  StockMovementFilters,
} from '@/types/stockMovement'

function buildStockMovementParams(
  filters: StockMovementFilters,
) {
  return {
    search:
      filters.search || undefined,

    type:
      filters.type || undefined,

    product_id:
      filters.product_id === ''
        ? undefined
        : filters.product_id,

    date_from:
      filters.date_from || undefined,

    date_to:
      filters.date_to || undefined,

    page:
      filters.page,

    per_page:
      filters.per_page,
  }
}

export async function getStockMovements(
  filters: StockMovementFilters,
): Promise<PaginatedStockMovementsResponse> {
  const response =
    await api.get<PaginatedStockMovementsResponse>(
      '/api/stock-movements',
      {
        params:
          buildStockMovementParams(filters),
      },
    )

  return response.data
}