import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as budgetsApi from '@/api/budgets'
import * as categoriesApi from '@/api/categories'
import { ApiError } from '@/api/errors'
import { useCategoriesStore } from '@/stores/categories'
import type { Budget } from '@/types/api'
import BudgetFormDialog from '../BudgetFormDialog.vue'

vi.mock('@/api/budgets')
vi.mock('@/api/categories')

const existing: Budget = {
  id: 7,
  category_id: 1,
  month: '2026-11',
  currency: 'USD',
  limit: '250.50',
  spent: '0.00',
  remaining: '250.50',
  percent: 0,
  status: 'ok',
  category: { id: 1, name: 'Еда', color: null, icon: null },
  created_at: 1,
  updated_at: 1,
}

async function mountDialog(budget: Budget | null) {
  setActivePinia(createPinia())
  vi.mocked(categoriesApi.listCategories).mockResolvedValue({
    items: [{ id: 1, name: 'Еда', type: 'expense', color: null, icon: null, created_at: 1 }],
    total: 1,
    page: 1,
    pageCount: 1,
    perPage: 100,
  })
  await useCategoriesStore().load()

  const wrapper = mount(BudgetFormDialog, {
    props: { visible: true, month: '2026-11', budget },
    global: { plugins: [PrimeVue] },
    attachTo: document.body,
  })
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
  return wrapper
}

const submit = async () => {
  const form = document.body.querySelector('form') as HTMLFormElement
  form.dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
}

describe('BudgetFormDialog', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    document.body.innerHTML = ''
  })

  it('не отправляет пустую форму и показывает ошибки полей', async () => {
    await mountDialog(null)

    await submit()

    expect(budgetsApi.createBudget).not.toHaveBeenCalled()
    expect(document.body.querySelector('[data-test="error-category_id"]')).not.toBeNull()
    expect(document.body.querySelector('[data-test="error-limit"]')).not.toBeNull()
  })

  it('показывает месяц, на который создаётся бюджет', async () => {
    await mountDialog(null)

    expect(document.body.querySelector('[data-test="period"]')?.textContent).toBe('Ноябрь 2026')
  })

  it('при редактировании подставляет значения и отправляет PATCH с месяцем', async () => {
    vi.mocked(budgetsApi.updateBudget).mockResolvedValue(existing)
    vi.mocked(budgetsApi.listBudgets).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      pageCount: 1,
      perPage: 100,
    })
    const wrapper = await mountDialog(existing)

    await submit()

    expect(budgetsApi.updateBudget).toHaveBeenCalledWith(7, {
      category_id: 1,
      month: '2026-11',
      currency: 'USD',
      limit: '250.50',
    })
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('ошибки сервера по полям показываются у полей', async () => {
    vi.mocked(budgetsApi.updateBudget).mockRejectedValue(
      new ApiError(422, 'Ошибка валидации.', {
        category_id: 'Бюджет на эту категорию, месяц и валюту уже есть.',
        limit: 'Лимит слишком велик.',
      }),
    )
    await mountDialog(existing)

    await submit()

    expect(document.body.querySelector('[data-test="error-limit"]')?.textContent).toContain(
      'Лимит слишком велик.',
    )
  })

  it('общая ошибка показывается сообщением формы', async () => {
    vi.mocked(budgetsApi.updateBudget).mockRejectedValue(new ApiError(0, 'Нет связи с сервером.'))
    await mountDialog(existing)

    await submit()

    expect(document.body.querySelector('[data-test="form-error"]')?.textContent).toContain(
      'Нет связи с сервером.',
    )
  })
})
