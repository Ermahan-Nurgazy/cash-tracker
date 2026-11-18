import type { Account, AccountInput, Paginated } from '@/types/api'
import { http } from './http'
import { toPaginated } from './pagination'

export async function listAccounts(
  params: { page?: number; 'per-page'?: number } = {},
): Promise<Paginated<Account>> {
  return toPaginated(await http.get<Account[]>('/accounts', { params }))
}

export async function createAccount(input: AccountInput): Promise<Account> {
  const { data } = await http.post<Account>('/accounts', input)
  return data
}

export async function updateAccount(id: number, input: Partial<AccountInput>): Promise<Account> {
  const { data } = await http.patch<Account>(`/accounts/${id}`, input)
  return data
}

export async function deleteAccount(id: number): Promise<void> {
  await http.delete(`/accounts/${id}`)
}
