import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '@/api/auth'
import { ApiError } from '@/api/errors'
import { clearToken, getToken, setToken } from '@/api/token'
import type { User } from '@/types/api'
import { useAuthStore } from '../auth'

vi.mock('@/api/auth')

const user: User = { id: 1, email: 'a@example.com', created_at: 1, updated_at: 1 }

describe('auth store', () => {
  beforeEach(() => {
    clearToken()
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('вход сохраняет токен и пользователя', async () => {
    vi.mocked(authApi.login).mockResolvedValue({ user, access_token: 'tok' })

    const store = useAuthStore()
    await store.login({ email: 'a@example.com', password: 'secret123' })

    expect(store.isAuthenticated).toBe(true)
    expect(store.user).toEqual(user)
    expect(getToken()).toBe('tok')
  })

  it('ошибка входа не меняет состояние и пробрасывается', async () => {
    vi.mocked(authApi.login).mockRejectedValue(new ApiError(401, 'Неверный email или пароль.'))

    const store = useAuthStore()

    await expect(store.login({ email: 'a@example.com', password: 'x' })).rejects.toMatchObject({
      status: 401,
    })
    expect(store.isAuthenticated).toBe(false)
    expect(getToken()).toBeNull()
  })

  it('регистрация сразу открывает сессию', async () => {
    vi.mocked(authApi.register).mockResolvedValue({ user, access_token: 'new' })

    const store = useAuthStore()
    await store.register({ email: 'a@example.com', password: 'secret123' })

    expect(store.isAuthenticated).toBe(true)
    expect(getToken()).toBe('new')
  })

  it('при старте берёт токен из хранилища и восстанавливает пользователя', async () => {
    setToken('saved')
    vi.mocked(authApi.fetchMe).mockResolvedValue(user)

    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(true)
    expect(store.user).toBeNull()

    await store.restore()
    expect(store.user).toEqual(user)
  })

  it('restore не делает запрос повторно, если пользователь уже загружен', async () => {
    setToken('saved')
    vi.mocked(authApi.fetchMe).mockResolvedValue(user)

    const store = useAuthStore()
    await store.restore()
    await store.restore()

    expect(authApi.fetchMe).toHaveBeenCalledOnce()
  })

  it('restore сбрасывает сессию, если токен недействителен', async () => {
    setToken('stale')
    vi.mocked(authApi.fetchMe).mockRejectedValue(new ApiError(401, 'Требуется авторизация.'))

    const store = useAuthStore()
    await store.restore()

    expect(store.isAuthenticated).toBe(false)
    expect(getToken()).toBeNull()
  })

  it('restore сохраняет токен, если сервер недоступен', async () => {
    setToken('saved')
    vi.mocked(authApi.fetchMe).mockRejectedValue(new ApiError(0, 'Нет связи'))

    const store = useAuthStore()
    await store.restore()

    expect(store.isAuthenticated).toBe(true)
    expect(getToken()).toBe('saved')
  })

  it('выход очищает сессию, даже если сервер недоступен', async () => {
    setToken('saved')
    vi.mocked(authApi.logout).mockRejectedValue(new ApiError(0, 'Нет связи'))

    const store = useAuthStore()
    await store.logout()

    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
    expect(getToken()).toBeNull()
  })
})
