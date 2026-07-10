export interface PurchaseSupplier {
  id: number
  code: string
  name: string
}

export interface PurchaseCreator {
  id: number
  name: string
}

export interface PurchaseItemProduct {
  id: number
  name: string
  sku: string
  unit: string
  image_url: string | null
}

export interface PurchaseItem {
  id: number
  product_id: number
  product: PurchaseItemProduct
  quantity: number
  unit_cost: number
  subtotal: number
  stock_before: number
  stock_after: number
  average_cost_before: number
  average_cost_after: number
}

export interface PurchaseItemPayload {
  product_id: number
  quantity: number
  unit_cost: number
}

export interface CreatePurchasePayload {
  supplier_id: number
  invoice_number: string | null
  purchase_date: string
  notes: string | null
  items: PurchaseItemPayload[]
}

export interface PurchaseItemRecord {
  id: number
  purchase_number: string
  invoice_number: string | null
  purchase_date: string
  total_amount: number
  status: string
  notes: string | null
  supplier: PurchaseSupplier
  creator: PurchaseCreator
  items_count: number
  items?: PurchaseItem[]
  created_at: string | null
  updated_at: string | null
}

export interface PurchaseFilters {
  search: string
  supplier_id: number | ''
  date_from: string
  date_to: string
  page: number
  per_page: number
}

export interface PurchasePaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedPurchasesResponse {
  data: PurchaseItemRecord[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: PurchasePaginationMeta
}

export interface PurchaseMutationResponse {
  message: string
  purchase: PurchaseItemRecord
}

export interface SinglePurchaseResponse {
  data: PurchaseItemRecord
}

export type PurchaseValidationErrors = Record<
  string,
  string[]
>