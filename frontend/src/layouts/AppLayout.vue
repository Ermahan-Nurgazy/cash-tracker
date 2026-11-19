<script setup lang="ts">
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import { ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { navItems } from './nav'

const auth = useAuthStore()
const theme = useThemeStore()
const route = useRoute()
const router = useRouter()

/** Меню на узких экранах открывается в выдвижной панели */
const menuOpen = ref(false)

watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
  },
)

async function logout() {
  await auth.logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <div class="layout">
    <aside class="layout__sidebar" data-test="sidebar">
      <div class="layout__brand">
        <i class="pi pi-wallet" />
        <span>Трекер расходов</span>
      </div>
      <nav class="layout__nav" aria-label="Основное меню">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="layout__link"
          :active-class="item.name === 'dashboard' ? '' : 'layout__link--active'"
          exact-active-class="layout__link--active"
        >
          <i :class="item.icon" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
    </aside>

    <Drawer v-model:visible="menuOpen" header="Трекер расходов" class="layout__drawer">
      <nav class="layout__nav" aria-label="Основное меню">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="layout__link"
          :active-class="item.name === 'dashboard' ? '' : 'layout__link--active'"
          exact-active-class="layout__link--active"
        >
          <i :class="item.icon" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
    </Drawer>

    <div class="layout__main">
      <header class="layout__header">
        <Button
          class="layout__burger"
          icon="pi pi-bars"
          severity="secondary"
          text
          aria-label="Открыть меню"
          data-test="burger"
          @click="menuOpen = true"
        />
        <span class="layout__title">{{ route.meta.title ?? '' }}</span>

        <div class="layout__actions">
          <span v-if="auth.user" class="layout__user" data-test="user-email">
            {{ auth.user.email }}
          </span>
          <Button
            :icon="theme.isDark ? 'pi pi-sun' : 'pi pi-moon'"
            severity="secondary"
            text
            rounded
            :aria-label="theme.isDark ? 'Светлая тема' : 'Тёмная тема'"
            data-test="theme-toggle"
            @click="theme.toggle()"
          />
          <Button
            icon="pi pi-sign-out"
            severity="secondary"
            text
            rounded
            aria-label="Выйти"
            data-test="logout"
            @click="logout"
          />
        </div>
      </header>

      <main class="layout__content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
}

.layout__sidebar {
  position: sticky;
  top: 0;
  display: none;
  width: 15rem;
  height: 100vh;
  flex-shrink: 0;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem;
  border-right: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}

.layout__brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--p-primary-color);
}

.layout__nav {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.layout__link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--p-content-border-radius);
  color: var(--p-text-color);
  text-decoration: none;
}

.layout__link:hover {
  background: var(--p-content-hover-background);
}

.layout__link--active {
  background: var(--p-highlight-background);
  color: var(--p-highlight-color);
  font-weight: 600;
}

.layout__main {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
}

.layout__header {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-bottom: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}

.layout__title {
  font-size: 1.125rem;
  font-weight: 600;
}

.layout__actions {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-left: auto;
}

.layout__user {
  display: none;
  margin-right: 0.5rem;
  color: var(--p-text-muted-color);
}

.layout__content {
  flex: 1;
  padding: 1rem;
}

/* Широкий экран: постоянное боковое меню вместо выдвижной панели */
@media (min-width: 992px) {
  .layout__sidebar {
    display: flex;
  }

  .layout__burger {
    display: none;
  }

  .layout__user {
    display: inline;
  }

  .layout__content {
    padding: 1.5rem;
  }
}
</style>
