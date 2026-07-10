import api from '@/services/api'

import type {
  PaginatedUsersResponse,
  UserFilters,
} from '@/types/user'

function buildUserParams(filters: UserFilters) {
  return {
    search: filters.search || undefined,
    role: filters.role || undefined,
    status: filters.status || undefined,
    page: filters.page,
    per_page: filters.per_page,
  }
}

export async function getUsers(
  filters: UserFilters,
): Promise<PaginatedUsersResponse> {
  const response = await api.get<PaginatedUsersResponse>(
    '/api/users',
    {
      params: buildUserParams(filters),
    },
  )

  return response.data
}