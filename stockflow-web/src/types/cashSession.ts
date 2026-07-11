export type CashSessionStatus =
  | 'open'
  | 'closed'

export type CashDifferenceStatus =
  | 'balanced'
  | 'over'
  | 'short'
  | null

export interface CashSessionUser {
  id: number
  name: string
}

export interface CashSessionItem {
  id: number
  session_number: string

  cashier: CashSessionUser
  closer: CashSessionUser | null

  opened_at: string
  closed_at: string | null

  duration_minutes: number | null

  opening_cash: number

  cash_sales_total: number | null
  expected_cash_now: number | null

  expected_closing_cash: number | null
  actual_closing_cash: number | null
  difference: number | null

  difference_status:
    CashDifferenceStatus

  status: CashSessionStatus

  sales_count?: number

  opening_notes: string | null
  closing_notes: string | null

  is_owned_by_current_user: boolean
  can_use_pos: boolean
  can_close: boolean

  created_at: string | null
  updated_at: string | null
}

export interface CurrentCashSessionResponse {
  session: CashSessionItem | null
  register_in_use: boolean
  message: string
}

export interface OpenCashSessionPayload {
  opening_cash: number
  opening_notes: string | null
}

export interface CloseCashSessionPayload {
  actual_closing_cash: number
  closing_notes: string | null
}

export interface CashSessionMutationResponse {
  message: string
  session: CashSessionItem
}

export interface CashSessionFilters {
  search: string

  status:
    | ''
    | 'open'
    | 'closed'

  difference_status:
    | ''
    | 'balanced'
    | 'over'
    | 'short'

  date_from: string
  date_to: string

  page: number
  per_page: number
}

export interface CashSessionPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedCashSessionsResponse {
  data: CashSessionItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: CashSessionPaginationMeta
}

export interface CashSessionResourceResponse {
  data: CashSessionItem
}

export interface CashSessionValidationErrors {
  [field: string]: string[]
}