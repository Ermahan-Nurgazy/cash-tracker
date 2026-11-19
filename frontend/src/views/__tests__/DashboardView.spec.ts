import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/errors'
import * as accountsApi from '@/api/accounts'
import * as categoriesApi from '@/api/categories'
import * as reports from '@/api/reports'
import * as transactionsApi from '@/api/transactions'
import TransactionFormDialog from '@/components/TransactionFormDialog.vue'
import type {
  Account,
  BalancesReport,
  CategoryReport,
  MonthlyReport,
  Paginated,
  Transaction,
} from '@/types/api'
import { currentMonth, shiftMonth } from '@/utils/months'
import DashboardView from '../DashboardView.vue'

vi.mock('@/api/reports')
vi.mock('@/api/transactions')
vi.mock('@/api/accounts')
vi.mock('@/api/categories')

function paginated<T>(items: T[], total = items.length): Paginated<T> {
  return { items, total, page: 1, pageCount: Math.max(1, Math.ceil(total / 10)), perPage: 10 }
}

const cash: Account = {
  id: 1,
  name: 'Наличные',
  currency: 'KZT',
  initial_balance: '0.00',
  created_at: 1,
  updated_at: 1,
}

const tx = (id: number): Transaction => ({
  id,
  account_id: 1,
  category_id: 1,
  amount: '250.00',
  type: 'expense',
  occurred_at: '2026-11-10',
  note: 'Кофе',
  created_at: 1,
  updated_at: 1,
  account: { id: 1, name: 'Наличные', currency: 'KZT' },
  category: { id: 1, name: 'Еда', type: 'expense', color: null, icon: null },
})

const month = currentMonth()
const previous = shiftMonth(month, -1)

const monthly: MonthlyReport = {
  period: { date_from: '', date_to: '' },
  currencies: [
    {
      currency: 'KZT',
      total_income: '5000.00',
      total_expense: '1500.00',
      balance: '3500.00',
      months: [
        { month: previous, income: '0.00', expense: '1000.00', balance: '-1000.00' },
        { month, income: '5000.00', expense: '1500.00', balance: '3500.00' },
      ],
    },
    {
      currency: 'USD',
      total_income: '0.00',
      total_expense: '10.00',
      balance: '-10.00',
      months: [{ month, income: '0.00', expense: '10.00', balance: '-10.00' }],
    },
  ],
}

const categories: CategoryReport = {
  type: 'expense',
  period: { date_from: '', date_to: '' },
  currencies: [
    {
      currency: 'KZT',
      total: '1500.00',
      count: 3,
      categories: [
        {
          category_id: 1,
          name: 'Еда',
          color: null,
          icon: null,
          total: '1000.00',
          count: 2,
          share: 66.7,
        },
        {
          category_id: 2,
          name: 'Такси',
          color: null,
          icon: null,
          total: '500.00',
          count: 1,
          share: 33.3,
        },
      ],
    },
  ],
}

const balances: BalancesReport = {
  accounts: [
    {
      id: 1,
      name: 'Наличные',
      currency: 'KZT',
      initial_balance: '0.00',
      income: '5000.00',
      expense: '1500.00',
      balance: '3500.00',
    },
    {
      id: 2,
      name: 'Карта USD',
      currency: 'USD',
      initial_balance: '100.00',
      income: '0.00',
      expense: '10.00',
      balance: '90.00',
    },
  ],
  totals: [
    { currency: 'KZT', balance: '3500.00' },
    { currency: 'USD', balance: '90.00' },
  ],
}

async function mountView() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/accounts', name: 'accounts', component: { template: '<div />' } },
      { path: '/transactions', name: 'transactions', component: { template: '<div />' } },
    ],
  })
  const wrapper = mount(DashboardView, {
    global: {
      plugins: [PrimeVue, ToastService, router],
      // Chart.js рисует на canvas, которого нет в jsdom: сам график проверяется через конфиг в utils
      stubs: { MonthlyChart: { template: '<div data-test="stub" />' } },
    },
  })
  await flushPromises()
  return wrapper
}

const plain = (text: string) => text.replace(/\s/g, ' ')

describe('DashboardView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(reports.fetchMonthlyReport).mockResolvedValue(monthly)
    vi.mocked(reports.fetchCategoryReport).mockResolvedValue(categories)
    vi.mocked(reports.fetchBalances).mockResolvedValue(balances)
    vi.mocked(transactionsApi.listTransactions).mockResolvedValue(paginated([tx(1), tx(2)]))
    vi.mocked(accountsApi.listAccounts).mockResolvedValue(paginated([cash]))
    vi.mocked(categoriesApi.listCategories).mockResolvedValue(paginated([]))
  })

  it('показывает итоги месяца выбранной валюты', async () => {
    const wrapper = await mountView()

    expect(plain(wrapper.find('[data-test="tile-income"]').text())).toMatch(/5 000,00 (₸|KZT)/)
    expect(plain(wrapper.find('[data-test="tile-expense"]').text())).toMatch(/1 500,00 (₸|KZT)/)
    expect(plain(wrapper.find('[data-test="tile-balance"]').text())).toMatch(/3 500,00 (₸|KZT)/)
    // Расходы выросли с 1000 до 1500: +50 % к прошлому месяцу
    expect(wrapper.find('[data-test="delta"]').text()).toContain('+50%')
  })

  it('рисует полосы категорий по убыванию с долями', async () => {
    const wrapper = await mountView()
    const rows = wrapper.findAll('[data-test="bars"] li')

    expect(rows).toHaveLength(2)
    expect(rows[0]?.text()).toContain('Еда')
    expect(rows[0]?.text()).toContain('66.7%')
    expect(rows[1]?.text()).toContain('Такси')
  })

  it('переключает валюту: данные перестраиваются под неё', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('[data-test="currency"]').exists()).toBe(true)

    const usd = wrapper
      .find('[data-test="currency"]')
      .findAll('button, [role="radio"], [data-pc-section="pcbutton"]')
      .find((el) => el.text() === 'USD')
    await usd?.trigger('click')
    await flushPromises()

    expect(plain(wrapper.find('[data-test="tile-expense"]').text())).toMatch(/10,00/)
    expect(wrapper.find('[data-test="no-expenses"]').exists()).toBe(true)
    const accounts = wrapper.find('[data-test="accounts"]').text()
    expect(accounts).toContain('Карта USD')
    expect(accounts).not.toContain('Наличные')
  })

  it('показывает остатки по счетам и общий итог', async () => {
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="accounts"]').text()).toContain('Наличные')
    expect(plain(wrapper.find('[data-test="accounts-total"]').text())).toMatch(/3 500,00/)
  })

  it('переключает график на таблицу', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('[data-test="chart"]').exists()).toBe(true)

    await wrapper.find('[data-test="toggle-table"]').trigger('click')

    expect(wrapper.find('[data-test="table"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="chart"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-test="table"] tbody tr')).toHaveLength(2)
  })

  it('листает месяцы; следующий недоступен для текущего', async () => {
    const wrapper = await mountView()
    expect(wrapper.find('[data-test="next"]').attributes('disabled')).toBeDefined()

    await wrapper.find('[data-test="prev"]').trigger('click')
    await flushPromises()

    expect(reports.fetchCategoryReport).toHaveBeenLastCalledWith(
      expect.objectContaining({ date_from: `${previous}-01` }),
    )
    expect(wrapper.find('[data-test="next"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('[data-test="current"]').exists()).toBe(true)
  })

  it('показывает пустое состояние без данных', async () => {
    vi.mocked(reports.fetchMonthlyReport).mockResolvedValue({ ...monthly, currencies: [] })
    vi.mocked(reports.fetchCategoryReport).mockResolvedValue({ ...categories, currencies: [] })
    vi.mocked(reports.fetchBalances).mockResolvedValue({ accounts: [], totals: [] })
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="empty"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="tile-income"]').exists()).toBe(false)
  })

  it('показывает ошибку загрузки', async () => {
    vi.mocked(reports.fetchBalances).mockRejectedValue(new ApiError(0, 'Нет связи с сервером.'))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="error"]').text()).toContain('Нет связи с сервером.')
  })

  it('показывает последние операции по 10 на страницу', async () => {
    const wrapper = await mountView()

    expect(transactionsApi.listTransactions).toHaveBeenCalledWith({
      sort: '-occurred_at',
      page: 1,
      'per-page': 10,
    })
    const recent = wrapper.find('[data-test="recent"]')
    expect(recent.findAll('tbody tr')).toHaveLength(2)
    expect(recent.text()).toContain('Кофе')
    expect(wrapper.find('[data-test="all-transactions"]').exists()).toBe(true)
  })

  it('листает последние операции', async () => {
    vi.mocked(transactionsApi.listTransactions).mockResolvedValue(
      paginated(
        Array.from({ length: 10 }, (_, i) => tx(i + 1)),
        25,
      ),
    )
    const wrapper = await mountView()

    const next = wrapper.find('[data-test="recent"] .p-paginator-next')
    expect(next.exists()).toBe(true)
    await next.trigger('click')
    await flushPromises()

    expect(transactionsApi.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 2, 'per-page': 10 }),
    )
  })

  it('быстрые действия открывают форму операции с нужным типом', async () => {
    const wrapper = await mountView()

    await wrapper.find('[data-test="add-income"]').trigger('click')
    await flushPromises()

    const dialog = wrapper.findComponent(TransactionFormDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('initialType')).toBe('income')

    await wrapper.find('[data-test="add-expense"]').trigger('click')
    await flushPromises()
    expect(dialog.props('initialType')).toBe('expense')
    wrapper.unmount()
  })

  it('без счетов кнопки операций неактивны и есть подсказка', async () => {
    vi.mocked(accountsApi.listAccounts).mockResolvedValue(paginated([]))
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="add-expense"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-test="add-income"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-test="add-category"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('[data-test="no-accounts"]').exists()).toBe(true)
  })

  it('кнопки операций недоступны, пока не загрузились счета и категории', async () => {
    let resolveAccounts: (value: Paginated<Account>) => void = () => undefined
    vi.mocked(accountsApi.listAccounts).mockReturnValue(
      new Promise((resolve) => (resolveAccounts = resolve)),
    )
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="add-expense"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-test="add-income"]').attributes('disabled')).toBeDefined()
    await wrapper.find('[data-test="add-expense"]').trigger('click')
    expect(wrapper.findComponent(TransactionFormDialog).props('visible')).toBe(false)

    resolveAccounts(paginated([cash]))
    await flushPromises()

    expect(wrapper.find('[data-test="add-expense"]').attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('повтор после ошибки списка возвращает на первую страницу', async () => {
    vi.mocked(transactionsApi.listTransactions).mockResolvedValue(
      paginated(
        Array.from({ length: 10 }, (_, i) => tx(i + 1)),
        25,
      ),
    )
    const wrapper = await mountView()
    vi.mocked(transactionsApi.listTransactions).mockRejectedValueOnce(
      new ApiError(0, 'Нет связи с сервером.'),
    )
    await wrapper.find('[data-test="recent"] .p-paginator-next').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-test="recent-error"]').exists()).toBe(true)

    await wrapper.find('[data-test="recent-error"] button').trigger('click')
    await flushPromises()

    expect(transactionsApi.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 1 }),
    )
    expect(wrapper.find('[data-test="recent-error"]').exists()).toBe(false)
  })

  it('после сохранения возвращает список последних операций на первую страницу', async () => {
    vi.mocked(transactionsApi.listTransactions).mockResolvedValue(
      paginated(
        Array.from({ length: 10 }, (_, i) => tx(i + 1)),
        25,
      ),
    )
    const wrapper = await mountView()
    await wrapper.find('[data-test="recent"] .p-paginator-next').trigger('click')
    await flushPromises()
    expect(transactionsApi.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 2 }),
    )

    wrapper.findComponent(TransactionFormDialog).vm.$emit('saved')
    await flushPromises()

    expect(transactionsApi.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ page: 1 }),
    )
  })

  it('после сохранения операции обновляет итоги и список', async () => {
    const wrapper = await mountView()
    vi.mocked(reports.fetchMonthlyReport).mockClear()
    vi.mocked(transactionsApi.listTransactions).mockClear()

    wrapper.findComponent(TransactionFormDialog).vm.$emit('saved')
    await flushPromises()

    expect(reports.fetchMonthlyReport).toHaveBeenCalledOnce()
    expect(transactionsApi.listTransactions).toHaveBeenCalledOnce()
  })
})
