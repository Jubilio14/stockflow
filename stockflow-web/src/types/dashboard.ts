export type DashboardDifferenceStatus =
  | 'balanced'
  | 'over'
  | 'short'
  | null

export interface DashboardTodaySummary {
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

export interface DashboardProductCategory {
  id: number
  name: string
}

export interface DashboardLowStockProduct {
  id: number
  name: string
  sku: string
  unit: string

  current_stock: number
  minimum_stock: number
  stock_shortage: number

  category:
    DashboardProductCategory | null

  image_url: string | null
}

export interface DashboardInventory {
  low_stock_count: number

  low_stock_products:
    DashboardLowStockProduct[]
}

export interface DashboardUserSummary {
  id: number
  name: string
}

export interface DashboardActiveCashSession {
  id: number
  session_number: string

  cashier: DashboardUserSummary

  opened_at: string
  duration_minutes: number

  opening_cash: number
  cash_sales_total: number
  expected_cash_now: number

  sales_count: number
}

export interface DashboardLatestCashDifference {
  id: number
  session_number: string

  cashier: DashboardUserSummary

  closer:
    DashboardUserSummary | null

  closed_at: string

  opening_cash: number
  cash_sales_total: number

  expected_closing_cash: number | null
  actual_closing_cash: number | null
  difference: number | null

  difference_status:
    DashboardDifferenceStatus

  sales_count: number
}

export interface DashboardRecentSale {
  id: number
  sale_number: string
  sold_at: string

  cashier: DashboardUserSummary

  cash_session: {
    id: number
    session_number: string
  }

  items_count: number
  total_items: number

  subtotal: number
  discount_amount: number
  total_amount: number
  gross_profit: number

  payment_method: string
}

export interface DashboardOverviewResponse {
  generated_at: string

  today: DashboardTodaySummary

  inventory: DashboardInventory

  active_cash_session:
    DashboardActiveCashSession | null

  latest_cash_difference:
    DashboardLatestCashDifference | null

  recent_sales: DashboardRecentSale[]
}