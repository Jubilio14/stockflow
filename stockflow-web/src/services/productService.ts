import api from '@/services/api'

import type {
  PaginatedProductsResponse,
  ProductFilters,
  ProductMutationResponse,
} from '@/types/product'

function buildProductParams(filters: ProductFilters) {
  return {
    search: filters.search || undefined,

    category_id:
      filters.category_id === ''
        ? undefined
        : filters.category_id,

    status: filters.status || undefined,

    stock_status:
      filters.stock_status || undefined,

    page: filters.page,
    per_page: filters.per_page,
  }
}

export async function getProducts(
  filters: ProductFilters,
): Promise<PaginatedProductsResponse> {
  const response =
    await api.get<PaginatedProductsResponse>(
      '/api/products',
      {
        params: buildProductParams(filters),
      },
    )

  return response.data
}

export async function createProduct(
  formData: FormData,
): Promise<ProductMutationResponse> {
  const response =
    await api.post<ProductMutationResponse>(
      '/api/products',
      formData,
    )

  return response.data
}

export async function updateProduct(
  productId: number,
  formData: FormData,
): Promise<ProductMutationResponse> {
  formData.append('_method', 'PUT')

  const response =
    await api.post<ProductMutationResponse>(
      `/api/products/${productId}`,
      formData,
    )

  return response.data
}

export async function toggleProductStatus(
  productId: number,
): Promise<ProductMutationResponse> {
  const response =
    await api.patch<ProductMutationResponse>(
      `/api/products/${productId}/toggle-status`,
    )

  return response.data
}