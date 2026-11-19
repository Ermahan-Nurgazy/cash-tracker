import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import * as authApi from '@/api/auth'
import { clearToken, setToken } from '@/api/token'
import { useAuthStore } from '@/stores/auth'
import { formatTimestamp } from '@/utils/format'
import UserMenu from '../UserMenu.vue'

vi.mock('@/api/auth')

const Stub = { template: '<div />' }
// 19.11.2026 12:00 по UTC: середина дня, чтобы дата не сдвигалась в любом часовом поясе
const CREATED_AT = Date.UTC(2026, 10, 19, 12) / 1000

let mounted: VueWrapper | null = null

async function mountMenu() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: Stub },
      { path: '/login', name: 'login', component: Stub },
    ],
  })
  await router.push('/')

  mounted = mount(UserMenu, { global: { plugins: [PrimeVue, router] }, attachTo: document.body })
  return { wrapper: mounted, router }
}

async function open(wrapper: VueWrapper) {
  await wrapper.find('[data-test="user-menu"]').trigger('click')
  await flushPromises()
}

const dom = (test: string) => document.body.querySelector<HTMLElement>(`[data-test="${test}"]`)

describe('UserMenu', () => {
  afterEach(() => {
    mounted?.unmount()
    mounted = null
  })

  beforeEach(async () => {
    localStorage.clear()
    clearToken()
    setToken('tok')
    setActivePinia(createPinia())
    vi.resetAllMocks()
    vi.mocked(authApi.fetchMe).mockResolvedValue({
      id: 1,
      email: 'anna@example.com',
      created_at: CREATED_AT,
      updated_at: CREATED_AT,
    })
    await useAuthStore().restore()
  })

  it('показывает аватар с первой буквой email', async () => {
    const { wrapper } = await mountMenu()

    expect(wrapper.find('.p-avatar').text()).toBe('A')
    expect(wrapper.find('[data-test="user-email"]').text()).toBe('anna@example.com')
  })

  it('открывает панель с email и датой регистрации', async () => {
    const { wrapper } = await mountMenu()
    expect(dom('user-panel')).toBeNull()

    await open(wrapper)

    expect(dom('profile-email')?.textContent).toBe('anna@example.com')
    expect(dom('profile-since')?.textContent).toContain(formatTimestamp(CREATED_AT))
    expect(dom('profile-since')?.textContent).toContain('19.11.2026')
  })

  it('переключает тему', async () => {
    const { wrapper } = await mountMenu()
    const before = document.documentElement.classList.contains('app-dark')

    await open(wrapper)
    dom('theme-toggle')?.click()
    await flushPromises()

    expect(document.documentElement.classList.contains('app-dark')).toBe(!before)
  })

  it('выход очищает сессию и ведёт на страницу входа', async () => {
    vi.mocked(authApi.logout).mockResolvedValue()
    const { wrapper, router } = await mountMenu()

    await open(wrapper)
    dom('logout')?.click()
    await flushPromises()

    expect(authApi.logout).toHaveBeenCalledOnce()
    expect(useAuthStore().isAuthenticated).toBe(false)
    expect(router.currentRoute.value.name).toBe('login')
  })
})
