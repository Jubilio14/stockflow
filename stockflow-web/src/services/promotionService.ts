import api from '@/services/api'

import type {
  AvailablePromotionsResponse,
  PaginatedPromotionsResponse,
  PromotionFilters,
  PromotionItem,
  PromotionMutationResponse,
  SavePromotionPayload,
} from '@/types/promotion'

function buildPromotionParams(
  filters: PromotionFilters,
) {
  return {
    search:
      filters.search || undefined,

    discount_type:
      filters.discount_type || undefined,

    status:
      filters.status || undefined,

    page:
      filters.page,

    per_page:
      filters.per_page,
  }
}

export async function getPromotions(
  filters: PromotionFilters,
): Promise<PaginatedPromotionsResponse> {
  const response =
    await api.get<PaginatedPromotionsResponse>(
      '/api/promotions',
      {
        params:
          buildPromotionParams(filters),
      },
    )

  return response.data
}

export async function createPromotion(
  payload: SavePromotionPayload,
): Promise<PromotionMutationResponse> {
  const response =
    await api.post<PromotionMutationResponse>(
      '/api/promotions',
      payload,
    )

  return response.data
}

export async function updatePromotion(
  promotionId: number,
  payload: SavePromotionPayload,
): Promise<PromotionMutationResponse> {
  const response =
    await api.put<PromotionMutationResponse>(
      `/api/promotions/${promotionId}`,
      payload,
    )

  return response.data
}

export async function togglePromotionStatus(
  promotionId: number,
): Promise<PromotionMutationResponse> {
  const response =
    await api.patch<PromotionMutationResponse>(
      `/api/promotions/${promotionId}/toggle-status`,
    )

  return response.data
}

export async function getAvailablePromotions():
  Promise<PromotionItem[]> {
  const response =
    await api.get<AvailablePromotionsResponse>(
      '/api/promotions/available',
    )

  return response.data.data
}