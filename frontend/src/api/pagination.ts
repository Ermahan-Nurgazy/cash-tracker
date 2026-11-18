import type { AxiosResponse } from 'axios'
import type { Paginated } from '@/types/api'

/** Загружает все страницы списка (для справочников: счета, категории). */
export async function fetchAllPages<T>(
  fetchPage: (page: number) => Promise<Paginated<T>>,
): Promise<T[]> {
  const first = await fetchPage(1)
  const items = [...first.items]

  for (let page = 2; page <= first.pageCount; page++) {
    items.push(...(await fetchPage(page)).items)
  }

  return items
}

/** Собирает страницу из тела ответа и заголовков X-Pagination-*. */
export function toPaginated<T>(response: AxiosResponse<T[]>): Paginated<T> {
  const header = (name: string, fallback: number): number => {
    const value = Number(response.headers[name])
    return Number.isFinite(value) && value > 0 ? value : fallback
  }

  return {
    items: response.data,
    total: header('x-pagination-total-count', response.data.length),
    page: header('x-pagination-current-page', 1),
    pageCount: header('x-pagination-page-count', 1),
    perPage: header('x-pagination-per-page', response.data.length || 1),
  }
}
