import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import * as api from '@/api/transactions'
import type { Paginated, Transaction } from '@/types/api'
import { useTransactionsStore } from '../transactions'

vi.mock('@/api/transactions')

function page(
  items: Transaction[],
  extra: Partial<Paginated<Transaction>> = {},
): Paginated<Transaction> {
  return { items, total: items.length, page: 1, pageCount: 1, perPage: 20, ...extra }
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

describe('transactions store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(api.listTransactions).mockResolvedValue(page([tx(1)]))
  })

  it('загружает страницу с сортировкой по умолчанию', async () => {
    const store = useTransactionsStore()
    await store.load()

    expect(store.items).toHaveLength(1)
    expect(api.listTransactions).toHaveBeenCalledWith({
      sort: '-occurred_at',
      page: 1,
      'per-page': 20,
    })
  })

  it('передаёт только заполненные фильтры и сбрасывает страницу', async () => {
    const store = useTransactionsStore()
    await store.setPage(3)

    await store.setQuery({ dateFrom: '2026-11-01', accountId: 2, q: '  кофе  ', type: 'expense' })

    expect(store.page).toBe(1)
    expect(api.listTransactions).toHaveBeenLastCalledWith({
      sort: '-occurred_at',
      page: 1,
      'per-page': 20,
      date_from: '2026-11-01',
      account_id: 2,
      type: 'expense',
      q: 'кофе',
    })
    expect(store.hasFilters).toBe(true)
  })

  it('resetQuery очищает фильтры', async () => {
    const store = useTransactionsStore()
    await store.setQuery({ categoryId: 5 })
    await store.resetQuery()

    expect(store.hasFilters).toBe(false)
    expect(api.listTransactions).toHaveBeenLastCalledWith({
      sort: '-occurred_at',
      page: 1,
      'per-page': 20,
    })
  })

  it('setSort передаёт сортировку на сервер', async () => {
    const store = useTransactionsStore()
    await store.setSort('amount')

    expect(api.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ sort: 'amount' }),
    )
  })

  it('запоминает ошибку загрузки и очищает список', async () => {
    const store = useTransactionsStore()
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

    const store = useTransactionsStore()
    const slow = store.load()
    await store.setQuery({ q: 'новый' })

    resolveSlow(page([tx(99)]))
    await slow

    expect(store.items.map((t) => t.id)).toEqual([2])
  })

  it('после удаления последней записи страницы возвращается назад', async () => {
    vi.mocked(api.deleteTransaction).mockResolvedValue()
    vi.mocked(api.listTransactions)
      .mockResolvedValueOnce(page([], { total: 20, pageCount: 1 }))
      .mockResolvedValueOnce(page([tx(1)], { total: 20, pageCount: 1 }))

    const store = useTransactionsStore()
    store.page = 2
    await store.remove(5)

    expect(store.page).toBe(1)
    expect(store.items).toHaveLength(1)
  })

  it('создание, изменение и удаление перезагружают список', async () => {
    vi.mocked(api.createTransaction).mockResolvedValue(tx(3))
    vi.mocked(api.updateTransaction).mockResolvedValue(tx(3))
    vi.mocked(api.deleteTransaction).mockResolvedValue()
    const store = useTransactionsStore()

    await store.create({
      account_id: 1,
      category_id: 1,
      amount: '5.00',
      type: 'expense',
      occurred_at: '2026-11-10',
    })
    await store.update(3, { amount: '6.00' })
    await store.remove(3)

    expect(api.listTransactions).toHaveBeenCalledTimes(3)
  })

  it('reset очищает данные и фильтры', async () => {
    const store = useTransactionsStore()
    await store.setQuery({ q: 'x' })
    store.reset()

    expect(store.items).toEqual([])
    expect(store.hasFilters).toBe(false)
    expect(store.page).toBe(1)
  })
})
