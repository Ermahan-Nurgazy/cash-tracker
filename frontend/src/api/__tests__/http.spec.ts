import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../errors'
import { http, setUnauthorizedHandler } from '../http'
import { toPaginated } from '../pagination'
import { clearToken, getToken, setToken } from '../token'

/** Подменяет сетевой слой: отвечает заданным статусом и телом, запоминает запрос. */
function mockServer(status: number, data: unknown = {}, headers: Record<string, string> = {}) {
  const seen: InternalAxiosRequestConfig[] = []

  const adapter: AxiosAdapter = (config) => {
    seen.push(config)
    const response = { data, status, statusText: '', headers, config, request: {} }

    if (status >= 200 && status < 300) {
      return Promise.resolve(response)
    }
    return Promise.reject(new AxiosError('failed', 'ERR_BAD_REQUEST', config, {}, response))
  }

  http.defaults.adapter = adapter
  return seen
}

describe('http-клиент', () => {
  const unauthorized = vi.fn()

  beforeEach(() => {
    clearToken()
    unauthorized.mockReset()
    setUnauthorizedHandler(unauthorized)
  })

  afterEach(() => {
    setUnauthorizedHandler(null)
  })

  it('подставляет Bearer-токен', async () => {
    setToken('abc')
    const seen = mockServer(200, {})

    await http.get('/accounts')

    expect(seen[0]?.headers.get('Authorization')).toBe('Bearer abc')
  })

  it('не отправляет заголовок без токена', async () => {
    const seen = mockServer(200, {})

    await http.get('/health')

    expect(seen[0]?.headers.has('Authorization')).toBe(false)
  })

  it('на 401 сбрасывает токен и вызывает обработчик', async () => {
    setToken('expired')
    mockServer(401, { status: 401, message: 'Требуется авторизация.' })

    await expect(http.get('/accounts')).rejects.toMatchObject({ status: 401 })

    expect(getToken()).toBeNull()
    expect(unauthorized).toHaveBeenCalledOnce()
  })

  it('401 при входе (неверный пароль) не разлогинивает', async () => {
    setToken('keep')
    mockServer(401, { status: 401, message: 'Неверный email или пароль.' })

    const error = await http.post('/auth/login', {}).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).message).toBe('Неверный email или пароль.')
    expect(getToken()).toBe('keep')
    expect(unauthorized).not.toHaveBeenCalled()
  })

  it('приводит 422 к ApiError с ошибками по полям', async () => {
    mockServer(422, {
      status: 422,
      message: 'Ошибка валидации.',
      errors: { email: 'Неверный email' },
    })

    const error = (await http.post('/auth/register', {}).catch((e: unknown) => e)) as ApiError

    expect(error).toBeInstanceOf(ApiError)
    expect(error.isValidation).toBe(true)
    expect(error.errors).toEqual({ email: 'Неверный email' })
  })

  it('сообщает об отсутствии связи', async () => {
    http.defaults.adapter = () => Promise.reject(new AxiosError('Network Error', 'ERR_NETWORK'))

    const error = (await http.get('/accounts').catch((e: unknown) => e)) as ApiError

    expect(error.isNetwork).toBe(true)
    expect(error.message).toContain('Нет связи')
  })

  it('не падает, если тело ошибки не JSON-объект', async () => {
    mockServer(502, '<html>Bad gateway</html>')

    const error = (await http.get('/accounts').catch((e: unknown) => e)) as ApiError

    expect(error.status).toBe(502)
    expect(error.message).toBe('Не удалось выполнить запрос.')
  })
})

describe('toPaginated', () => {
  it('читает заголовки пагинации', () => {
    const page = toPaginated({
      data: [1, 2],
      headers: {
        'x-pagination-total-count': '12',
        'x-pagination-current-page': '2',
        'x-pagination-page-count': '6',
        'x-pagination-per-page': '2',
      },
    } as never)

    expect(page).toEqual({ items: [1, 2], total: 12, page: 2, pageCount: 6, perPage: 2 })
  })

  it('подставляет значения по умолчанию без заголовков', () => {
    const page = toPaginated({ data: [1, 2, 3], headers: {} } as never)

    expect(page).toMatchObject({ total: 3, page: 1, pageCount: 1 })
  })
})
