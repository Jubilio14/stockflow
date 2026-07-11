export type SalesReportPaymentMethod =
  | ''
  | 'cash'
  | 'qris'
  | 'transfer'
  | 'debit'

export interface SalesReportFilters {
  date_from: string
  date_to: string
  cashier_id: number | ''
  payment_method:
    SalesReportPaymentMethod
}

export interface AppliedSalesReportFilters {
  date_from: string
  date_to: string
  cashier_id: number | null
  payment_method: string | null
  total_days: number
}

export interface SalesReportSummary {
  total_transactions: number
  total_items_sold: number

  total_subtotal: number
  total_discount: number
  total_revenue: number

  total_cost: number
  gross_profit: number

  average_transaction: number
  gross_margin_percentage: number
}

export interface DailySalesReportRow {
  date: string

  total_transactions: number
  total_items_sold: number

  total_subtotal: number
  total_discount: number
  total_revenue: number

  total_cost: number
  gross_profit: number

  average_transaction: number
  gross_margin_percentage: number
}

export interface SalesReportResponse {
  filters: AppliedSalesReportFilters
  summary: SalesReportSummary
  daily: DailySalesReportRow[]
}

export interface ReportCashierOption {
  id: number
  name: string
}

export interface CashierOptionsResponse {
  data: ReportCashierOption[]
}