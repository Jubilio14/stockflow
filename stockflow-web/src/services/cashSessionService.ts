import api from '@/services/api'

import type {
  CashSessionFilters,
  CashSessionItem,
  CashSessionMutationResponse,
  CashSessionResourceResponse,
  CloseCashSessionPayload,
  CurrentCashSessionResponse,
  OpenCashSessionPayload,
  PaginatedCashSessionsResponse,
} from '@/types/cashSession'

export async function getCurrentCashSession():
  Promise<CurrentCashSessionResponse> {
  const response =
    await api.get<CurrentCashSessionResponse>(
      '/api/cash-sessions/current',
    )

  return response.data
}

export async function openCashSession(
  payload: OpenCashSessionPayload,
): Promise<CashSessionMutationResponse> {
  const response =
    await api.post<CashSessionMutationResponse>(
      '/api/cash-sessions/open',
      payload,
    )

  return response.data
}

export async function closeCashSession(
  sessionId: number,
  payload: CloseCashSessionPayload,
): Promise<CashSessionMutationResponse> {
  const response =
    await api.post<CashSessionMutationResponse>(
      `/api/cash-sessions/${sessionId}/close`,
      payload,
    )

  return response.data
}

function buildCashSessionParams(
  filters: CashSessionFilters,
) {
  return {
    search:
      filters.search.trim() ||
      undefined,

    status:
      filters.status ||
      undefined,

    difference_status:
      filters.difference_status ||
      undefined,

    date_from:
      filters.date_from ||
      undefined,

    date_to:
      filters.date_to ||
      undefined,

    page:
      filters.page,

    per_page:
      filters.per_page,
  }
}

export async function getCashSessions(
  filters: CashSessionFilters,
): Promise<PaginatedCashSessionsResponse> {
  const response =
    await api.get<PaginatedCashSessionsResponse>(
      '/api/cash-sessions',
      {
        params:
          buildCashSessionParams(
            filters,
          ),
      },
    )

  return response.data
}

export async function getCashSession(
  cashSessionId: number,
): Promise<CashSessionItem> {
  const response =
    await api.get<CashSessionResourceResponse>(
      `/api/cash-sessions/${cashSessionId}`,
    )

  return response.data.data
}