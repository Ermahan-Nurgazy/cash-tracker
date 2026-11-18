import type { BalancesReport, CategoryReport, MonthlyReport, MonthlyReportRow } from '@/types/api'
import type { YearMonth } from './months'

/** Сколько категорий показывать отдельными строками, остальные сворачиваются в «Другие» */
export const TOP_CATEGORIES = 7

/** Валюты, по которым есть хоть какие-то данные, в порядке появления (без повторов) */
export function collectCurrencies(
  monthly: MonthlyReport | null,
  categories: CategoryReport | null,
  balances: BalancesReport | null,
): string[] {
  const all = [
    ...(balances?.totals.map((item) => item.currency) ?? []),
    ...(monthly?.currencies.map((item) => item.currency) ?? []),
    ...(categories?.currencies.map((item) => item.currency) ?? []),
  ]
  return [...new Set(all)].sort()
}

export interface MonthSummary {
  income: string
  expense: string
  balance: string
  /** Расходы прошлого месяца; null, если их не было */
  previousExpense: string | null
}

const EMPTY_ROW = (month: YearMonth): MonthlyReportRow => ({
  month,
  income: '0.00',
  expense: '0.00',
  balance: '0.00',
})

/** Итоги выбранного месяца и расходы предыдущего (для сравнения) из отчёта по месяцам */
export function summarizeMonth(
  monthly: MonthlyReport | null,
  currency: string,
  month: YearMonth,
): MonthSummary {
  const rows = monthly?.currencies.find((item) => item.currency === currency)?.months ?? []
  const index = rows.findIndex((row) => row.month === month)
  const row = rows[index] ?? EMPTY_ROW(month)
  const previous = index > 0 ? rows[index - 1] : undefined

  return {
    income: row.income,
    expense: row.expense,
    balance: row.balance,
    previousExpense: previous && Number(previous.expense) > 0 ? previous.expense : null,
  }
}

/** Изменение расходов к прошлому месяцу в процентах (целое); null, если сравнивать не с чем */
export function expenseChangePercent(summary: MonthSummary): number | null {
  if (summary.previousExpense === null) return null
  const current = Number(summary.expense)
  const previous = Number(summary.previousExpense)
  return Math.round(((current - previous) / previous) * 100)
}

export interface CategoryBar {
  key: string
  name: string
  total: string
  count: number
  share: number
  /** Длина полосы относительно самой большой, 0–100 */
  width: number
}

/**
 * Строки для ранжированных полос: самые крупные категории по убыванию,
 * хвост сворачивается в «Другие», чтобы список не растягивался.
 */
export function buildCategoryBars(report: CategoryReport | null, currency: string): CategoryBar[] {
  const block = report?.currencies.find((item) => item.currency === currency)
  if (!block) return []

  const sorted = [...block.categories].sort((a, b) => Number(b.total) - Number(a.total))
  const head = sorted.slice(0, TOP_CATEGORIES)
  const tail = sorted.slice(TOP_CATEGORIES)

  const items: Omit<CategoryBar, 'width'>[] = head.map((item) => ({
    key: String(item.category_id),
    name: item.name,
    total: item.total,
    count: item.count,
    share: item.share,
  }))

  if (tail.length > 0) {
    const cents = tail.reduce((sum, item) => sum + Math.round(Number(item.total) * 100), 0)
    items.push({
      key: 'other',
      name: 'Другие',
      total: (cents / 100).toFixed(2),
      count: tail.reduce((sum, item) => sum + item.count, 0),
      share: Math.round(tail.reduce((sum, item) => sum + item.share, 0) * 10) / 10,
    })
  }

  const max = Math.max(...items.map((item) => Number(item.total)), 0)

  return items.map((item) => ({
    ...item,
    width: max > 0 ? Math.max(2, (Number(item.total) / max) * 100) : 0,
  }))
}

/** Месяцы выбранной валюты для графика и таблицы динамики */
export function monthlyRows(monthly: MonthlyReport | null, currency: string): MonthlyReportRow[] {
  return monthly?.currencies.find((item) => item.currency === currency)?.months ?? []
}

/** Есть ли в рядах хоть одна операция: иначе график из одних нулей не показываем */
export function hasActivity(rows: MonthlyReportRow[]): boolean {
  return rows.some((row) => Number(row.income) > 0 || Number(row.expense) > 0)
}
