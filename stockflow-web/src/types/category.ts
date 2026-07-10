export interface CategoryItem {
  id: number
  name: string
  description: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface CategoryFilters {
  search: string
  status: '' | 'active' | 'inactive'
  page: number
  per_page: number
}

export interface CategoryPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedCategoriesResponse {
  data: CategoryItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: CategoryPaginationMeta
}

export interface CreateCategoryPayload {
  name: string
  description: string
  is_active: boolean
}

export interface UpdateCategoryPayload {
  name: string
  description: string
}

export interface CategoryMutationResponse {
  message: string
  category: CategoryItem
}

export type CategoryValidationErrors = Record<
  string,
  string[]
>