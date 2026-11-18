import axios from 'axios'
import { toApiError } from './errors'
import { clearToken, getToken } from './token'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  timeout: 15000,
  headers: { Accept: 'application/json' },
})

/** Эти запросы выполняются без токена, и 401 на них означает «неверные данные», а не «сессия истекла». */
const PUBLIC_PATHS = ['/auth/login', '/auth/register']

let unauthorizedHandler: (() => void) | null = null

/**
 * Что делать, когда сервер отклонил токен: auth-store (шаг 4.2) сбросит состояние,
 * а роутер перекинет на страницу входа. Через колбэк, чтобы не было циклических импортов.
 */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

http.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  return config
})

/**
 * Запросы с responseType blob (скачивание файла) получают и тело ошибки как Blob.
 * Разбираем его в JSON, чтобы пользователь увидел сообщение сервера, а не общий текст.
 */
/** Blob.text() есть не везде (например, в jsdom), FileReader работает во всех окружениях */
function readText(blob: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(reader.error)
    reader.readAsText(blob)
  })
}

async function readBlobBody(error: unknown): Promise<void> {
  if (!axios.isAxiosError(error) || !(error.response?.data instanceof Blob)) return

  try {
    error.response.data = JSON.parse(await readText(error.response.data))
  } catch {
    error.response.data = {}
  }
}

http.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    await readBlobBody(error)
    const apiError = toApiError(error)
    const url = axios.isAxiosError(error) ? (error.config?.url ?? '') : ''

    if (apiError.status === 401 && !PUBLIC_PATHS.includes(url)) {
      clearToken()
      unauthorizedHandler?.()
    }

    return Promise.reject(apiError)
  },
)
