import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/errors'
import * as reports from '@/api/reports'
import type { BalancesReport, CategoryReport, MonthlyReport } from '@/types/api'
import { currentMonth, shiftMonth } from '@/utils/months'
import DashboardView from '../DashboardView.vue'

vi.mock('@/api/reports')

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
      plugins: [PrimeVue, router],
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
    expect(wrapper.text()).toContain('Карта USD')
    expect(wrapper.text()).not.toContain('Наличные')
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
})
