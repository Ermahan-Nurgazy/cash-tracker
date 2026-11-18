import { isAxiosError } from 'axios'

/** Единый вид ошибки API: {status, message, errors?}, см. ErrorHandler и Serializer на бэкенде. */
export class ApiError extends Error {
  readonly status: number
  /** Ошибки валидации по полям (только для 422) */
  readonly errors: Record<string, string>

  constructor(status: number, message: string, errors: Record<string, string> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  get isNetwork(): boolean {
    return this.status === 0
  }
}

const NETWORK_MESSAGE = 'Нет связи с сервером. Проверьте подключение.'
const FALLBACK_MESSAGE = 'Не удалось выполнить запрос.'

/** Приводит любую ошибку (axios, ApiError, прочее) к ApiError. */
export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) {
    return error
  }

  if (isAxiosError(error)) {
    if (!error.response) {
      return new ApiError(0, NETWORK_MESSAGE)
    }

    const { status, data } = error.response as { status: number; data: unknown }
    const body = typeof data === 'object' && data !== null ? (data as Record<string, unknown>) : {}
    const message = typeof body.message === 'string' ? body.message : FALLBACK_MESSAGE
    const errors =
      typeof body.errors === 'object' && body.errors !== null
        ? (body.errors as Record<string, string>)
        : {}

    return new ApiError(status, message, errors)
  }

  return new ApiError(0, error instanceof Error ? error.message : FALLBACK_MESSAGE)
}
