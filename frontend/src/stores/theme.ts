import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const KEY = 'cash-tracker.theme'
/** Класс на <html>, который включает тёмную тему PrimeVue (см. darkModeSelector в main.ts) */
export const DARK_CLASS = 'app-dark'

function readSaved(): 'light' | 'dark' | null {
  try {
    const value = localStorage.getItem(KEY)
    return value === 'light' || value === 'dark' ? value : null
  } catch {
    return null
  }
}

function prefersDark(): boolean {
  return typeof window.matchMedia === 'function'
    ? window.matchMedia('(prefers-color-scheme: dark)').matches
    : false
}

export const useThemeStore = defineStore('theme', () => {
  // Выбор пользователя важнее настройки системы
  const isDark = ref(readSaved() === null ? prefersDark() : readSaved() === 'dark')

  watch(
    isDark,
    (dark) => {
      document.documentElement.classList.toggle(DARK_CLASS, dark)
    },
    { immediate: true },
  )

  function toggle() {
    isDark.value = !isDark.value
    try {
      localStorage.setItem(KEY, isDark.value ? 'dark' : 'light')
    } catch {
      /* тема не сохранится между визитами, но переключится */
    }
  }

  return { isDark, toggle }
})
