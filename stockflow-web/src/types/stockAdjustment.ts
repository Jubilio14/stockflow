export type StockAdjustmentReason =
  | 'stock_opname'
  | 'damaged'
  | 'lost'
  | 'expired'
  | 'correction'
  | 'other'

export interface StockAdjustmentCreator {
  id: number
  name: string
}

export interface StockAdjustmentProduct {
  id: number
  name: string
  sku: string
  unit: string
  image_url: string | null
}

export interface StockAdjustmentItem {
  id: number
  product_id: number
  product: StockAdjustmentProduct

  system_stock: number
  actual_stock: number
  quantity_change: number

  average_cost_before: number
  average_cost_after: number

  inventory_value_change: number
}

export interface StockAdjustmentRecord {
  id: number
  adjustment_number: string
  adjustment_date: string
  reason: StockAdjustmentReason
  status: string
  notes: string | null

  creator: StockAdjustmentCreator

  items_count: number
  items?: StockAdjustmentItem[]

  created_at: string | null
  updated_at: string | null
}

export interface StockAdjustmentItemPayload {
  product_id: number
  actual_stock: number
}

export interface CreateStockAdjustmentPayload {
  adjustment_date: string
  reason: StockAdjustmentReason
  notes: string | null
  items: StockAdjustmentItemPayload[]
}

export interface StockAdjustmentFilters {
  search: string

  reason:
    | ''
    | StockAdjustmentReason

  date_from: string
  date_to: string

  page: number
  per_page: number
}

export interface StockAdjustmentPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedStockAdjustmentsResponse {
  data: StockAdjustmentRecord[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: StockAdjustmentPaginationMeta
}

export interface StockAdjustmentMutationResponse {
  message: string
  adjustment: StockAdjustmentRecord
}

export interface SingleStockAdjustmentResponse {
  data: StockAdjustmentRecord
}

export type StockAdjustmentValidationErrors =
  Record<string, string[]>