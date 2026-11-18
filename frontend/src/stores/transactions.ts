import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { toApiError } from '@/api/errors'
import * as transactionsApi from '@/api/transactions'
import type {
  Transaction,
  TransactionFilters,
  TransactionInput,
  TransactionType,
} from '@/types/api'

export interface TransactionQuery {
  dateFrom: string | null
  dateTo: string | null
  accountId: number | null
  categoryId: number | null
  type: TransactionType | null
  q: string
}

export const PER_PAGE = 20

function emptyQuery(): TransactionQuery {
  return { dateFrom: null, dateTo: null, accountId: null, categoryId: null, type: null, q: '' }
}

export const useTransactionsStore = defineStore('transactions', () => {
  const items = ref<Transaction[]>([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const query = reactive<TransactionQuery>(emptyQuery())
  const sort = ref<NonNullable<TransactionFilters['sort']>>('-occurred_at')
  const page = ref(1)

  const hasFilters = computed(() => JSON.stringify(query) !== JSON.stringify(emptyQuery()))

  // Номер запроса: ответ устаревшего запроса (быстрые смены фильтров) не должен затирать свежий
  let requestId = 0

  function buildParams(): TransactionFilters {
    const params: TransactionFilters = { sort: sort.value, page: page.value, 'per-page': PER_PAGE }

    if (query.dateFrom) params.date_from = query.dateFrom
    if (query.dateTo) params.date_to = query.dateTo
    if (query.accountId) params.account_id = query.accountId
    if (query.categoryId) params.category_id = query.categoryId
    if (query.type) params.type = query.type
    if (query.q.trim()) params.q = query.q.trim()

    return params
  }

  async function load() {
    const current = ++requestId
    loading.value = true
    error.value = null

    try {
      const result = await transactionsApi.listTransactions(buildParams())
      if (current !== requestId) return

      // Последняя запись страницы удалена: возвращаемся на предыдущую
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

  async function setQuery(patch: Partial<TransactionQuery>) {
    Object.assign(query, patch)
    page.value = 1
    await load()
  }

  async function resetQuery() {
    Object.assign(query, emptyQuery())
    page.value = 1
    await load()
  }

  async function setSort(value: NonNullable<TransactionFilters['sort']>) {
    sort.value = value
    page.value = 1
    await load()
  }

  async function setPage(value: number) {
    page.value = value
    await load()
  }

  async function create(input: TransactionInput) {
    await transactionsApi.createTransaction(input)
    await load()
  }

  async function update(id: number, input: Partial<TransactionInput>) {
    await transactionsApi.updateTransaction(id, input)
    await load()
  }

  async function remove(id: number) {
    await transactionsApi.deleteTransaction(id)
    await load()
  }

  /** При выходе из аккаунта данные прежнего пользователя не должны остаться в памяти */
  function reset() {
    requestId++
    items.value = []
    total.value = 0
    loading.value = false
    error.value = null
    Object.assign(query, emptyQuery())
    sort.value = '-occurred_at'
    page.value = 1
  }

  return {
    items,
    total,
    loading,
    error,
    query,
    sort,
    page,
    hasFilters,
    load,
    setQuery,
    resetQuery,
    setSort,
    setPage,
    create,
    update,
    remove,
    reset,
  }
})
