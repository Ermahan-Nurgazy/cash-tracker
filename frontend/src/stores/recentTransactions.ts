import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiError } from '@/api/errors'
import { listTransactions } from '@/api/transactions'
import type { Transaction } from '@/types/api'

/** Сколько операций на странице списка на главном экране */
export const RECENT_PER_PAGE = 10

/**
 * Последние операции для главного экрана. Отдельно от стора раздела «Операции»,
 * чтобы не сбивать там фильтры, сортировку и номер страницы.
 */
export const useRecentTransactionsStore = defineStore('recentTransactions', () => {
  const items = ref<Transaction[]>([])
  const total = ref(0)
  const page = ref(1)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Номер запроса: при быстром листании ответ устаревшего запроса не должен затирать свежий
  let requestId = 0

  async function load() {
    const current = ++requestId
    loading.value = true
    error.value = null

    try {
      const result = await listTransactions({
        sort: '-occurred_at',
        page: page.value,
        'per-page': RECENT_PER_PAGE,
      })
      if (current !== requestId) return

      // Страница опустела (операции удалили в другом разделе): переходим на последнюю
      if (result.items.length === 0 && page.value > 1 && result.total > 0) {
        page.value = Math.max(1, result.pageCount)
        await load()
        return
      }

      items.value = result.items
      total.value = result.total
    } catch (e) {
      if (current !== requestId) return
      items.value = []
      total.value = 0
      error.value = toApiError(e).message
    } finally {
      if (current === requestId) loading.value = false
    }
  }

  async function setPage(value: number) {
    page.value = value
    await load()
  }

  /** При выходе из аккаунта данные прежнего пользователя не должны остаться в памяти */
  function reset() {
    requestId++
    items.value = []
    total.value = 0
    page.value = 1
    loading.value = false
    error.value = null
  }

  return { items, total, page, loading, error, load, setPage, reset }
})
