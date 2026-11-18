import type { ChartConfiguration } from 'chart.js'
import type { MonthlyReportRow } from '@/types/api'
import { formatMoney } from './format'
import { monthShortLabel } from './months'

/** Цвета графика берутся из CSS-переменных, чтобы тёмная и светлая темы менялись в одном месте */
export interface ChartColors {
  surface: string
  textPrimary: string
  textSecondary: string
  muted: string
  grid: string
  baseline: string
  income: string
  expense: string
}

export const INCOME_LABEL = 'Доходы'
export const EXPENSE_LABEL = 'Расходы'

/** Подпись оси: 1 500 → «1,5 тыс.»; точные значения остаются в подсказке и таблице */
export function compactNumber(value: number): string {
  return new Intl.NumberFormat('ru-RU', { notation: 'compact', maximumFractionDigits: 1 }).format(
    value,
  )
}

/** Столбчатый график «доходы и расходы по месяцам» одной валюты */
export function buildMonthlyChartConfig(
  rows: MonthlyReportRow[],
  currency: string,
  colors: ChartColors,
): ChartConfiguration<'bar'> {
  const bar = {
    // Столбец тоньше 24px, скруглён только с вершины: основание лежит на оси
    maxBarThickness: 24,
    borderRadius: 4,
    borderSkipped: 'bottom' as const,
    borderWidth: 0,
  }

  return {
    type: 'bar',
    data: {
      labels: rows.map((row) => monthShortLabel(row.month)),
      datasets: [
        {
          ...bar,
          label: INCOME_LABEL,
          data: rows.map((row) => Number(row.income)),
          backgroundColor: colors.income,
        },
        {
          ...bar,
          label: EXPENSE_LABEL,
          data: rows.map((row) => Number(row.expense)),
          backgroundColor: colors.expense,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      interaction: { mode: 'index', intersect: false },
      datasets: { bar: { categoryPercentage: 0.7, barPercentage: 0.9 } },
      plugins: {
        // Легенда выводится HTML-ом рядом с графиком, а не на холсте
        legend: { display: false },
        tooltip: {
          backgroundColor: colors.surface,
          titleColor: colors.textPrimary,
          bodyColor: colors.textSecondary,
          borderColor: colors.grid,
          borderWidth: 1,
          padding: 10,
          callbacks: {
            title: (items) => rows[items[0]?.dataIndex ?? 0]?.month ?? '',
            label: (item) =>
              `${item.dataset.label}: ${formatMoney(String(item.parsed.y), currency)}`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          border: { color: colors.baseline },
          ticks: { color: colors.muted },
        },
        y: {
          beginAtZero: true,
          grid: { color: colors.grid },
          border: { display: false },
          ticks: { color: colors.muted, callback: (value) => compactNumber(Number(value)) },
        },
      },
    },
  }
}
