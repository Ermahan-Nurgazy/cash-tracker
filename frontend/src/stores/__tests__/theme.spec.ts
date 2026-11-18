import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { DARK_CLASS, useThemeStore } from '../theme'

function mockSystemDark(dark: boolean) {
  vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: dark }))
  window.matchMedia = globalThis.matchMedia
}

describe('theme store', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.classList.remove(DARK_CLASS)
    setActivePinia(createPinia())
  })

  it('следует системной теме, если выбора нет', () => {
    mockSystemDark(true)

    const store = useThemeStore()

    expect(store.isDark).toBe(true)
    expect(document.documentElement.classList.contains(DARK_CLASS)).toBe(true)
  })

  it('сохранённый выбор важнее системной темы', () => {
    mockSystemDark(true)
    localStorage.setItem('cash-tracker.theme', 'light')

    const store = useThemeStore()

    expect(store.isDark).toBe(false)
    expect(document.documentElement.classList.contains(DARK_CLASS)).toBe(false)
  })

  it('переключение меняет класс и запоминает выбор', async () => {
    mockSystemDark(false)
    const store = useThemeStore()

    store.toggle()
    await Promise.resolve()

    expect(store.isDark).toBe(true)
    expect(document.documentElement.classList.contains(DARK_CLASS)).toBe(true)
    expect(localStorage.getItem('cash-tracker.theme')).toBe('dark')

    store.toggle()
    await Promise.resolve()

    expect(document.documentElement.classList.contains(DARK_CLASS)).toBe(false)
    expect(localStorage.getItem('cash-tracker.theme')).toBe('light')
  })
})
