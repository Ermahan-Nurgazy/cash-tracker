import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as accountsApi from '@/api/accounts'
import * as categoriesApi from '@/api/categories'
import { ApiError } from '@/api/errors'
import type { Account, Category } from '@/types/api'
import AccountsView from '../AccountsView.vue'
import CategoriesView from '../CategoriesView.vue'

const confirmRequire = vi.hoisted(() => vi.fn())
const toastAdd = vi.hoisted(() => vi.fn())
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))
vi.mock('@/api/accounts')
vi.mock('@/api/categories')

const page = <T>(items: T[]) => ({
  items,
  total: items.length,
  page: 1,
  pageCount: 1,
  perPage: 100,
})

const category = (id: number, name: string, type: Category['type']): Category => ({
  id,
  name,
  type,
  color: '#FF0000',
  icon: null,
  created_at: 1,
})

const account: Account = {
  id: 1,
  name: 'Наличные',
  currency: 'KZT',
  initial_balance: '1500.00',
  created_at: 1,
  updated_at: 1,
}

const options = { global: { plugins: [PrimeVue], stubs: { teleport: true } } }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('CategoriesView', () => {
  beforeEach(() => {
    vi.mocked(categoriesApi.listCategories).mockResolvedValue(
      page([category(1, 'Еда', 'expense'), category(2, 'Зарплата', 'income')]),
    )
  })

  it('показывает категории и фильтрует по типу', async () => {
    const wrapper = mount(CategoriesView, options)
    await flushPromises()

    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    expect(wrapper.text()).toContain('Еда')

    const filter = wrapper.find('[data-test="type-filter"]')
    const buttons = filter.findAll('[role="radio"], button, [data-pc-section="pcbutton"]')
    const income = buttons.find((button) => button.text() === 'Доходы')
    await income?.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).toContain('Зарплата')
    expect(wrapper.text()).not.toContain('Еда')
  })

  it('показывает пустое состояние', async () => {
    vi.mocked(categoriesApi.listCategories).mockResolvedValue(page([]))
    const wrapper = mount(CategoriesView, options)
    await flushPromises()

    expect(wrapper.find('[data-test="empty"]').exists()).toBe(true)
  })

  it('показывает ошибку загрузки', async () => {
    vi.mocked(categoriesApi.listCategories).mockRejectedValue(new ApiError(0, 'Нет связи.'))
    const wrapper = mount(CategoriesView, options)
    await flushPromises()

    expect(wrapper.find('[data-test="load-error"]').text()).toContain('Нет связи.')
  })

  it('удаляет категорию после подтверждения', async () => {
    vi.mocked(categoriesApi.deleteCategory).mockResolvedValue()
    const wrapper = mount(CategoriesView, options)
    await flushPromises()

    await wrapper.find('[data-test="delete"]').trigger('click')
    expect(categoriesApi.deleteCategory).not.toHaveBeenCalled()

    await confirmRequire.mock.calls[0]?.[0].accept()

    expect(categoriesApi.deleteCategory).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
  })

  it('объясняет, почему категорию с операциями не удалить (409)', async () => {
    vi.mocked(categoriesApi.deleteCategory).mockRejectedValue(
      new ApiError(409, 'Нельзя удалить категорию, по которой есть операции.'),
    )
    const wrapper = mount(CategoriesView, options)
    await flushPromises()

    await wrapper.find('[data-test="delete"]').trigger('click')
    await confirmRequire.mock.calls[0]?.[0].accept()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'error',
        detail: 'Нельзя удалить категорию, по которой есть операции.',
      }),
    )
  })
})

describe('AccountsView', () => {
  beforeEach(() => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue(page([account]))
  })

  it('показывает счета с начальным остатком', async () => {
    const wrapper = mount(AccountsView, options)
    await flushPromises()
    const text = wrapper.text().replace(/\s/g, ' ')

    expect(text).toContain('Наличные')
    expect(text).toMatch(/1 500,00 (₸|KZT)/)
  })

  it('показывает пустое состояние с подсказкой', async () => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue(page([]))
    const wrapper = mount(AccountsView, options)
    await flushPromises()

    expect(wrapper.find('[data-test="empty"]').text()).toContain('Счетов пока нет')
  })

  it('объясняет, почему счёт с операциями не удалить (409)', async () => {
    vi.mocked(accountsApi.deleteAccount).mockRejectedValue(
      new ApiError(409, 'Нельзя удалить счёт, по которому есть операции.'),
    )
    const wrapper = mount(AccountsView, options)
    await flushPromises()

    await wrapper.find('[data-test="delete"]').trigger('click')
    await confirmRequire.mock.calls[0]?.[0].accept()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'error',
        detail: 'Нельзя удалить счёт, по которому есть операции.',
      }),
    )
  })

  it('после удаления перезагружает список', async () => {
    vi.mocked(accountsApi.deleteAccount).mockResolvedValue()
    const wrapper = mount(AccountsView, options)
    await flushPromises()
    vi.mocked(accountsApi.listAccounts).mockClear()

    await wrapper.find('[data-test="delete"]').trigger('click')
    await confirmRequire.mock.calls[0]?.[0].accept()

    expect(accountsApi.deleteAccount).toHaveBeenCalledWith(1)
    expect(accountsApi.listAccounts).toHaveBeenCalledOnce()
  })
})
