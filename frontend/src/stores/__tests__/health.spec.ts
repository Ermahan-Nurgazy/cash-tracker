import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchHealth } from '@/api/health'
import { useHealthStore } from '../health'

vi.mock('@/api/health', () => ({ fetchHealth: vi.fn() }))

describe('health store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(fetchHealth).mockReset()
  })

  it('сохраняет ответ API', async () => {
    const response = { status: 'ok', database: 'up', time: '2026-01-01T00:00:00+00:00' } as const
    vi.mocked(fetchHealth).mockResolvedValue(response)

    const store = useHealthStore()
    await store.load()

    expect(store.health).toEqual(response)
    expect(store.error).toBeNull()
    expect(store.loading).toBe(false)
  })

  it('фиксирует ошибку, если API недоступен', async () => {
    vi.mocked(fetchHealth).mockRejectedValue(new Error('network'))

    const store = useHealthStore()
    await store.load()

    expect(store.health).toBeNull()
    expect(store.error).toBe('API недоступен')
    expect(store.loading).toBe(false)
  })
})
