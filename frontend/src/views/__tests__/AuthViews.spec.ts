import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import PrimeVue from 'primevue/config'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ApiError } from '@/api/errors'
import * as authApi from '@/api/auth'
import { clearToken } from '@/api/token'
import { useAuthStore } from '@/stores/auth'
import LoginView from '../LoginView.vue'
import RegisterView from '../RegisterView.vue'

vi.mock('@/api/auth')

const session = {
  token: 'tok',
  user: { id: 1, email: 'a@example.com', created_at: '2026-01-01' },
}

async function mountView(view: typeof LoginView, path: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/transactions', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: view },
      { path: '/register', name: 'register', component: view },
    ],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = mount(view, { global: { plugins: [PrimeVue, router] } })
  return { wrapper, router }
}

async function fill(wrapper: ReturnType<typeof mount>, values: Record<string, string>) {
  for (const [id, value] of Object.entries(values)) {
    await wrapper.find(`#${id}`).setValue(value)
  }
}

describe('LoginView', () => {
  beforeEach(() => {
    clearToken()
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('после входа открывает страницу из redirect', async () => {
    vi.mocked(authApi.login).mockResolvedValue(session)
    const { wrapper, router } = await mountView(LoginView, '/login?redirect=/transactions')

    await fill(wrapper, { email: 'a@example.com', password: 'secret123' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(authApi.login).toHaveBeenCalledWith({ email: 'a@example.com', password: 'secret123' })
    expect(router.currentRoute.value.path).toBe('/transactions')
    expect(useAuthStore().isAuthenticated).toBe(true)
  })

  it('внешний redirect игнорируется: переход на главную', async () => {
    vi.mocked(authApi.login).mockResolvedValue(session)
    const { wrapper, router } = await mountView(LoginView, '/login?redirect=//evil.example')

    await fill(wrapper, { email: 'a@example.com', password: 'secret123' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/')
  })

  it('при ошибке остаётся на странице и показывает сообщение', async () => {
    vi.mocked(authApi.login).mockRejectedValue(new ApiError(401, 'Неверный email или пароль.'))
    const { wrapper, router } = await mountView(LoginView, '/login')

    await fill(wrapper, { email: 'a@example.com', password: 'wrongpass1' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/login')
    expect(wrapper.text()).toContain('Неверный email или пароль.')
    expect(useAuthStore().isAuthenticated).toBe(false)
  })
})

describe('RegisterView', () => {
  beforeEach(() => {
    clearToken()
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('регистрирует и открывает главную', async () => {
    vi.mocked(authApi.register).mockResolvedValue(session)
    const { wrapper, router } = await mountView(RegisterView, '/register')

    await fill(wrapper, {
      email: 'a@example.com',
      password: 'secret123',
      confirm: 'secret123',
    })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(authApi.register).toHaveBeenCalledWith({ email: 'a@example.com', password: 'secret123' })
    expect(router.currentRoute.value.path).toBe('/')
    expect(useAuthStore().isAuthenticated).toBe(true)
  })

  it('не регистрирует при несовпадающих паролях', async () => {
    const { wrapper } = await mountView(RegisterView, '/register')

    await fill(wrapper, { email: 'a@example.com', password: 'secret123', confirm: 'other1234' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(authApi.register).not.toHaveBeenCalled()
    expect(wrapper.find('[data-test="error-confirm"]').exists()).toBe(true)
  })
})
