import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchHealth } from '@/api/health'
import type { HealthResponse } from '@/types/api'

export const useHealthStore = defineStore('health', () => {
  const health = ref<HealthResponse | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load() {
    loading.value = true
    error.value = null
    try {
      health.value = await fetchHealth()
    } catch {
      health.value = null
      error.value = 'API недоступен'
    } finally {
      loading.value = false
    }
  }

  return { health, loading, error, load }
})
