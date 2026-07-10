import type { CategoryItem } from '@/types/category'

export type ProductStockStatus =
  | 'available'
  | 'low_stock'
  | 'out_of_stock'

export interface ProductItem {
  id: number
  category_id: number
  category: CategoryItem
  name: string
  sku: string
  barcode: string | null
  unit: string
  selling_price: number
  average_cost: number
  current_stock: number
  minimum_stock: number
  stock_status: ProductStockStatus
  image_url: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface ProductFilters {
  search: string
  category_id: number | ''
  status: '' | 'active' | 'inactive'
  stock_status: '' | ProductStockStatus
  page: number
  per_page: number
}

export interface ProductPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedProductsResponse {
  data: ProductItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: ProductPaginationMeta
}

export interface ProductMutationResponse {
  message: string
  product: ProductItem
}

export type ProductValidationErrors = Record<
  string,
  string[]
>