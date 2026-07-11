export interface PosProductCategory {
  id: number
  name: string
}

export interface PosProduct {
  id: number
  name: string
  sku: string
  barcode: string | null
  unit: string

  selling_price: number
  current_stock: number

  image_url: string | null
  category: PosProductCategory
}

export interface PosProductFilters {
  search: string
  category_id: number | ''
  page: number
  per_page: number
}

export interface PosProductPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedPosProductsResponse {
  data: PosProduct[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: PosProductPaginationMeta
}

export interface SaleItemPayload {
  product_id: number
  quantity: number
}

export interface CreateSalePayload {
  promotion_id: number | null
  payment_method: 'cash'
  amount_paid: number
  notes: string | null
  items: SaleItemPayload[]
}

export interface SaleCashSession {
  id: number
  session_number: string
}

export interface SaleCashier {
  id: number
  name: string
}

export interface SalePromotion {
  id: number | null
  name: string
  code: string
  discount_type:
    | 'percentage'
    | 'fixed'
  discount_value: number
}

export interface SaleItemProduct {
  id: number
  name: string
  sku: string
  unit: string
  image_url: string | null
}

export interface SaleItemRecord {
  id: number
  product_id: number
  product: SaleItemProduct

  quantity: number
  selling_price: number

  subtotal: number
  allocated_discount: number
  net_sales: number

  average_cost?: number
  cost_total?: number
  gross_profit?: number

  stock_before: number
  stock_after: number
}

export interface SaleRecord {
  id: number
  sale_number: string

  cash_session: SaleCashSession
  cashier: SaleCashier

  promotion: SalePromotion | null

  subtotal: number
  discount_amount: number
  total_amount: number

  total_cost?: number
  gross_profit?: number

  payment_method: string
  amount_paid: number
  change_amount: number

  status: string
  sold_at: string
  notes: string | null

  items_count?: number
  items?: SaleItemRecord[]

  created_at: string | null
}

export interface SaleMutationResponse {
  message: string
  sale: SaleRecord
}

export type SaleValidationErrors =
  Record<string, string[]>