import { defineStore } from 'pinia'
import { ref } from 'vue'
import * as budgetsApi from '@/api/budgets'
import { toApiError } from '@/api/errors'
import { fetchAllPages } from '@/api/pagination'
import type { Budget, BudgetInput } from '@/types/api'
import { currentMonth, type YearMonth } from '@/utils/months'

/** Бюджеты выбранного месяца. */
export const useBudgetsStore = defineStore('budgets', () => {
  const month = ref<YearMonth>(currentMonth())
  const items = ref<Budget[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Номер запроса: быстрая смена месяца не должна показать бюджеты прежнего
  let requestId = 0

  async function load() {
    const current = ++requestId
    loading.value = true
    error.value = null

    try {
      const list = await fetchAllPages((page) =>
        budgetsApi.listBudgets({ month: month.value, page, 'per-page': 100 }),
      )
      if (current !== requestId) return
      items.value = list
    } catch (e) {
      if (current !== requestId) return
      error.value = toApiError(e).message
    } finally {
      if (current === requestId) loading.value = false
    }
  }

  async function setMonth(value: YearMonth) {
    month.value = value
    // Бюджеты прошлого месяца не показываем, пока грузятся новые
    items.value = []
    await load()
  }

  async function create(input: BudgetInput) {
    await budgetsApi.createBudget(input)
    await load()
  }

  async function update(id: number, input: Partial<BudgetInput>) {
    await budgetsApi.updateBudget(id, input)
    await load()
  }

  async function remove(id: number) {
    await budgetsApi.deleteBudget(id)
    await load()
  }

  function reset() {
    requestId++
    month.value = currentMonth()
    items.value = []
    loading.value = false
    error.value = null
  }

  return { month, items, loading, error, load, setMonth, create, update, remove, reset }
})
