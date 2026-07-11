import api from '@/services/api'

import type {
  CreateStockAdjustmentPayload,
  PaginatedStockAdjustmentsResponse,
  SingleStockAdjustmentResponse,
  StockAdjustmentFilters,
  StockAdjustmentMutationResponse,
  StockAdjustmentRecord,
} from '@/types/stockAdjustment'

function buildStockAdjustmentParams(
  filters: StockAdjustmentFilters,
) {
  return {
    search:
      filters.search || undefined,

    reason:
      filters.reason || undefined,

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

export async function getStockAdjustments(
  filters: StockAdjustmentFilters,
): Promise<PaginatedStockAdjustmentsResponse> {
  const response =
    await api.get<PaginatedStockAdjustmentsResponse>(
      '/api/stock-adjustments',
      {
        params:
          buildStockAdjustmentParams(filters),
      },
    )

  return response.data
}

export async function createStockAdjustment(
  payload: CreateStockAdjustmentPayload,
): Promise<StockAdjustmentMutationResponse> {
  const response =
    await api.post<StockAdjustmentMutationResponse>(
      '/api/stock-adjustments',
      payload,
    )

  return response.data
}

export async function getStockAdjustment(
  adjustmentId: number,
): Promise<StockAdjustmentRecord> {
  const response =
    await api.get<SingleStockAdjustmentResponse>(
      `/api/stock-adjustments/${adjustmentId}`,
    )

  return response.data.data
}