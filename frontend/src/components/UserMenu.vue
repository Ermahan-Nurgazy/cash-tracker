<script setup lang="ts">
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { formatTimestamp } from '@/utils/format'

const auth = useAuthStore()
const theme = useThemeStore()
const router = useRouter()

const panel = ref<InstanceType<typeof Popover> | null>(null)
const loggingOut = ref(false)

/** Первая буква email для аватара; без загруженного пользователя — значок */
const initial = computed(() => auth.user?.email.charAt(0).toUpperCase() ?? '')

function toggle(event: Event) {
  panel.value?.toggle(event)
}

async function logout() {
  // Повторные клики, пока запрос выхода не завершился, лишних запросов не создают
  if (loggingOut.value) return
  loggingOut.value = true
  try {
    await auth.logout()
    panel.value?.hide()
    await router.replace({ name: 'login' })
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <button
    type="button"
    class="user-menu__trigger"
    aria-label="Профиль"
    aria-haspopup="true"
    data-test="user-menu"
    @click="toggle"
  >
    <Avatar
      :label="initial || undefined"
      :icon="initial ? undefined : 'pi pi-user'"
      shape="circle"
      class="user-menu__avatar"
    />
    <span v-if="auth.user" class="user-menu__email" data-test="user-email">
      {{ auth.user.email }}
    </span>
    <i class="pi pi-angle-down user-menu__caret" />
  </button>

  <Popover ref="panel">
    <div class="user-menu__panel" data-test="user-panel">
      <div v-if="auth.user" class="user-menu__profile">
        <Avatar :label="initial" shape="circle" size="large" class="user-menu__avatar" />
        <div class="user-menu__info">
          <span class="user-menu__name" data-test="profile-email">{{ auth.user.email }}</span>
          <span class="user-menu__since" data-test="profile-since">
            С нами с {{ formatTimestamp(auth.user.created_at) }}
          </span>
        </div>
      </div>

      <Button
        :label="theme.isDark ? 'Светлая тема' : 'Тёмная тема'"
        :icon="theme.isDark ? 'pi pi-sun' : 'pi pi-moon'"
        severity="secondary"
        text
        fluid
        class="user-menu__action"
        data-test="theme-toggle"
        @click="theme.toggle()"
      />
      <Button
        label="Выйти"
        icon="pi pi-sign-out"
        severity="danger"
        text
        fluid
        class="user-menu__action"
        :loading="loggingOut"
        data-test="logout"
        @click="logout"
      />
    </div>
  </Popover>
</template>

<style scoped>
.user-menu__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.5rem 0.25rem 0.25rem;
  border: 0;
  border-radius: 2rem;
  background: transparent;
  color: var(--p-text-color);
  font: inherit;
  cursor: pointer;
}

.user-menu__trigger:hover {
  background: var(--p-content-hover-background);
}

.user-menu__trigger:focus-visible {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}

.user-menu__avatar {
  background: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
  font-weight: 600;
}

/* На узком экране в шапке только аватар, email — в панели */
.user-menu__email {
  display: none;
  max-width: 16rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--p-text-muted-color);
}

.user-menu__caret {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}

.user-menu__panel {
  display: flex;
  min-width: 15rem;
  flex-direction: column;
  gap: 0.25rem;
}

.user-menu__profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.25rem 0.25rem 0.75rem;
  margin-bottom: 0.25rem;
  border-bottom: 1px solid var(--p-content-border-color);
}

.user-menu__info {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.user-menu__name {
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 600;
}

.user-menu__since {
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
}

.user-menu__action {
  justify-content: flex-start;
}

@media (min-width: 992px) {
  .user-menu__email {
    display: inline;
  }
}
</style>
