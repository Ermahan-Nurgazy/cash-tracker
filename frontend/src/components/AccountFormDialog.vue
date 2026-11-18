<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, reactive, ref, watch } from 'vue'
import { toApiError } from '@/api/errors'
import { useAccountsStore } from '@/stores/accounts'
import type { Account } from '@/types/api'
import { toMoneyString } from '@/utils/format'
import { CURRENCIES } from '@/utils/palette'

const props = defineProps<{
  visible: boolean
  /** Редактируемый счёт; null — создание нового */
  account: Account | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: []
}>()

const store = useAccountsStore()

const form = reactive<{ name: string; currency: string; initialBalance: number }>({
  name: '',
  currency: 'KZT',
  initialBalance: 0,
})

const errors = reactive<Record<string, string>>({})
const formError = ref('')
const saving = ref(false)

const isEdit = computed(() => props.account !== null)

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    Object.keys(errors).forEach((key) => delete errors[key])
    formError.value = ''

    const account = props.account
    Object.assign(
      form,
      account === null
        ? { name: '', currency: 'KZT', initialBalance: 0 }
        : {
            name: account.name,
            currency: account.currency,
            initialBalance: Number(account.initial_balance),
          },
    )
  },
  { immediate: true },
)

async function save() {
  formError.value = ''
  Object.keys(errors).forEach((key) => delete errors[key])

  const currency = form.currency.trim().toUpperCase()

  if (form.name.trim() === '') errors.name = 'Введите название.'
  if (!/^[A-Z]{3}$/.test(currency))
    errors.currency = 'Код валюты: три латинские буквы, например KZT.'
  if (Object.keys(errors).length > 0) return

  const payload = {
    name: form.name.trim(),
    currency,
    initial_balance: toMoneyString(form.initialBalance ?? 0),
  }

  saving.value = true
  try {
    if (props.account) {
      await store.update(props.account.id, payload)
    } else {
      await store.create(payload)
    }
    emit('saved')
    emit('update:visible', false)
  } catch (e) {
    const apiError = toApiError(e)
    if (apiError.isValidation && Object.keys(apiError.errors).length > 0) {
      Object.assign(errors, apiError.errors)
    } else {
      formError.value = apiError.message
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="isEdit ? 'Редактирование счёта' : 'Новый счёт'"
    :style="{ width: '24rem', maxWidth: '95vw' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form class="form" novalidate @submit.prevent="save">
      <Message v-if="formError" severity="error" :closable="false" data-test="form-error">
        {{ formError }}
      </Message>

      <div class="field">
        <label for="acc-name">Название</label>
        <InputText
          id="acc-name"
          v-model="form.name"
          maxlength="100"
          :invalid="!!errors.name"
          fluid
        />
        <small v-if="errors.name" class="error" data-test="error-name">{{ errors.name }}</small>
      </div>

      <div class="field">
        <label for="acc-currency">Валюта</label>
        <Select
          v-model="form.currency"
          input-id="acc-currency"
          :options="CURRENCIES"
          editable
          :invalid="!!errors.currency"
          fluid
        />
        <small v-if="errors.currency" class="error" data-test="error-currency">
          {{ errors.currency }}
        </small>
      </div>

      <div class="field">
        <label for="acc-balance">Начальный остаток</label>
        <InputNumber
          v-model="form.initialBalance"
          input-id="acc-balance"
          locale="ru-RU"
          :min-fraction-digits="2"
          :max-fraction-digits="2"
          :invalid="!!errors.initial_balance"
          fluid
        />
        <small v-if="errors.initial_balance" class="error" data-test="error-initial_balance">
          {{ errors.initial_balance }}
        </small>
      </div>

      <div class="actions">
        <Button
          type="button"
          label="Отмена"
          severity="secondary"
          text
          @click="emit('update:visible', false)"
        />
        <Button type="submit" :label="isEdit ? 'Сохранить' : 'Добавить'" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.error {
  color: var(--p-red-500);
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
