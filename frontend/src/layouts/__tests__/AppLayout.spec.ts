import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import * as authApi from '@/api/auth'
import { clearToken, setToken } from '@/api/token'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '../AppLayout.vue'
import { navItems } from '../nav'

vi.mock('@/api/auth')

const Stub = { template: '<div />' }

async function mountLayout() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/',
        component: AppLayout,
        children: [
          { path: '', name: 'dashboard', component: Stub, meta: { title: 'Дашборд' } },
          { path: 'transactions', name: 'transactions', component: Stub },
          { path: 'categories', name: 'categories', component: Stub },
          { path: 'budgets', name: 'budgets', component: Stub },
          { path: 'accounts', name: 'accounts', component: Stub },
        ],
      },
      { path: '/login', name: 'login', component: Stub },
    ],
  })
  await router.push('/')

  const wrapper = mount(AppLayout, { global: { plugins: [PrimeVue, router] } })
  return { wrapper, router }
}

describe('AppLayout', () => {
  beforeEach(() => {
    localStorage.clear()
    clearToken()
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('показывает все разделы меню и заголовок страницы', async () => {
    const { wrapper } = await mountLayout()
    const sidebar = wrapper.find('[data-test="sidebar"]')

    for (const item of navItems) {
      expect(sidebar.text()).toContain(item.label)
    }
    expect(wrapper.find('.layout__title').text()).toBe('Дашборд')
  })

  it('подсвечивает активный раздел', async () => {
    const { wrapper, router } = await mountLayout()

    await router.push('/categories')
    await flushPromises()

    const active = wrapper.find('[data-test="sidebar"] .layout__link--active')
    expect(active.text()).toContain('Категории')
  })

  it('показывает email пользователя', async () => {
    setToken('tok')
    setActivePinia(createPinia())
    vi.mocked(authApi.fetchMe).mockResolvedValue({
      id: 1,
      email: 'a@example.com',
      created_at: 1,
      updated_at: 1,
    })
    await useAuthStore().restore()

    const { wrapper } = await mountLayout()

    expect(wrapper.find('[data-test="user-email"]').text()).toBe('a@example.com')
  })

  it('переключает тему', async () => {
    const { wrapper } = await mountLayout()
    const before = document.documentElement.classList.contains('app-dark')

    await wrapper.find('[data-test="theme-toggle"]').trigger('click')
    await flushPromises()

    expect(document.documentElement.classList.contains('app-dark')).toBe(!before)
  })

  it('выход очищает сессию и ведёт на страницу входа', async () => {
    setToken('tok')
    setActivePinia(createPinia())
    vi.mocked(authApi.logout).mockResolvedValue()
    const { wrapper, router } = await mountLayout()

    await wrapper.find('[data-test="logout"]').trigger('click')
    await flushPromises()

    expect(authApi.logout).toHaveBeenCalledOnce()
    expect(useAuthStore().isAuthenticated).toBe(false)
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('кнопка бургер открывает выдвижное меню', async () => {
    const { wrapper } = await mountLayout()

    await wrapper.find('[data-test="burger"]').trigger('click')
    await flushPromises()

    expect(document.body.textContent).toContain('Cash Tracker')
    // Панель выводится в body, а боковое меню остаётся внутри обёртки
    expect(document.body.querySelectorAll('.layout__link')).toHaveLength(navItems.length)
    wrapper.unmount()
  })
})
