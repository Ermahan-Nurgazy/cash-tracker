import { createRouter, createWebHistory, type RouteLocationNormalized } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    /** Страница доступна только после входа */
    requiresAuth?: boolean
    /** Заголовок страницы в шапке */
    title?: string
    /** Шаг плана, на котором страница будет реализована (для заглушек) */
    step?: string
    /** Страница только для гостей (вход, регистрация) */
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
          meta: { title: 'Дашборд', step: '4.6' },
        },
        {
          path: 'transactions',
          name: 'transactions',
          component: () => import('@/views/TransactionsView.vue'),
          meta: { title: 'Операции', step: '4.4' },
        },
        {
          path: 'categories',
          name: 'categories',
          component: () => import('@/views/CategoriesView.vue'),
          meta: { title: 'Категории', step: '4.5' },
        },
        {
          path: 'accounts',
          name: 'accounts',
          component: () => import('@/views/AccountsView.vue'),
          meta: { title: 'Счета', step: '4.5' },
        },
      ],
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'dashboard' },
    },
  ],
})

/** Куда вернуть пользователя после входа: только внутренние пути, иначе возможен редирект на чужой сайт. */
export function safeRedirect(value: RouteLocationNormalized['query'][string] | undefined): string {
  const target = Array.isArray(value) ? value[0] : value
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//')
    ? target
    : '/'
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.isAuthenticated) {
    await auth.restore()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return safeRedirect(to.query.redirect)
  }

  return true
})

export default router
