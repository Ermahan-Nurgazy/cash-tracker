import type { Money, TransactionType } from '@/types/api'

/**
 * Форматирует сумму для показа. Суммы приходят строками; число используется только для отображения,
 * поэтому точность двух знаков после запятой сохраняется.
 */
export function formatMoney(amount: Money, currency: string, type?: TransactionType): string {
  const value = Number(amount)
  let text: string

  try {
    text = new Intl.NumberFormat('ru-RU', { style: 'currency', currency }).format(Math.abs(value))
  } catch {
    // Неизвестный код валюты: показываем число и код как есть
    text = `${Math.abs(value).toFixed(2)} ${currency}`
  }

  const sign = type === 'expense' ? '−' : type === 'income' ? '+' : value < 0 ? '−' : ''
  return sign + text
}

/** '2026-11-10' → '10.11.2026' */
export function formatDate(iso: string): string {
  const [year, month, day] = iso.split('-')
  return year && month && day ? `${day}.${month}.${year}` : iso
}

/** Date → 'ГГГГ-ММ-ДД' по локальному времени (toISOString сдвинул бы дату по UTC). */
export function toIsoDate(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

/** 'ГГГГ-ММ-ДД' → Date в локальном времени */
export function fromIsoDate(iso: string): Date {
  const [year, month, day] = iso.split('-').map(Number)
  return new Date(year ?? 1970, (month ?? 1) - 1, day ?? 1)
}

/** Сумма из поля ввода → строка с двумя знаками для API */
export function toMoneyString(value: number): string {
  return value.toFixed(2)
}
