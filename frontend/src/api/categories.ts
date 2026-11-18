import type { Category, CategoryInput, Paginated, TransactionType } from '@/types/api'
import { http } from './http'
import { toPaginated } from './pagination'

export async function listCategories(
  params: { type?: TransactionType; page?: number; 'per-page'?: number } = {},
): Promise<Paginated<Category>> {
  return toPaginated(await http.get<Category[]>('/categories', { params }))
}

export async function createCategory(input: CategoryInput): Promise<Category> {
  const { data } = await http.post<Category>('/categories', input)
  return data
}

export async function updateCategory(id: number, input: Partial<CategoryInput>): Promise<Category> {
  const { data } = await http.patch<Category>(`/categories/${id}`, input)
  return data
}

export async function deleteCategory(id: number): Promise<void> {
  await http.delete(`/categories/${id}`)
}
