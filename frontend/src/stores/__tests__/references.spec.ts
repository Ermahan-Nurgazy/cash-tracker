import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as accountsApi from '@/api/accounts'
import * as categoriesApi from '@/api/categories'
import type { Category } from '@/types/api'
import { useAccountsStore } from '../accounts'
import { useCategoriesStore } from '../categories'

vi.mock('@/api/accounts')
vi.mock('@/api/categories')

const category = (id: number, type: Category['type']): Category => ({
  id,
  name: `К${id}`,
  type,
  color: null,
  icon: null,
  created_at: 1,
})

describe('справочники', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('счета загружаются один раз', async () => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      pageCount: 1,
      perPage: 100,
    })
    const store = useAccountsStore()

    await store.load()
    await store.load()
    expect(accountsApi.listAccounts).toHaveBeenCalledTimes(1)

    await store.load(true)
    expect(accountsApi.listAccounts).toHaveBeenCalledTimes(2)
  })

  it('категории подгружают все страницы и фильтруются по типу', async () => {
    vi.mocked(categoriesApi.listCategories)
      .mockResolvedValueOnce({
        items: [category(1, 'expense'), category(2, 'income')],
        total: 3,
        page: 1,
        pageCount: 2,
        perPage: 2,
      })
      .mockResolvedValueOnce({
        items: [category(3, 'expense')],
        total: 3,
        page: 2,
        pageCount: 2,
        perPage: 2,
      })
    const store = useCategoriesStore()

    await store.load()

    expect(store.items).toHaveLength(3)
    expect(store.byType('expense').map((c) => c.id)).toEqual([1, 3])
    expect(store.byType('income').map((c) => c.id)).toEqual([2])
  })

  it('reset забывает загруженное', async () => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      pageCount: 1,
      perPage: 100,
    })
    const store = useAccountsStore()
    await store.load()

    store.reset()
    await store.load()

    expect(accountsApi.listAccounts).toHaveBeenCalledTimes(2)
  })

  it('изменения счетов и категорий перезагружают справочник, даже если загрузка уже идёт', async () => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      pageCount: 1,
      perPage: 100,
    })
    vi.mocked(accountsApi.createAccount).mockResolvedValue({} as never)
    vi.mocked(accountsApi.updateAccount).mockResolvedValue({} as never)
    vi.mocked(accountsApi.deleteAccount).mockResolvedValue()
    const store = useAccountsStore()

    const pending = store.load()
    await store.create({ name: 'A', currency: 'KZT' })
    await pending
    await store.update(1, { name: 'B' })
    await store.remove(1)

    // Первая загрузка и по одной после create, update, remove
    expect(accountsApi.listAccounts).toHaveBeenCalledTimes(4)
  })

  it('категории: создание, изменение и удаление перезагружают список', async () => {
    vi.mocked(categoriesApi.listCategories).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      pageCount: 1,
      perPage: 100,
    })
    vi.mocked(categoriesApi.createCategory).mockResolvedValue({} as never)
    vi.mocked(categoriesApi.updateCategory).mockResolvedValue({} as never)
    vi.mocked(categoriesApi.deleteCategory).mockResolvedValue()
    const store = useCategoriesStore()

    await store.create({ name: 'A', type: 'expense' })
    await store.update(1, { name: 'B' })
    await store.remove(1)

    expect(categoriesApi.listCategories).toHaveBeenCalledTimes(3)
  })
})
