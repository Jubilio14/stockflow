export type StockMovementType =
  | 'purchase'
  | 'sale'
  | 'adjustment'

export interface StockMovementProduct {
  id: number
  name: string
  sku: string
  unit: string
  image_url: string | null
}

export interface StockMovementCreator {
  id: number
  name: string
}

export interface StockMovementItem {
  id: number
  type: StockMovementType

  reference_type: string | null
  reference_id: number | null

  quantity_change: number
  stock_before: number
  stock_after: number

  unit_cost: number | null

  average_cost_before: number
  average_cost_after: number

  notes: string | null
  movement_at: string

  product: StockMovementProduct
  creator: StockMovementCreator

  created_at: string | null
}

export interface StockMovementFilters {
  search: string

  type:
    | ''
    | StockMovementType

  product_id: number | ''

  date_from: string
  date_to: string

  page: number
  per_page: number
}

export interface StockMovementPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedStockMovementsResponse {
  data: StockMovementItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: StockMovementPaginationMeta
}

export type StockMovementValidationErrors =
  Record<string, string[]>