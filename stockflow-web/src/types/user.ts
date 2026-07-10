import type { UserRole } from '@/types/auth'

export interface UserItem {
  id: number
  name: string
  email: string
  role: UserRole
  is_active: boolean
  created_at: string | null
}

export interface UserFilters {
  search: string
  role: '' | UserRole
  status: '' | 'active' | 'inactive'
  page: number
  per_page: number
}

export interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

export interface PaginatedUsersResponse {
  data: UserItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: {
    current_page: number
    from: number | null
    last_page: number
    links: PaginationLink[]
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

export type StaffRole = 'admin' | 'cashier'

export interface CreateUserPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  role: StaffRole
  is_active: boolean
}

export interface CreateUserResponse {
  message: string
  user: UserItem
}

export type UserValidationErrors = Record<string, string[]>