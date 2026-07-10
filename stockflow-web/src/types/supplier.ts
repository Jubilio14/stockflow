export interface SupplierItem {
  id: number
  code: string
  name: string
  contact_person: string | null
  phone: string | null
  email: string | null
  address: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface SupplierFilters {
  search: string
  status: '' | 'active' | 'inactive'
  page: number
  per_page: number
}

export interface SupplierPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedSuppliersResponse {
  data: SupplierItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: SupplierPaginationMeta
}

export interface SupplierMutationResponse {
  message: string
  supplier: SupplierItem
}

export type SupplierValidationErrors = Record<
  string,
  string[]
>