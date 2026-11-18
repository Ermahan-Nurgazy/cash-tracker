import Aura from '@primeuix/themes/aura'
import { createPinia } from 'pinia'
import 'primeicons/primeicons.css'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import { createApp } from 'vue'
import { setUnauthorizedHandler } from '@/api/http'
import './assets/main.css'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useThemeStore } from './stores/theme'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      darkModeSelector: '.app-dark',
    },
  },
})

app.use(ConfirmationService)
app.use(ToastService)

// Тема применяется до монтирования, чтобы не мигать светлым фоном
useThemeStore(pinia)

// Сервер отклонил токен: забываем сессию и отправляем на вход с возвратом на текущую страницу
setUnauthorizedHandler(() => {
  useAuthStore(pinia).reset()
  const current = router.currentRoute.value
  if (current.name !== 'login' && current.name !== 'register') {
    void router.replace({
      name: 'login',
      query: current.fullPath === '/' ? {} : { redirect: current.fullPath },
    })
  }
})

app.mount('#app')
