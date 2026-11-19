import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import InputNumber from 'primevue/inputnumber'
import PrimeVue from 'primevue/config'
import Select from 'primevue/select'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import * as api from '@/api/transactions'
import { useAccountsStore } from '@/stores/accounts'
import { useCategoriesStore } from '@/stores/categories'
import type { Transaction } from '@/types/api'
import TransactionFormDialog from '../TransactionFormDialog.vue'

vi.mock('@/api/transactions')

const account = {
  id: 1,
  name: 'Наличные',
  currency: 'KZT',
  initial_balance: '0.00',
  created_at: 1,
  updated_at: 1,
}
const food = {
  id: 10,
  name: 'Еда',
  type: 'expense' as const,
  color: null,
  icon: null,
  created_at: 1,
}
const salary = {
  id: 11,
  name: 'Зарплата',
  type: 'income' as const,
  color: null,
  icon: null,
  created_at: 1,
}

// Диалог выводится в body (teleport), поэтому разметку ищем в document, а компоненты — через wrapper
let mounted: VueWrapper | null = null

async function mountDialog(
  transaction: Transaction | null = null,
  initialType?: 'expense' | 'income',
) {
  const accounts = useAccountsStore()
  const categories = useCategoriesStore()
  accounts.items = [account]
  categories.items = [food, salary]

  mounted = mount(TransactionFormDialog, {
    props: { visible: true, transaction, initialType },
    global: { plugins: [PrimeVue] },
    attachTo: document.body,
  })
  // Диалог появляется после первого рендера и анимации
  await flushPromises()
  await new Promise((resolve) => setTimeout(resolve, 30))
  return mounted
}

const dom = (selector: string) => document.body.querySelector<HTMLElement>(selector)

async function submit() {
  dom('form')?.dispatchEvent(new Event('submit', { cancelable: true }))
  await flushPromises()
}

describe('TransactionFormDialog', () => {
  afterEach(() => {
    mounted?.unmount()
    mounted = null
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(api.listTransactions).mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      pageCount: 1,
      perPage: 20,
    })
  })

  it('открывается с типом из initialType и предлагает категории этого типа', async () => {
    vi.mocked(api.createTransaction).mockResolvedValue({} as Transaction)
    const wrapper = await mountDialog(null, 'income')

    const [, categorySelect] = wrapper.findAllComponents(Select)
    expect(categorySelect?.props('options')).toEqual([salary])

    categorySelect?.vm.$emit('update:modelValue', 11)
    wrapper.findComponent(InputNumber).vm.$emit('update:modelValue', 1000)
    await submit()

    expect(api.createTransaction).toHaveBeenCalledWith(
      expect.objectContaining({ type: 'income', category_id: 11 }),
    )
  })

  it('не отправляет пустую форму и показывает ошибки', async () => {
    await mountDialog()

    await submit()

    expect(api.createTransaction).not.toHaveBeenCalled()
    expect(dom('[data-test="error-category_id"]') !== null).toBe(true)
    expect(dom('[data-test="error-amount"]') !== null).toBe(true)
  })

  it('отправляет операцию с суммой в виде строки и закрывается', async () => {
    vi.mocked(api.createTransaction).mockResolvedValue({} as Transaction)
    const wrapper = await mountDialog()

    const [, categorySelect] = wrapper.findAllComponents(Select)
    categorySelect?.vm.$emit('update:modelValue', 10)
    wrapper.findComponent(InputNumber).vm.$emit('update:modelValue', 1234.5)
    const note = dom('#tx-note') as HTMLTextAreaElement
    note.value = '  Обед  '
    note.dispatchEvent(new Event('input'))
    await submit()

    expect(api.createTransaction).toHaveBeenCalledWith(
      expect.objectContaining({
        account_id: 1,
        category_id: 10,
        amount: '1234.50',
        type: 'expense',
        note: 'Обед',
      }),
    )
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('показывает ошибки валидации сервера у полей', async () => {
    vi.mocked(api.createTransaction).mockRejectedValue(
      new ApiError(422, 'Ошибка валидации.', { amount: 'Сумма слишком большая.' }),
    )
    const wrapper = await mountDialog()

    const [, categorySelect] = wrapper.findAllComponents(Select)
    categorySelect?.vm.$emit('update:modelValue', 10)
    wrapper.findComponent(InputNumber).vm.$emit('update:modelValue', 5)
    await submit()

    expect(dom('[data-test="error-amount"]')?.textContent?.trim()).toBe('Сумма слишком большая.')
    expect(wrapper.emitted('saved')).toBeUndefined()
  })

  it('показывает общую ошибку при сбое сети и остаётся открытым', async () => {
    vi.mocked(api.createTransaction).mockRejectedValue(new ApiError(0, 'Нет связи с сервером.'))
    const wrapper = await mountDialog()

    const [, categorySelect] = wrapper.findAllComponents(Select)
    categorySelect?.vm.$emit('update:modelValue', 10)
    wrapper.findComponent(InputNumber).vm.$emit('update:modelValue', 5)
    await submit()

    expect(dom('[data-test="form-error"]')?.textContent?.trim()).toContain('Нет связи')
    expect(wrapper.emitted('update:visible')).toBeUndefined()
  })

  it('при редактировании заполняет форму и вызывает обновление', async () => {
    vi.mocked(api.updateTransaction).mockResolvedValue({} as Transaction)
    const wrapper = await mountDialog({
      id: 7,
      account_id: 1,
      category_id: 11,
      amount: '500.00',
      type: 'income',
      occurred_at: '2026-11-05',
      note: 'Аванс',
      created_at: 1,
      updated_at: 1,
      account: { id: 1, name: 'Наличные', currency: 'KZT' },
      category: { id: 11, name: 'Зарплата', type: 'income', color: null, icon: null },
    })

    expect((dom('#tx-note') as HTMLTextAreaElement).value).toBe('Аванс')

    wrapper.findComponent(InputNumber).vm.$emit('update:modelValue', 750)
    await submit()

    expect(api.updateTransaction).toHaveBeenCalledWith(
      7,
      expect.objectContaining({ amount: '750.00', category_id: 11, occurred_at: '2026-11-05' }),
    )
    expect(api.createTransaction).not.toHaveBeenCalled()
  })
})
