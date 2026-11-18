import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as authApi from '@/api/auth'
import { toApiError } from '@/api/errors'
import { clearToken, getToken, setToken } from '@/api/token'
import type { Credentials, User } from '@/types/api'
import { useAccountsStore } from './accounts'
import { useBudgetsStore } from './budgets'
import { useCategoriesStore } from './categories'
import { useDashboardStore } from './dashboard'
import { useTransactionsStore } from './transactions'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getToken())
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => token.value !== null)

  function applySession(session: { user: User; access_token: string }) {
    token.value = session.access_token
    user.value = session.user
    setToken(session.access_token)
  }

  /** Сбрасывает локальное состояние без запроса к серверу (выход, истёкший токен). */
  function reset() {
    token.value = null
    user.value = null
    clearToken()

    // Данные прежнего пользователя не должны быть видны следующему
    useAccountsStore().reset()
    useCategoriesStore().reset()
    useBudgetsStore().reset()
    useTransactionsStore().reset()
    useDashboardStore().reset()
  }

  async function login(credentials: Credentials) {
    applySession(await authApi.login(credentials))
  }

  async function register(credentials: Credentials) {
    applySession(await authApi.register(credentials))
  }

  /**
   * Подтягивает пользователя по сохранённому токену. Если токен недействителен, сессия сбрасывается.
   * Сеть недоступна: токен остаётся, пользователь загрузится при следующей попытке.
   */
  async function restore(): Promise<void> {
    if (token.value === null || user.value !== null) {
      return
    }

    try {
      user.value = await authApi.fetchMe()
    } catch (error) {
      if (toApiError(error).status === 401) {
        reset()
      }
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch {
      // Токен всё равно забываем локально, даже если сервер недоступен
    } finally {
      reset()
    }
  }

  return { token, user, isAuthenticated, login, register, restore, logout, reset }
})
