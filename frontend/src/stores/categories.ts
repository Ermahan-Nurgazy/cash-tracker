import { defineStore } from 'pinia'
import { ref } from 'vue'
import * as categoriesApi from '@/api/categories'
import { fetchAllPages } from '@/api/pagination'
import type { Category, CategoryInput, TransactionType } from '@/types/api'

/** Справочник категорий: грузится один раз и обновляется по требованию. */
export const useCategoriesStore = defineStore('categories', () => {
  const items = ref<Category[]>([])
  const loaded = ref(false)
  const loading = ref(false)

  async function load(force = false) {
    if (!force && (loaded.value || loading.value)) {
      return
    }

    loading.value = true
    try {
      items.value = await fetchAllPages((page) =>
        categoriesApi.listCategories({ page, 'per-page': 100 }),
      )
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  function byType(type: TransactionType): Category[] {
    return items.value.filter((category) => category.type === type)
  }

  async function create(input: CategoryInput) {
    await categoriesApi.createCategory(input)
    await load(true)
  }

  async function update(id: number, input: Partial<CategoryInput>) {
    await categoriesApi.updateCategory(id, input)
    await load(true)
  }

  async function remove(id: number) {
    await categoriesApi.deleteCategory(id)
    await load(true)
  }

  function reset() {
    items.value = []
    loaded.value = false
  }

  return { items, loaded, loading, load, byType, create, update, remove, reset }
})
