import axios from 'axios'
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import api from '@/services/api'

import type {
  AuthUser,
  AuthUserResponse,
  LoginPayload,
  LoginResponse,
} from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const isLoading = ref(false)
  const isInitialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  async function login(payload: LoginPayload): Promise<string> {
    isLoading.value = true

    try {
      await api.get('/sanctum/csrf-cookie')

      const response = await api.post<LoginResponse>(
        '/login',
        payload,
      )

      user.value = response.data.user

      return response.data.message
    } finally {
      isLoading.value = false
    }
  }

  async function fetchUser(): Promise<void> {
    try {
      const response =
        await api.get<AuthUserResponse>('/api/user')

      user.value = response.data.user
    } catch (error: unknown) {
      if (
        axios.isAxiosError(error) &&
        error.response?.status === 401
      ) {
        user.value = null
        return
      }

      throw error
    } finally {
      isInitialized.value = true
    }
  }

  async function logout(): Promise<void> {
    isLoading.value = true

    try {
      await api.post('/logout')
      user.value = null
    } finally {
      isLoading.value = false
    }
  }

  return {
    user,
    isLoading,
    isInitialized,
    isAuthenticated,
    login,
    fetchUser,
    logout,
  }
})