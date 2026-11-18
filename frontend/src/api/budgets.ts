import type { Budget, BudgetInput, Paginated } from '@/types/api'
import { http } from './http'
import { toPaginated } from './pagination'

export async function listBudgets(
  params: {
    month?: string
    category_id?: number
    currency?: string
    page?: number
    'per-page'?: number
  } = {},
): Promise<Paginated<Budget>> {
  return toPaginated(await http.get<Budget[]>('/budgets', { params }))
}

export async function createBudget(input: BudgetInput): Promise<Budget> {
  const { data } = await http.post<Budget>('/budgets', input)
  return data
}

export async function updateBudget(id: number, input: Partial<BudgetInput>): Promise<Budget> {
  const { data } = await http.patch<Budget>(`/budgets/${id}`, input)
  return data
}

export async function deleteBudget(id: number): Promise<void> {
  await http.delete(`/budgets/${id}`)
}
