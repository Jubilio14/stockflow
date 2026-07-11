export type CashSessionStatus =
  | 'open'
  | 'closed'

export type CashDifferenceStatus =
  | 'balanced'
  | 'over'
  | 'short'
  | null

export interface CashSessionCashier {
  id: number
  name: string
}

export interface CashSessionItem {
  id: number
  session_number: string

  cashier: CashSessionCashier

  opened_at: string
  opening_cash: number
  cash_sales_total: number | null
expected_cash_now: number | null

  closed_at: string | null

  expected_closing_cash: number | null
  actual_closing_cash: number | null
  difference: number | null

  difference_status:
    CashDifferenceStatus

  status: CashSessionStatus

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

export interface CashSessionValidationErrors {
  [field: string]: string[]
}