import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import * as api from '@/api/transactions'
import type { Paginated, Transaction } from '@/types/api'
import { useRecentTransactionsStore } from '../recentTransactions'

vi.mock('@/api/transactions')

function page(
  items: Transaction[],
  extra: Partial<Paginated<Transaction>> = {},
): Paginated<Transaction> {
  return { items, total: items.length, page: 1, pageCount: 1, perPage: 10, ...extra }
}

const tx = (id: number): Transaction => ({
  id,
  account_id: 1,
  category_id: 1,
  amount: '10.00',
  type: 'expense',
  occurred_at: '2026-11-10',
  note: null,
  created_at: 1,
  updated_at: 1,
  account: { id: 1, name: 'Наличные', currency: 'KZT' },
  category: { id: 1, name: 'Еда', type: 'expense', color: null, icon: null },
})

describe('recentTransactions store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(api.listTransactions).mockResolvedValue(page([tx(1)], { total: 15, pageCount: 2 }))
  })

  it('загружает 10 последних операций по дате', async () => {
    const store = useRecentTransactionsStore()
    await store.load()

    expect(api.listTransactions).toHaveBeenCalledWith({
      sort: '-occurred_at',
      page: 1,
      'per-page': 10,
    })
    expect(store.items).toHaveLength(1)
    expect(store.total).toBe(15)
  })

  it('setPage загружает нужную страницу', async () => {
    const store = useRecentTransactionsStore()
    await store.setPage(2)

    expect(store.page).toBe(2)
    expect(api.listTransactions).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 }))
  })

  it('с опустевшей страницы переходит на последнюю', async () => {
    vi.mocked(api.listTransactions)
      .mockResolvedValueOnce(page([], { total: 5, pageCount: 1 }))
      .mockResolvedValueOnce(page([tx(1)], { total: 5, pageCount: 1 }))

    const store = useRecentTransactionsStore()
    await store.setPage(3)

    expect(store.page).toBe(1)
    expect(store.items).toHaveLength(1)
  })

  it('запоминает ошибку и очищает список', async () => {
    const store = useRecentTransactionsStore()
    await store.load()
    vi.mocked(api.listTransactions).mockRejectedValue(new ApiError(0, 'Нет связи'))

    await store.load()

    expect(store.error).toBe('Нет связи')
    expect(store.items).toEqual([])
    expect(store.loading).toBe(false)
  })

  it('игнорирует ответ устаревшего запроса', async () => {
    let resolveSlow: (value: Paginated<Transaction>) => void = () => undefined
    vi.mocked(api.listTransactions)
      .mockReturnValueOnce(new Promise((resolve) => (resolveSlow = resolve)))
      .mockResolvedValueOnce(page([tx(2)]))

    const store = useRecentTransactionsStore()
    const slow = store.load()
    await store.setPage(2)

    resolveSlow(page([tx(99)]))
    await slow

    expect(store.items.map((t) => t.id)).toEqual([2])
  })

  it('reset очищает данные и страницу', async () => {
    const store = useRecentTransactionsStore()
    await store.setPage(2)
    store.reset()

    expect(store.items).toEqual([])
    expect(store.total).toBe(0)
    expect(store.page).toBe(1)
  })
})
