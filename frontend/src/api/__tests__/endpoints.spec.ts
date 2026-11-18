import { type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import * as accounts from '../accounts'
import * as auth from '../auth'
import * as categories from '../categories'
import { http } from '../http'
import * as reports from '../reports'
import { clearToken, getToken, setToken } from '../token'
import * as transactions from '../transactions'

let seen: InternalAxiosRequestConfig[] = []
const originalAdapter = http.defaults.adapter

beforeEach(() => {
  seen = []
  http.defaults.adapter = ((config: InternalAxiosRequestConfig) => {
    seen.push(config)
    return Promise.resolve({ data: [], status: 200, statusText: '', headers: {}, config })
  }) as AxiosAdapter
})

afterEach(() => {
  http.defaults.adapter = originalAdapter
})

/** Короткая запись последнего запроса: метод, путь, параметры, тело. */
function last() {
  const config = seen[seen.length - 1]!
  return {
    method: config.method,
    url: config.url,
    params: config.params,
    body: typeof config.data === 'string' ? JSON.parse(config.data) : config.data,
  }
}

describe('API-обёртки: адреса, методы и параметры', () => {
  it('auth', async () => {
    await auth.login({ email: 'a@b.c', password: 'p' })
    expect(last()).toMatchObject({ method: 'post', url: '/auth/login' })
    expect(last().body).toEqual({ email: 'a@b.c', password: 'p' })

    await auth.register({ email: 'a@b.c', password: 'p' })
    expect(last()).toMatchObject({ method: 'post', url: '/auth/register' })

    await auth.fetchMe()
    expect(last()).toMatchObject({ method: 'get', url: '/auth/me' })

    await auth.logout()
    expect(last()).toMatchObject({ method: 'post', url: '/auth/logout' })
  })

  it('accounts', async () => {
    await accounts.listAccounts({ page: 2 })
    expect(last()).toMatchObject({ method: 'get', url: '/accounts', params: { page: 2 } })

    await accounts.createAccount({ name: 'Н', currency: 'KZT' })
    expect(last()).toMatchObject({ method: 'post', url: '/accounts' })

    await accounts.updateAccount(5, { name: 'Новый' })
    expect(last()).toMatchObject({ method: 'patch', url: '/accounts/5', body: { name: 'Новый' } })

    await accounts.deleteAccount(5)
    expect(last()).toMatchObject({ method: 'delete', url: '/accounts/5' })
  })

  it('categories', async () => {
    await categories.listCategories()
    expect(last()).toMatchObject({ method: 'get', url: '/categories' })

    await categories.updateCategory(3, { name: 'Еда' })
    expect(last()).toMatchObject({ method: 'patch', url: '/categories/3' })

    await categories.deleteCategory(3)
    expect(last()).toMatchObject({ method: 'delete', url: '/categories/3' })
  })

  it('transactions', async () => {
    await transactions.listTransactions({ page: 1, type: 'expense' })
    expect(last()).toMatchObject({
      method: 'get',
      url: '/transactions',
      params: { page: 1, type: 'expense' },
    })

    await transactions.updateTransaction(9, { amount: '10.00' })
    expect(last()).toMatchObject({ method: 'patch', url: '/transactions/9' })

    await transactions.deleteTransaction(9)
    expect(last()).toMatchObject({ method: 'delete', url: '/transactions/9' })
  })

  it('reports', async () => {
    await reports.fetchCategoryReport({ date_from: '2026-01-01', type: 'expense' })
    expect(last()).toMatchObject({
      url: '/reports/categories',
      params: { date_from: '2026-01-01', type: 'expense' },
    })

    await reports.fetchMonthlyReport({ date_from: '2026-01-01' })
    expect(last()).toMatchObject({ url: '/reports/monthly' })

    await reports.fetchBalances()
    expect(last()).toMatchObject({ url: '/reports/balances' })
  })
})

describe('token: хранилище недоступно', () => {
  afterEach(() => {
    clearToken()
  })

  it('не падает, если localStorage бросает исключения', () => {
    const broken = new Proxy(localStorage, {
      get() {
        throw new Error('запрещено')
      },
    })
    const original = Object.getOwnPropertyDescriptor(window, 'localStorage')!
    Object.defineProperty(window, 'localStorage', { value: broken, configurable: true })

    try {
      expect(getToken()).toBeNull()
      expect(() => setToken('x')).not.toThrow()
      expect(() => clearToken()).not.toThrow()
    } finally {
      Object.defineProperty(window, 'localStorage', original)
    }
  })
})
