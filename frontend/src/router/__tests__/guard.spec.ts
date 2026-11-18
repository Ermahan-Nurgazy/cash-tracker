import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as authApi from '@/api/auth'
import { ApiError } from '@/api/errors'
import { clearToken, setToken } from '@/api/token'
import router, { safeRedirect } from '@/router'

vi.mock('@/api/auth')

/** Токен читается при создании store, поэтому store пересоздаётся после записи токена. */
function signIn(token: string) {
  setToken(token)
  setActivePinia(createPinia())
}

describe('guard роутера', () => {
  beforeEach(async () => {
    clearToken()
    setActivePinia(createPinia())
    vi.resetAllMocks()
    // Начинаем с нейтральной точки, иначе предыдущий переход влияет на следующий
    await router.replace('/login').catch(() => undefined)
  })

  it('гостя с закрытой страницы отправляет на вход', async () => {
    await router.push('/')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('запоминает, куда вернуться после входа', async () => {
    router.addRoute({
      path: '/secret',
      name: 'secret',
      component: { template: '<div />' },
      meta: { requiresAuth: true },
    })

    await router.push('/secret?tab=1')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/secret?tab=1')
  })

  it('авторизованного пользователя пускает на закрытую страницу', async () => {
    signIn('tok')
    vi.mocked(authApi.fetchMe).mockResolvedValue({
      id: 1,
      email: 'a@example.com',
      created_at: 1,
      updated_at: 1,
    })

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('dashboard')
  })

  it('авторизованного с недействительным токеном отправляет на вход', async () => {
    signIn('stale')
    vi.mocked(authApi.fetchMe).mockRejectedValue(new ApiError(401, 'Требуется авторизация.'))

    await router.push('/')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('авторизованного со страницы входа возвращает на главную', async () => {
    signIn('tok')
    vi.mocked(authApi.fetchMe).mockResolvedValue({
      id: 1,
      email: 'a@example.com',
      created_at: 1,
      updated_at: 1,
    })

    await router.push('/register')

    expect(router.currentRoute.value.name).toBe('dashboard')
  })
})

describe('safeRedirect', () => {
  it('пропускает только внутренние пути', () => {
    expect(safeRedirect('/transactions?page=2')).toBe('/transactions?page=2')
    expect(safeRedirect('//evil.example')).toBe('/')
    expect(safeRedirect('https://evil.example')).toBe('/')
    expect(safeRedirect(undefined)).toBe('/')
    expect(safeRedirect(['/a', '/b'])).toBe('/a')
  })
})
