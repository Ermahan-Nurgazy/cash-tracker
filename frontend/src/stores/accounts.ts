import { defineStore } from 'pinia'
import { ref } from 'vue'
import * as accountsApi from '@/api/accounts'
import { fetchAllPages } from '@/api/pagination'
import type { Account, AccountInput } from '@/types/api'

/** Справочник счетов: нужен формам и фильтрам, поэтому грузится один раз и обновляется по требованию. */
export const useAccountsStore = defineStore('accounts', () => {
  const items = ref<Account[]>([])
  const loaded = ref(false)
  const loading = ref(false)

  async function load(force = false) {
    if (!force && (loaded.value || loading.value)) {
      return
    }

    loading.value = true
    try {
      items.value = await fetchAllPages((page) =>
        accountsApi.listAccounts({ page, 'per-page': 100 }),
      )
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function create(input: AccountInput) {
    await accountsApi.createAccount(input)
    await load(true)
  }

  async function update(id: number, input: Partial<AccountInput>) {
    await accountsApi.updateAccount(id, input)
    await load(true)
  }

  async function remove(id: number) {
    await accountsApi.deleteAccount(id)
    await load(true)
  }

  function reset() {
    items.value = []
    loaded.value = false
  }

  return { items, loaded, loading, load, create, update, remove, reset }
})
