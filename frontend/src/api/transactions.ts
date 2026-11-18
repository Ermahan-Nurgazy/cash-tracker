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

/** Параметры экспорта: те же фильтры, что у списка, но без сортировки и страниц */
export type ExportFilters = Omit<TransactionFilters, 'sort' | 'page' | 'per-page'>

export interface ImportResult {
  imported: number
}

/** Скачивает CSV с операциями; тело ошибки (JSON) разбирает перехватчик http. */
export async function exportTransactions(params: ExportFilters = {}): Promise<Blob> {
  const { data } = await http.get<Blob>('/transactions/export', { params, responseType: 'blob' })
  return data
}

/** Отправляет файл как есть (байты), чтобы сервер сам определил кодировку: UTF-8 или Windows-1251. */
export async function importTransactions(file: File): Promise<ImportResult> {
  const { data } = await http.post<ImportResult>('/transactions/import', await file.arrayBuffer(), {
    headers: { 'Content-Type': 'text/csv' },
    timeout: 60000,
  })
  return data
}
