const KEY = 'cash-tracker.token'

// Хранилище может быть недоступно (приватный режим, запрет cookie): тогда работаем без него

export function getToken(): string | null {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

export function setToken(token: string): void {
  try {
    localStorage.setItem(KEY, token)
  } catch {
    /* токен останется только в памяти store */
  }
}

export function clearToken(): void {
  try {
    localStorage.removeItem(KEY)
  } catch {
    /* ничего не делаем */
  }
}
