export type UserRole = 'owner' | 'admin' | 'cashier'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: UserRole
  is_active: boolean
}

export interface LoginPayload {
  email: string
  password: string
  remember: boolean
}

export interface LoginResponse {
  message: string
  user: AuthUser
}

export interface AuthUserResponse {
  user: AuthUser
}