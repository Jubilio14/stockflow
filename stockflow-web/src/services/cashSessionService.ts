import api from '@/services/api'

import type {
  CashSessionMutationResponse,
  CloseCashSessionPayload,
  CurrentCashSessionResponse,
  OpenCashSessionPayload,
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