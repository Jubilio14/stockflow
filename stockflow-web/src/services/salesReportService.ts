import api from '@/services/api'

import type {
  CashierOptionsResponse,
  ReportCashierOption,
  SalesReportFilters,
  SalesReportResponse,
} from '@/types/salesReport'

function buildReportParams(
  filters: SalesReportFilters,
) {
  return {
    date_from:
      filters.date_from,

    date_to:
      filters.date_to,

    cashier_id:
      filters.cashier_id === ''
        ? undefined
        : filters.cashier_id,

    payment_method:
      filters.payment_method ||
      undefined,
  }
}

export async function getSalesReport(
  filters: SalesReportFilters,
): Promise<SalesReportResponse> {
  const response =
    await api.get<SalesReportResponse>(
      '/api/reports/sales',
      {
        params:
          buildReportParams(filters),
      },
    )

  return response.data
}

/*
|--------------------------------------------------------------------------
| Pilihan kasir
|--------------------------------------------------------------------------
|
| Menggunakan endpoint user management yang sudah ada.
| Hanya mengambil user role cashier.
|
*/

export async function getReportCashiers():
  Promise<ReportCashierOption[]> {
  const response =
    await api.get<CashierOptionsResponse>(
      '/api/users',
      {
        params: {
          role: 'cashier',
          per_page: 100,
        },
      },
    )

  return response.data.data.map(
    (cashier) => ({
      id: cashier.id,
      name: cashier.name,
    }),
  )
}