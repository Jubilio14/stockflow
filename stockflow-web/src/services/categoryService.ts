import api from '@/services/api'

import type {
  CategoryFilters,
  CategoryMutationResponse,
  CreateCategoryPayload,
  PaginatedCategoriesResponse,
  UpdateCategoryPayload,
} from '@/types/category'

function buildCategoryParams(
  filters: CategoryFilters,
) {
  return {
    search: filters.search || undefined,
    status: filters.status || undefined,
    page: filters.page,
    per_page: filters.per_page,
  }
}

export async function getCategories(
  filters: CategoryFilters,
): Promise<PaginatedCategoriesResponse> {
  const response =
    await api.get<PaginatedCategoriesResponse>(
      '/api/categories',
      {
        params: buildCategoryParams(filters),
      },
    )

  return response.data
}

export async function createCategory(
  payload: CreateCategoryPayload,
): Promise<CategoryMutationResponse> {
  const response =
    await api.post<CategoryMutationResponse>(
      '/api/categories',
      payload,
    )

  return response.data
}

export async function updateCategory(
  categoryId: number,
  payload: UpdateCategoryPayload,
): Promise<CategoryMutationResponse> {
  const response =
    await api.put<CategoryMutationResponse>(
      `/api/categories/${categoryId}`,
      payload,
    )

  return response.data
}

export async function toggleCategoryStatus(
  categoryId: number,
): Promise<CategoryMutationResponse> {
  const response =
    await api.patch<CategoryMutationResponse>(
      `/api/categories/${categoryId}/toggle-status`,
    )

  return response.data
}