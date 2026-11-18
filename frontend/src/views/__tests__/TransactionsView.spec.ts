import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import * as accountsApi from '@/api/accounts'
import * as categoriesApi from '@/api/categories'
import { ApiError } from '@/api/errors'
import * as api from '@/api/transactions'
import { useTransactionsStore } from '@/stores/transactions'
import type { Transaction } from '@/types/api'
import * as download from '@/utils/download'
import TransactionsView from '../TransactionsView.vue'

const confirmRequire = vi.hoisted(() => vi.fn())
const toastAdd = vi.hoisted(() => vi.fn())
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }))
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: confirmRequire }) }))
vi.mock('@/api/transactions')
vi.mock('@/api/accounts')
vi.mock('@/api/categories')
vi.mock('@/utils/download')

const tx = (id: number, over: Partial<Transaction> = {}): Transaction => ({
  id,
  account_id: 1,
  category_id: 10,
  amount: '1500.00',
  type: 'expense',
  occurred_at: '2026-11-10',
  note: 'Обед',
  created_at: 1,
  updated_at: 1,
  account: { id: 1, name: 'Наличные', currency: 'KZT' },
  category: { id: 10, name: 'Еда', type: 'expense', color: '#ff0000', icon: null },
  ...over,
})

const emptyPage = <T>(items: T[]) => ({
  items,
  total: items.length,
  page: 1,
  pageCount: 1,
  perPage: 20,
})

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/accounts', name: 'accounts', component: { template: '<div />' } }],
  })
  const wrapper = mount(TransactionsView, {
    global: {
      plugins: [PrimeVue, ConfirmationService, ToastService, router],
      stubs: { teleport: true },
    },
  })
  await flushPromises()
  return wrapper
}

describe('TransactionsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(accountsApi.listAccounts).mockResolvedValue(
      emptyPage([
        {
          id: 1,
          name: 'Наличные',
          currency: 'KZT',
          initial_balance: '0.00',
          created_at: 1,
          updated_at: 1,
        },
      ]),
    )
    vi.mocked(categoriesApi.listCategories).mockResolvedValue(emptyPage([]))
    vi.mocked(api.listTransactions).mockResolvedValue(
      emptyPage([tx(1), tx(2, { type: 'income', amount: '99.50' })]),
    )
  })

  it('показывает операции с форматированными суммами', async () => {
    const wrapper = await mountView()
    const text = wrapper.text().replace(/\s/g, ' ')

    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    expect(text).toContain('10.11.2026')
    expect(text).toContain('Еда')
    expect(text).toMatch(/−1 500,00 (₸|KZT)/)
    expect(text).toMatch(/\+99,50 (₸|KZT)/)
  })

  it('показывает пустое состояние', async () => {
    vi.mocked(api.listTransactions).mockResolvedValue(emptyPage([]))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="empty"]').text()).toBe('Операций пока нет.')
  })

  it('показывает ошибку загрузки', async () => {
    vi.mocked(api.listTransactions).mockRejectedValue(new ApiError(0, 'Нет связи с сервером.'))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="list-error"]').text()).toContain('Нет связи с сервером.')
  })

  it('без счетов блокирует добавление и подсказывает завести счёт', async () => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue(emptyPage([]))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="no-accounts"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="add"]').attributes('disabled')).toBeDefined()
  })

  it('поиск отправляется с задержкой, а «Сбросить» очищает фильтры', async () => {
    vi.useFakeTimers()
    try {
      const wrapper = await mountView()
      vi.mocked(api.listTransactions).mockClear()

      await wrapper.find('input[placeholder="Поиск по заметке"]').setValue('кофе')
      expect(api.listTransactions).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(450)
      expect(api.listTransactions).toHaveBeenCalledWith(expect.objectContaining({ q: 'кофе' }))

      await wrapper.find('[data-test="reset"]').trigger('click')
      await flushPromises()
      expect(api.listTransactions).toHaveBeenLastCalledWith(
        expect.not.objectContaining({ q: expect.anything() }),
      )
    } finally {
      vi.useRealTimers()
    }
  })

  it('удаляет операцию только после подтверждения', async () => {
    vi.mocked(api.deleteTransaction).mockResolvedValue()
    const wrapper = await mountView()

    await wrapper.find('[data-test="delete"]').trigger('click')

    expect(confirmRequire).toHaveBeenCalledOnce()
    expect(api.deleteTransaction).not.toHaveBeenCalled()

    await confirmRequire.mock.calls[0]?.[0].accept()

    expect(api.deleteTransaction).toHaveBeenCalledWith(1)
  })

  it('открывает форму редактирования', async () => {
    const wrapper = await mountView()

    await wrapper.find('[data-test="edit"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Редактирование операции')
  })

  it('экспортирует операции с текущими фильтрами, без страниц и сортировки', async () => {
    const blob = new Blob(['csv'])
    vi.mocked(api.exportTransactions).mockResolvedValue(blob)
    const wrapper = await mountView()
    await useTransactionsStore().setQuery({ type: 'expense', q: 'обед' })
    await flushPromises()

    await wrapper.find('[data-test="export"]').trigger('click')
    await flushPromises()

    expect(api.exportTransactions).toHaveBeenCalledWith({ type: 'expense', q: 'обед' })
    expect(download.saveBlob).toHaveBeenCalledWith(
      blob,
      expect.stringMatching(/^transactions-\d{4}-\d{2}-\d{2}\.csv$/),
    )
  })

  it('ошибка экспорта показывается сообщением, файл не сохраняется', async () => {
    vi.mocked(api.exportTransactions).mockRejectedValue(
      new ApiError(400, 'Слишком много операций для экспорта.'),
    )
    const wrapper = await mountView()

    await wrapper.find('[data-test="export"]').trigger('click')
    await flushPromises()

    expect(download.saveBlob).not.toHaveBeenCalled()
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'error',
        detail: 'Слишком много операций для экспорта.',
      }),
    )
  })

  it('после импорта перезагружает список', async () => {
    const wrapper = await mountView()
    const before = vi.mocked(api.listTransactions).mock.calls.length

    wrapper.findComponent({ name: 'TransactionImportDialog' }).vm.$emit('imported', 3)
    await flushPromises()

    expect(vi.mocked(api.listTransactions).mock.calls.length).toBeGreaterThan(before)
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: 'Импортировано операций: 3' }),
    )
  })
})
