/** Месяц в формате ГГГГ-ММ (как в отчёте по месяцам на бэкенде) */
export type YearMonth = string

const pad = (value: number) => String(value).padStart(2, '0')

export function currentMonth(now: Date = new Date()): YearMonth {
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`
}

function parts(month: YearMonth): [number, number] {
  const [year, mon] = month.split('-').map(Number)
  return [year ?? 1970, mon ?? 1]
}

/** Сдвиг на delta месяцев: shiftMonth('2026-01', -1) → '2025-12' */
export function shiftMonth(month: YearMonth, delta: number): YearMonth {
  const [year, mon] = parts(month)
  const index = year * 12 + (mon - 1) + delta
  return `${Math.floor(index / 12)}-${pad((index % 12) + 1)}`
}

/** Первый и последний день месяца в формате ГГГГ-ММ-ДД */
export function monthBounds(month: YearMonth): { from: string; to: string } {
  const [year, mon] = parts(month)
  const lastDay = new Date(year, mon, 0).getDate()
  return { from: `${month}-01`, to: `${month}-${pad(lastDay)}` }
}

/** «Ноябрь 2026» */
export function monthLabel(month: YearMonth): string {
  const [year, mon] = parts(month)
  const text = new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric' }).format(
    new Date(year, mon - 1, 1),
  )
  return text.charAt(0).toUpperCase() + text.slice(1).replace(/\s?г\.$/, '')
}

/** «ноя» для подписи оси; год добавляется к январю */
export function monthShortLabel(month: YearMonth): string {
  const [year, mon] = parts(month)
  const text = new Intl.DateTimeFormat('ru-RU', { month: 'short' })
    .format(new Date(year, mon - 1, 1))
    .replace('.', '')
  return mon === 1 ? `${text} ${year}` : text
}
