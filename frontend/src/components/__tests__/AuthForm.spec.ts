import { flushPromises, mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import AuthForm from '../AuthForm.vue'

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function mountForm(mode: 'login' | 'register', submit = vi.fn().mockResolvedValue(undefined)) {
  const wrapper = mount(AuthForm, {
    props: { mode, submit },
    global: { plugins: [PrimeVue], stubs },
  })
  return { wrapper, submit }
}

async function fill(wrapper: ReturnType<typeof mount>, values: Record<string, string>) {
  for (const [id, value] of Object.entries(values)) {
    await wrapper.find(`#${id}`).setValue(value)
  }
}

describe('AuthForm', () => {
  it('не отправляет форму с пустыми полями и показывает ошибки', async () => {
    const { wrapper, submit } = mountForm('login')

    await wrapper.find('form').trigger('submit')

    expect(submit).not.toHaveBeenCalled()
    expect(wrapper.find('[data-test="error-email"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="error-password"]').exists()).toBe(true)
  })

  it('отправляет email без пробелов и пароль при входе', async () => {
    const { wrapper, submit } = mountForm('login')

    await fill(wrapper, { email: ' a@example.com ', password: 'secret123' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(submit).toHaveBeenCalledWith({ email: 'a@example.com', password: 'secret123' })
  })

  it('при регистрации проверяет длину и совпадение паролей', async () => {
    const { wrapper, submit } = mountForm('register')

    await fill(wrapper, { email: 'a@example.com', password: 'short', confirm: 'other' })
    await wrapper.find('form').trigger('submit')

    expect(submit).not.toHaveBeenCalled()
    expect(wrapper.find('[data-test="error-password"]').text()).toContain('не короче 8')
    expect(wrapper.find('[data-test="error-confirm"]').text()).toContain('не совпадают')

    await fill(wrapper, { password: 'secret123', confirm: 'secret123' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(submit).toHaveBeenCalledWith({ email: 'a@example.com', password: 'secret123' })
  })

  it('показывает ошибки валидации сервера под полями', async () => {
    const submit = vi
      .fn()
      .mockRejectedValue(
        new ApiError(422, 'Ошибка валидации.', { email: 'Этот email уже зарегистрирован.' }),
      )
    const { wrapper } = mountForm('register', submit)

    await fill(wrapper, { email: 'a@example.com', password: 'secret123', confirm: 'secret123' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-test="error-email"]').text()).toBe('Этот email уже зарегистрирован.')
    expect(wrapper.find('[data-test="form-error"]').exists()).toBe(false)
  })

  it('показывает общее сообщение при неверном пароле', async () => {
    const submit = vi.fn().mockRejectedValue(new ApiError(401, 'Неверный email или пароль.'))
    const { wrapper } = mountForm('login', submit)

    await fill(wrapper, { email: 'a@example.com', password: 'wrongpass' })
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[data-test="form-error"]').text()).toContain('Неверный email или пароль.')
  })
})
