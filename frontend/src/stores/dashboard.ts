import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiError } from '@/api/errors'
import { fetchBalances, fetchCategoryReport, fetchMonthlyReport } from '@/api/reports'
import type { BalancesReport, CategoryReport, MonthlyReport } from '@/types/api'
import { currentMonth, monthBounds, shiftMonth, type YearMonth } from '@/utils/months'

/** Сколько месяцев показывать на графике динамики (включая выбранный) */
export const DYNAMICS_MONTHS = 12

export const useDashboardStore = defineStore('dashboard', () => {
  const month = ref<YearMonth>(currentMonth())
  const monthly = ref<MonthlyReport | null>(null)
  const categories = ref<CategoryReport | null>(null)
  const balances = ref<BalancesReport | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Номер запроса: быстрая смена месяца не должна показать данные предыдущего
  let requestId = 0

  async function load() {
    const current = ++requestId
    loading.value = true
    error.value = null

    const { to } = monthBounds(month.value)
    const { from } = monthBounds(shiftMonth(month.value, -(DYNAMICS_MONTHS - 1)))
    const range = monthBounds(month.value)

    try {
      const [monthlyReport, categoryReport, balancesReport] = await Promise.all([
        fetchMonthlyReport({ date_from: from, date_to: to }),
        fetchCategoryReport({ date_from: range.from, date_to: range.to, type: 'expense' }),
        fetchBalances(),
      ])
      if (current !== requestId) return

      monthly.value = monthlyReport
      categories.value = categoryReport
      balances.value = balancesReport
    } catch (e) {
      if (current !== requestId) return
      error.value = toApiError(e).message
    } finally {
      if (current === requestId) loading.value = false
    }
  }

  async function setMonth(value: YearMonth) {
    month.value = value
    await load()
  }

  /** При выходе из аккаунта данные прежнего пользователя не должны остаться в памяти */
  function reset() {
    requestId++
    month.value = currentMonth()
    monthly.value = null
    categories.value = null
    balances.value = null
    loading.value = false
    error.value = null
  }

  return { month, monthly, categories, balances, loading, error, load, setMonth, reset }
})
