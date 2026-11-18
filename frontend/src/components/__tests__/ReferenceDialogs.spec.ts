import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import type { Component } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as accountsApi from '@/api/accounts'
import * as categoriesApi from '@/api/categories'
import { ApiError } from '@/api/errors'
import type { Account, Category } from '@/types/api'
import AccountFormDialog from '../AccountFormDialog.vue'
import CategoryFormDialog from '../CategoryFormDialog.vue'

vi.mock('@/api/accounts')
vi.mock('@/api/categories')

let mounted: VueWrapper | null = null

// Диалог выводится в body (teleport), поэтому разметку ищем в document
async function mountDialog(component: Component, props: Record<string, unknown>) {
  mounted = mount(component, {
    props: { visible: true, ...props },
    global: { plugins: [PrimeVue] },
    attachTo: document.body,
  })
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
  return mounted
}

const dom = (selector: string) => document.body.querySelector<HTMLElement>(selector)
const text = (selector: string) => dom(selector)?.textContent?.trim()

function type(selector: string, value: string) {
  const input = dom(selector) as HTMLInputElement
  input.value = value
  input.dispatchEvent(new Event('input'))
}

async function submit() {
  dom('form')?.dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
}

const page = <T>(items: T[]) => ({
  items,
  total: items.length,
  page: 1,
  pageCount: 1,
  perPage: 100,
})

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
  vi.mocked(categoriesApi.listCategories).mockResolvedValue(page([]))
  vi.mocked(accountsApi.listAccounts).mockResolvedValue(page([]))
})

afterEach(() => {
  mounted?.unmount()
  mounted = null
})

describe('CategoryFormDialog', () => {
  it('не отправляет пустое название', async () => {
    await mountDialog(CategoryFormDialog, { category: null })

    await submit()

    expect(text('[data-test="error-name"]')).toBe('Введите название.')
    expect(categoriesApi.createCategory).not.toHaveBeenCalled()
  })

  it('создаёт категорию с выбранными цветом и иконкой', async () => {
    vi.mocked(categoriesApi.createCategory).mockResolvedValue({} as Category)
    const wrapper = await mountDialog(CategoryFormDialog, { category: null })

    type('#cat-name', '  Кафе  ')
    dom('[data-test="color"]')?.click()
    dom('[data-test="icon"]')?.click()
    await submit()

    expect(categoriesApi.createCategory).toHaveBeenCalledWith({
      name: 'Кафе',
      type: 'expense',
      color: '#F44336',
      icon: 'pi-shopping-cart',
    })
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('показывает отказ сервера по типу категории с операциями', async () => {
    vi.mocked(categoriesApi.updateCategory).mockRejectedValue(
      new ApiError(422, 'Ошибка валидации.', {
        type: 'Нельзя изменить тип категории, по которой есть операции.',
      }),
    )
    const category: Category = {
      id: 3,
      name: 'Еда',
      type: 'expense',
      color: null,
      icon: null,
      created_at: 1,
    }
    const wrapper = await mountDialog(CategoryFormDialog, { category })

    await submit()

    expect(text('[data-test="error-type"]')).toContain('Нельзя изменить тип')
    expect(wrapper.emitted('saved')).toBeUndefined()
  })

  it('общая ошибка сети остаётся в форме', async () => {
    vi.mocked(categoriesApi.createCategory).mockRejectedValue(new ApiError(0, 'Нет связи.'))
    await mountDialog(CategoryFormDialog, { category: null })

    type('#cat-name', 'Кафе')
    await submit()

    expect(text('[data-test="form-error"]')).toContain('Нет связи.')
  })
})

describe('AccountFormDialog', () => {
  it('проверяет название и код валюты на клиенте', async () => {
    await mountDialog(AccountFormDialog, { account: null })

    type('#acc-currency', 'TENGE')
    await submit()

    expect(text('[data-test="error-name"]')).toBe('Введите название.')
    expect(text('[data-test="error-currency"]')).toContain('три латинские буквы')
    expect(accountsApi.createAccount).not.toHaveBeenCalled()
  })

  it('создаёт счёт и приводит валюту к верхнему регистру', async () => {
    vi.mocked(accountsApi.createAccount).mockResolvedValue({} as Account)
    const wrapper = await mountDialog(AccountFormDialog, { account: null })

    type('#acc-name', 'Карта')
    type('#acc-currency', 'usd')
    await submit()

    expect(accountsApi.createAccount).toHaveBeenCalledWith({
      name: 'Карта',
      currency: 'USD',
      initial_balance: '0.00',
    })
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })

  it('показывает отказ сервера по смене валюты счёта с операциями', async () => {
    vi.mocked(accountsApi.updateAccount).mockRejectedValue(
      new ApiError(422, 'Ошибка валидации.', {
        currency: 'Нельзя изменить валюту счёта, по которому есть операции.',
      }),
    )
    const account: Account = {
      id: 4,
      name: 'Наличные',
      currency: 'KZT',
      initial_balance: '100.00',
      created_at: 1,
      updated_at: 1,
    }
    await mountDialog(AccountFormDialog, { account })

    await submit()

    expect(accountsApi.updateAccount).toHaveBeenCalledWith(4, {
      name: 'Наличные',
      currency: 'KZT',
      initial_balance: '100.00',
    })
    expect(text('[data-test="error-currency"]')).toContain('Нельзя изменить валюту')
  })
})
