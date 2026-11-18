import { describe, expect, it } from 'vitest'
import { formatDate, formatMoney, fromIsoDate, toIsoDate, toMoneyString } from '../format'

// Символ тенге зависит от данных ICU (₸ или KZT), а тысячи Intl разделяет неразрывным пробелом:
// поэтому пробелы нормализуются, а символ валюты допускается любой из двух
const plain = (text: string) => text.replace(/\s/g, ' ')

describe('formatMoney', () => {
  it('форматирует расход и доход со знаком', () => {
    expect(plain(formatMoney('1234.5', 'KZT', 'expense'))).toMatch(/^−1 234,50 (₸|KZT)$/)
    expect(plain(formatMoney('1234.5', 'KZT', 'income'))).toMatch(/^\+1 234,50 (₸|KZT)$/)
  })

  it('без типа показывает знак только у отрицательных', () => {
    expect(plain(formatMoney('10', 'USD'))).toContain('10,00')
    expect(formatMoney('-10', 'USD').startsWith('−')).toBe(true)
  })

  it('не падает на неизвестной валюте', () => {
    expect(formatMoney('5', 'ZZ', 'expense')).toBe('−5.00 ZZ')
  })
})

describe('даты', () => {
  it('formatDate переводит ГГГГ-ММ-ДД в ДД.ММ.ГГГГ', () => {
    expect(formatDate('2026-11-05')).toBe('05.11.2026')
    expect(formatDate('мусор')).toBe('мусор')
  })

  it('toIsoDate использует локальную дату без сдвига по UTC', () => {
    expect(toIsoDate(new Date(2026, 0, 1, 0, 30))).toBe('2026-01-01')
    expect(toIsoDate(new Date(2026, 11, 31, 23, 59))).toBe('2026-12-31')
  })

  it('fromIsoDate и toIsoDate взаимно обратны', () => {
    expect(toIsoDate(fromIsoDate('2026-02-28'))).toBe('2026-02-28')
  })
})

describe('toMoneyString', () => {
  it('всегда отдаёт два знака', () => {
    expect(toMoneyString(10)).toBe('10.00')
    expect(toMoneyString(0.1 + 0.2)).toBe('0.30')
  })
})
