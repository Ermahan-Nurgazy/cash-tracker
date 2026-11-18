import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as budgetsApi from '@/api/budgets'
import * as categoriesApi from '@/api/categories'
import { ApiError } from '@/api/errors'
import { useBudgetsStore } from '@/stores/budgets'
import type { Budget, Category } from '@/types/api'
import { currentMonth, shiftMonth } from '@/utils/months'
import BudgetsView from '../BudgetsView.vue'

const confirmRequire = vi.hoisted(() => vi.fn())
const toastAdd = vi.hoisted(() => vi.fn())
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))
vi.mock('@/api/budgets')
vi.mock('@/api/categories')

const page = <T>(items: T[]) => ({
  items,
  total: items.length,
  page: 1,
  pageCount: 1,
  perPage: 100,
})

const month = currentMonth()

const budget = (id: number, over: Partial<Budget> = {}): Budget => ({
  id,
  category_id: id,
  month,
  currency: 'KZT',
  limit: '1000.00',
  spent: '400.00',
  remaining: '600.00',
  percent: 40,
  status: 'ok',
  category: { id, name: `Категория ${id}`, color: '#2a78d6', icon: null },
  created_at: 1,
  updated_at: 1,
  ...over,
})

const expenseCategory: Category = {
  id: 1,
  name: 'Еда',
  type: 'expense',
  color: null,
  icon: null,
  created_at: 1,
}

const plain = (text: string) => text.replace(/\s/g, ' ')

async function mountView() {
  const wrapper = mount(BudgetsView, {
    global: { plugins: [PrimeVue] },
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

describe('BudgetsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    document.body.innerHTML = ''
    vi.mocked(categoriesApi.listCategories).mockResolvedValue(page([expenseCategory]))
    vi.mocked(budgetsApi.listBudgets).mockResolvedValue(
      page([
        budget(1),
        budget(2, {
          limit: '100.00',
          spent: '150.50',
          remaining: '-50.50',
          percent: 151,
          status: 'exceeded',
        }),
        budget(3, { percent: 85, status: 'warning', spent: '850.00', remaining: '150.00' }),
      ]),
    )
  })

  it('запрашивает бюджеты выбранного месяца и показывает расход и остаток', async () => {
    const wrapper = await mountView()

    expect(budgetsApi.listBudgets).toHaveBeenCalledWith(expect.objectContaining({ month }))
    const rows = wrapper.findAll('[data-test="budget"]')
    expect(rows).toHaveLength(3)
    expect(plain(rows[0]!.find('[data-test="spent"]').text())).toMatch(
      /400,00 (₸|KZT) из 1 000,00 (₸|KZT)/,
    )
    expect(rows[0]!.find('[data-test="percent"]').text()).toBe('40%')
    expect(rows[0]!.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('40')
  })

  it('статус передан текстом, а перерасход назван перерасходом', async () => {
    const wrapper = await mountView()
    const statuses = wrapper.findAll('[data-test="status"]').map((el) => el.text())

    expect(statuses).toEqual(['В пределах', 'Превышен', 'На исходе'])
    const exceeded = wrapper.findAll('[data-test="budget"]')[1]!
    expect(plain(exceeded.find('[data-test="remaining"]').text())).toMatch(/Перерасход 50,50/)
    // Полоса не длиннее трека, даже если расход больше лимита
    expect(exceeded.find('.fill').attributes('style')).toContain('width: 100%')
  })

  it('листает месяцы и перезагружает бюджеты', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('[data-test="current"]').exists()).toBe(false)

    await wrapper.find('[data-test="prev"]').trigger('click')
    await flushPromises()

    expect(budgetsApi.listBudgets).toHaveBeenLastCalledWith(
      expect.objectContaining({ month: shiftMonth(month, -1) }),
    )
    expect(wrapper.find('[data-test="current"]').exists()).toBe(true)
  })

  it('показывает пустое состояние', async () => {
    vi.mocked(budgetsApi.listBudgets).mockResolvedValue(page([]))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="list"]').exists()).toBe(false)
  })

  it('без категорий расходов подсказывает их создать и блокирует «Добавить»', async () => {
    vi.mocked(categoriesApi.listCategories).mockResolvedValue(page([]))
    vi.mocked(budgetsApi.listBudgets).mockResolvedValue(page([]))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="no-categories"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="add"]').attributes('disabled')).toBeDefined()
  })

  it('показывает ошибку загрузки', async () => {
    vi.mocked(budgetsApi.listBudgets).mockRejectedValue(new ApiError(0, 'Нет связи с сервером.'))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="error"]').text()).toContain('Нет связи с сервером.')
  })

  it('удаляет бюджет только после подтверждения', async () => {
    vi.mocked(budgetsApi.deleteBudget).mockResolvedValue(undefined)
    const wrapper = await mountView()

    await wrapper.findAll('[data-test="delete"]')[0]!.trigger('click')
    expect(budgetsApi.deleteBudget).not.toHaveBeenCalled()

    const { accept } = confirmRequire.mock.calls[0]![0] as { accept: () => Promise<void> }
    await accept()

    expect(budgetsApi.deleteBudget).toHaveBeenCalledWith(1)
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }))
  })

  it('сообщение об ошибке удаления показывается тостом', async () => {
    vi.mocked(budgetsApi.deleteBudget).mockRejectedValue(new ApiError(404, 'Запись не найдена.'))
    const wrapper = await mountView()

    await wrapper.findAll('[data-test="delete"]')[0]!.trigger('click')
    const { accept } = confirmRequire.mock.calls[0]![0] as { accept: () => Promise<void> }
    await accept()

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'error', detail: 'Запись не найдена.' }),
    )
  })
})

describe('budgets store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('игнорирует ответ устаревшего запроса', async () => {
    let resolveSlow: (value: ReturnType<typeof page<Budget>>) => void = () => undefined
    vi.mocked(budgetsApi.listBudgets)
      .mockReturnValueOnce(new Promise((resolve) => (resolveSlow = resolve)))
      .mockResolvedValueOnce(page([budget(2)]))

    const store = useBudgetsStore()
    const slow = store.load()
    await store.setMonth('2026-02')

    resolveSlow(page([budget(1)]))
    await slow

    expect(store.items.map((item) => item.id)).toEqual([2])
  })

  it('create отправляет данные и перезагружает список', async () => {
    vi.mocked(budgetsApi.createBudget).mockResolvedValue(budget(1))
    vi.mocked(budgetsApi.listBudgets).mockResolvedValue(page([budget(1)]))
    const store = useBudgetsStore()

    await store.create({ category_id: 1, month, currency: 'KZT', limit: '10.00' })

    expect(budgetsApi.createBudget).toHaveBeenCalledOnce()
    expect(store.items).toHaveLength(1)
  })

  it('reset очищает данные', async () => {
    vi.mocked(budgetsApi.listBudgets).mockResolvedValue(page([budget(1)]))
    const store = useBudgetsStore()
    await store.load()

    store.reset()

    expect(store.items).toEqual([])
    expect(store.month).toBe(currentMonth())
  })
})
