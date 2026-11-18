import type { Paginated, Transaction, TransactionFilters, TransactionInput } from '@/types/api'
import { http } from './http'
import { toPaginated } from './pagination'

export async function listTransactions(
  params: TransactionFilters = {},
): Promise<Paginated<Transaction>> {
  return toPaginated(await http.get<Transaction[]>('/transactions', { params }))
}

export async function createTransaction(input: TransactionInput): Promise<Transaction> {
  const { data } = await http.post<Transaction>('/transactions', input)
  return data
}

export async function updateTransaction(
  id: number,
  input: Partial<TransactionInput>,
): Promise<Transaction> {
  const { data } = await http.patch<Transaction>(`/transactions/${id}`, input)
  return data
}

export async function deleteTransaction(id: number): Promise<void> {
  await http.delete(`/transactions/${id}`)
}
