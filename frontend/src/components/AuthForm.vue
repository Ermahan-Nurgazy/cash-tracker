<script setup lang="ts">
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import { computed, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { toApiError } from '@/api/errors'
import type { Credentials } from '@/types/api'

const props = defineProps<{
  mode: 'login' | 'register'
  /** Вызывается с введёнными данными; ошибки API показываются в форме */
  submit: (credentials: Credentials) => Promise<void>
}>()

const isRegister = computed(() => props.mode === 'register')

const form = reactive({ email: '', password: '', confirm: '' })
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const loading = ref(false)

function validate(): boolean {
  fieldErrors.email = ''
  fieldErrors.password = ''
  fieldErrors.confirm = ''

  if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) {
    fieldErrors.email = 'Введите корректный email.'
  }
  if (form.password === '') {
    fieldErrors.password = 'Введите пароль.'
  } else if (isRegister.value && form.password.length < 8) {
    fieldErrors.password = 'Пароль должен быть не короче 8 символов.'
  }
  if (isRegister.value && form.confirm !== form.password) {
    fieldErrors.confirm = 'Пароли не совпадают.'
  }

  return !fieldErrors.email && !fieldErrors.password && !fieldErrors.confirm
}

async function onSubmit() {
  formError.value = ''
  if (!validate()) {
    return
  }

  loading.value = true
  try {
    await props.submit({ email: form.email.trim(), password: form.password })
  } catch (error) {
    const apiError = toApiError(error)
    if (apiError.isValidation && Object.keys(apiError.errors).length > 0) {
      fieldErrors.email = apiError.errors.email ?? ''
      fieldErrors.password = apiError.errors.password ?? ''
    } else {
      formError.value = apiError.message
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth">
    <Card class="auth__card">
      <template #title>{{ isRegister ? 'Регистрация' : 'Вход' }}</template>
      <template #subtitle>Cash Tracker</template>
      <template #content>
        <form class="auth__form" novalidate @submit.prevent="onSubmit">
          <Message v-if="formError" severity="error" :closable="false" data-test="form-error">
            {{ formError }}
          </Message>

          <div class="auth__field">
            <label for="email">Email</label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              :invalid="!!fieldErrors.email"
              fluid
            />
            <small v-if="fieldErrors.email" class="auth__error" data-test="error-email">
              {{ fieldErrors.email }}
            </small>
          </div>

          <div class="auth__field">
            <label for="password">Пароль</label>
            <Password
              v-model="form.password"
              input-id="password"
              :feedback="false"
              toggle-mask
              :autocomplete="isRegister ? 'new-password' : 'current-password'"
              :invalid="!!fieldErrors.password"
              fluid
            />
            <small v-if="fieldErrors.password" class="auth__error" data-test="error-password">
              {{ fieldErrors.password }}
            </small>
          </div>

          <div v-if="isRegister" class="auth__field">
            <label for="confirm">Повторите пароль</label>
            <Password
              v-model="form.confirm"
              input-id="confirm"
              :feedback="false"
              toggle-mask
              autocomplete="new-password"
              :invalid="!!fieldErrors.confirm"
              fluid
            />
            <small v-if="fieldErrors.confirm" class="auth__error" data-test="error-confirm">
              {{ fieldErrors.confirm }}
            </small>
          </div>

          <Button
            type="submit"
            :label="isRegister ? 'Зарегистрироваться' : 'Войти'"
            :loading="loading"
            fluid
          />
        </form>
      </template>
      <template #footer>
        <p class="auth__switch">
          <template v-if="isRegister">
            Уже есть аккаунт? <RouterLink :to="{ name: 'login' }">Войти</RouterLink>
          </template>
          <template v-else>
            Нет аккаунта? <RouterLink :to="{ name: 'register' }">Зарегистрироваться</RouterLink>
          </template>
        </p>
      </template>
    </Card>
  </main>
</template>

<style scoped>
.auth {
  display: flex;
  min-height: 100vh;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.auth__card {
  width: 100%;
  max-width: 24rem;
}

.auth__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.auth__field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.auth__error {
  color: var(--p-red-500);
}

.auth__switch {
  margin: 0;
  text-align: center;
}
</style>
