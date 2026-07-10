import api from '@/services/api'

import type {
  PaginatedSuppliersResponse,
  SupplierFilters,
  SupplierMutationResponse,
} from '@/types/supplier'

function buildSupplierParams(
  filters: SupplierFilters,
) {
  return {
    search: filters.search || undefined,
    status: filters.status || undefined,
    page: filters.page,
    per_page: filters.per_page,
  }
}

export async function getSuppliers(
  filters: SupplierFilters,
): Promise<PaginatedSuppliersResponse> {
  const response =
    await api.get<PaginatedSuppliersResponse>(
      '/api/suppliers',
      {
        params: buildSupplierParams(filters),
      },
    )

  return response.data
}

export async function createSupplier(
  payload: {
    code: string
    name: string
    contact_person: string | null
    phone: string | null
    email: string | null
    address: string | null
    is_active: boolean
  },
): Promise<SupplierMutationResponse> {
  const response =
    await api.post<SupplierMutationResponse>(
      '/api/suppliers',
      payload,
    )

  return response.data
}

export async function updateSupplier(
  supplierId: number,
  payload: {
    code: string
    name: string
    contact_person: string | null
    phone: string | null
    email: string | null
    address: string | null
  },
): Promise<SupplierMutationResponse> {
  const response =
    await api.put<SupplierMutationResponse>(
      `/api/suppliers/${supplierId}`,
      payload,
    )

  return response.data
}

export async function toggleSupplierStatus(
  supplierId: number,
): Promise<SupplierMutationResponse> {
  const response =
    await api.patch<SupplierMutationResponse>(
      `/api/suppliers/${supplierId}/toggle-status`,
    )

  return response.data
}