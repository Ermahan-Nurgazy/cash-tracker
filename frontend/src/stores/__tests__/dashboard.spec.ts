import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import * as reports from '@/api/reports'
import type { BalancesReport, CategoryReport, MonthlyReport } from '@/types/api'
import { useDashboardStore } from '../dashboard'

vi.mock('@/api/reports')

const monthlyReport = (marker: string): MonthlyReport => ({
  period: { date_from: marker, date_to: marker },
  currencies: [],
})
const categoryReport: CategoryReport = {
  type: 'expense',
  period: { date_from: '', date_to: '' },
  currencies: [],
}
const balancesReport: BalancesReport = { accounts: [], totals: [] }

describe('dashboard store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(reports.fetchMonthlyReport).mockResolvedValue(monthlyReport('a'))
    vi.mocked(reports.fetchCategoryReport).mockResolvedValue(categoryReport)
    vi.mocked(reports.fetchBalances).mockResolvedValue(balancesReport)
  })

  it('запрашивает 12 месяцев динамики и расходы за выбранный месяц', async () => {
    const store = useDashboardStore()
    store.month = '2026-11'

    await store.load()

    expect(reports.fetchMonthlyReport).toHaveBeenCalledWith({
      date_from: '2025-12-01',
      date_to: '2026-11-30',
    })
    expect(reports.fetchCategoryReport).toHaveBeenCalledWith({
      date_from: '2026-11-01',
      date_to: '2026-11-30',
      type: 'expense',
    })
    expect(store.monthly).not.toBeNull()
    expect(store.loading).toBe(false)
  })

  it('смена месяца перезагружает данные', async () => {
    const store = useDashboardStore()

    await store.setMonth('2026-03')

    expect(store.month).toBe('2026-03')
    expect(reports.fetchCategoryReport).toHaveBeenCalledWith(
      expect.objectContaining({ date_from: '2026-03-01', date_to: '2026-03-31' }),
    )
  })

  it('запоминает ошибку, а данные прошлой загрузки не стирает', async () => {
    const store = useDashboardStore()
    await store.load()
    vi.mocked(reports.fetchBalances).mockRejectedValue(new ApiError(0, 'Нет связи.'))

    await store.load()

    expect(store.error).toBe('Нет связи.')
    expect(store.monthly).not.toBeNull()
    expect(store.loading).toBe(false)
  })

  it('игнорирует ответ устаревшего запроса', async () => {
    let resolveSlow: (value: MonthlyReport) => void = () => undefined
    vi.mocked(reports.fetchMonthlyReport)
      .mockReturnValueOnce(new Promise((resolve) => (resolveSlow = resolve)))
      .mockResolvedValueOnce(monthlyReport('fresh'))

    const store = useDashboardStore()
    const slow = store.load()
    await store.setMonth('2026-02')

    resolveSlow(monthlyReport('stale'))
    await slow

    expect(store.monthly?.period.date_from).toBe('fresh')
  })

  it('reset очищает данные', async () => {
    const store = useDashboardStore()
    await store.load()

    store.reset()

    expect(store.monthly).toBeNull()
    expect(store.balances).toBeNull()
    expect(store.error).toBeNull()
  })
})
