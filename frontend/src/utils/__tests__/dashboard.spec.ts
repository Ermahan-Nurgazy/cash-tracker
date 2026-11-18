import { describe, expect, it } from 'vitest'
import type { BalancesReport, CategoryReport, CategoryReportItem, MonthlyReport } from '@/types/api'
import { buildMonthlyChartConfig, compactNumber, type ChartColors } from '../charts'
import {
  buildCategoryBars,
  collectCurrencies,
  expenseChangePercent,
  hasActivity,
  monthlyRows,
  summarizeMonth,
  TOP_CATEGORIES,
} from '../dashboard'
import { currentMonth, monthBounds, monthLabel, monthShortLabel, shiftMonth } from '../months'

const monthly: MonthlyReport = {
  period: { date_from: '2026-09-01', date_to: '2026-11-30' },
  currencies: [
    {
      currency: 'KZT',
      total_income: '5000.00',
      total_expense: '650.30',
      balance: '4349.70',
      months: [
        { month: '2026-09', income: '0.00', expense: '0.00', balance: '0.00' },
        { month: '2026-10', income: '0.00', expense: '600.30', balance: '-600.30' },
        { month: '2026-11', income: '5000.00', expense: '900.00', balance: '4100.00' },
      ],
    },
  ],
}

const item = (id: number, total: string, share = 10): CategoryReportItem => ({
  category_id: id,
  name: `К${id}`,
  color: null,
  icon: null,
  total,
  count: 1,
  share,
})

describe('месяцы', () => {
  it('сдвигает через границу года', () => {
    expect(shiftMonth('2026-01', -1)).toBe('2025-12')
    expect(shiftMonth('2025-12', 1)).toBe('2026-01')
    expect(shiftMonth('2026-11', -11)).toBe('2025-12')
  })

  it('считает границы месяца, включая февраль високосного года', () => {
    expect(monthBounds('2026-11')).toEqual({ from: '2026-11-01', to: '2026-11-30' })
    expect(monthBounds('2024-02').to).toBe('2024-02-29')
    expect(monthBounds('2026-02').to).toBe('2026-02-28')
  })

  it('форматирует подписи', () => {
    expect(monthLabel('2026-11')).toBe('Ноябрь 2026')
    expect(monthShortLabel('2026-03')).toBe('март')
    expect(monthShortLabel('2026-01')).toContain('2026')
  })

  it('currentMonth берёт месяц переданной даты', () => {
    expect(currentMonth(new Date(2026, 4, 9))).toBe('2026-05')
  })
})

describe('итоги месяца', () => {
  it('берёт строку выбранного месяца и расходы предыдущего', () => {
    const summary = summarizeMonth(monthly, 'KZT', '2026-11')

    expect(summary).toEqual({
      income: '5000.00',
      expense: '900.00',
      balance: '4100.00',
      previousExpense: '600.30',
    })
    expect(expenseChangePercent(summary)).toBe(50)
  })

  it('без расходов в прошлом месяце сравнение не показывается', () => {
    const summary = summarizeMonth(monthly, 'KZT', '2026-10')

    expect(summary.previousExpense).toBeNull()
    expect(expenseChangePercent(summary)).toBeNull()
  })

  it('для валюты без данных отдаёт нули', () => {
    expect(summarizeMonth(monthly, 'USD', '2026-11')).toMatchObject({
      income: '0.00',
      expense: '0.00',
      balance: '0.00',
    })
    expect(summarizeMonth(null, 'KZT', '2026-11').balance).toBe('0.00')
  })
})

describe('валюты', () => {
  it('собирает валюты из всех отчётов без повторов', () => {
    const categories: CategoryReport = {
      type: 'expense',
      period: { date_from: '', date_to: '' },
      currencies: [{ currency: 'USD', total: '1.00', count: 1, categories: [] }],
    }
    const balances: BalancesReport = {
      accounts: [],
      totals: [{ currency: 'KZT', balance: '1.00' }],
    }

    expect(collectCurrencies(monthly, categories, balances)).toEqual(['KZT', 'USD'])
    expect(collectCurrencies(null, null, null)).toEqual([])
  })
})

describe('полосы категорий', () => {
  const report = (items: CategoryReportItem[]): CategoryReport => ({
    type: 'expense',
    period: { date_from: '', date_to: '' },
    currencies: [{ currency: 'KZT', total: '0', count: items.length, categories: items }],
  })

  it('сортирует по убыванию и считает длину относительно максимума', () => {
    const bars = buildCategoryBars(report([item(1, '50.00'), item(2, '200.00')]), 'KZT')

    expect(bars.map((bar) => bar.name)).toEqual(['К2', 'К1'])
    expect(bars.map((bar) => bar.width)).toEqual([100, 25])
  })

  it('сворачивает хвост в «Другие» с точной суммой', () => {
    const items = Array.from({ length: TOP_CATEGORIES + 2 }, (_, i) =>
      item(i + 1, `${(TOP_CATEGORIES + 3 - i) * 100}.10`, 5),
    )
    const bars = buildCategoryBars(report(items), 'KZT')

    expect(bars).toHaveLength(TOP_CATEGORIES + 1)
    const other = bars[bars.length - 1]
    expect(other?.name).toBe('Другие')
    // Два самых мелких: 300.10 + 200.10
    expect(other?.total).toBe('500.20')
    expect(other?.count).toBe(2)
    expect(other?.share).toBe(10)
  })

  it('для отсутствующей валюты возвращает пустой список', () => {
    expect(buildCategoryBars(report([item(1, '1.00')]), 'USD')).toEqual([])
    expect(buildCategoryBars(null, 'KZT')).toEqual([])
  })

  it('очень маленькая категория остаётся видимой полосой', () => {
    const bars = buildCategoryBars(report([item(1, '100000.00'), item(2, '0.01')]), 'KZT')

    expect(bars[1]?.width).toBeGreaterThanOrEqual(2)
  })
})

describe('динамика', () => {
  it('отличает пустой период от периода с операциями', () => {
    expect(hasActivity(monthlyRows(monthly, 'KZT'))).toBe(true)
    expect(hasActivity(monthlyRows(monthly, 'USD'))).toBe(false)
    expect(hasActivity(monthlyRows(monthly, 'KZT').slice(0, 1))).toBe(false)
  })

  it('compactNumber сокращает большие значения', () => {
    expect(compactNumber(1500)).toMatch(/1,5/)
    expect(compactNumber(0)).toBe('0')
  })

  it('конфиг графика: два ряда, подписи месяцев и подсказка в валюте', () => {
    const colors: ChartColors = {
      surface: '#fff',
      textPrimary: '#000',
      textSecondary: '#333',
      muted: '#888',
      grid: '#eee',
      baseline: '#ccc',
      income: '#2a78d6',
      expense: '#eb6834',
    }
    const rows = monthlyRows(monthly, 'KZT')
    const config = buildMonthlyChartConfig(rows, 'KZT', colors)

    expect(config.data.labels).toHaveLength(3)
    expect(config.data.datasets.map((d) => d.label)).toEqual(['Доходы', 'Расходы'])
    expect(config.data.datasets[0]?.data).toEqual([0, 0, 5000])
    expect(config.data.datasets[1]?.backgroundColor).toBe('#eb6834')
    expect(config.options?.plugins?.legend?.display).toBe(false)

    const label = config.options?.plugins?.tooltip?.callbacks?.label as (ctx: unknown) => string
    const text = label({ dataset: { label: 'Расходы' }, parsed: { y: 900 } }).replace(/\s/g, ' ')
    expect(text).toMatch(/^Расходы: −?900,00 (₸|KZT)$/)
  })
})
