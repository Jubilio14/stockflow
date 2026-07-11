import api from '@/services/api'

import type {
  DashboardOverviewResponse,
} from '@/types/dashboard'

export async function getDashboardOverview():
  Promise<DashboardOverviewResponse> {
  const response =
    await api.get<DashboardOverviewResponse>(
      '/api/dashboard/overview',
    )

  return response.data
}