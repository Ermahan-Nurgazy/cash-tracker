<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, reactive, ref, watch } from 'vue'
import { toApiError } from '@/api/errors'
import { useBudgetsStore } from '@/stores/budgets'
import { useCategoriesStore } from '@/stores/categories'
import type { Budget } from '@/types/api'
import { toMoneyString } from '@/utils/format'
import { monthLabel, type YearMonth } from '@/utils/months'
import { CURRENCIES } from '@/utils/palette'

const props = defineProps<{
  visible: boolean
  /** Месяц, на который создаётся бюджет */
  month: YearMonth
  /** Редактируемый бюджет; null — создание нового */
  budget: Budget | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: []
}>()

const store = useBudgetsStore()
const categories = useCategoriesStore()

const form = reactive<{ categoryId: number | null; currency: string; limit: number | null }>({
  categoryId: null,
  currency: 'KZT',
  limit: null,
})

const errors = reactive<Record<string, string>>({})
const formError = ref('')
const saving = ref(false)

const isEdit = computed(() => props.budget !== null)
const expenseCategories = computed(() => categories.byType('expense'))

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    Object.keys(errors).forEach((key) => delete errors[key])
    formError.value = ''

    const budget = props.budget
    Object.assign(
      form,
      budget === null
        ? { categoryId: null, currency: 'KZT', limit: null }
        : {
            categoryId: budget.category_id,
            currency: budget.currency,
            limit: Number(budget.limit),
          },
    )
  },
  { immediate: true },
)

async function save() {
  formError.value = ''
  Object.keys(errors).forEach((key) => delete errors[key])

  if (form.categoryId === null) errors.category_id = 'Выберите категорию.'
  if (form.limit === null || !(form.limit > 0)) errors.limit = 'Укажите лимит больше нуля.'
  if (Object.keys(errors).length > 0) return

  const payload = {
    category_id: form.categoryId as number,
    month: props.month,
    currency: form.currency,
    limit: toMoneyString(form.limit as number),
  }

  saving.value = true
  try {
    if (props.budget) {
      await store.update(props.budget.id, payload)
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
    :header="isEdit ? 'Редактирование бюджета' : 'Новый бюджет'"
    :style="{ width: '26rem', maxWidth: '95vw' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form class="form" novalidate @submit.prevent="save">
      <Message v-if="formError" severity="error" :closable="false" data-test="form-error">
        {{ formError }}
      </Message>

      <p class="period" data-test="period">{{ monthLabel(month) }}</p>

      <div class="field">
        <label for="bud-category">Категория расходов</label>
        <Select
          v-model="form.categoryId"
          input-id="bud-category"
          :options="expenseCategories"
          option-label="name"
          option-value="id"
          placeholder="Выберите категорию"
          :invalid="!!errors.category_id"
          fluid
        />
        <small v-if="errors.category_id" class="error" data-test="error-category_id">
          {{ errors.category_id }}
        </small>
      </div>

      <div class="field">
        <label for="bud-currency">Валюта</label>
        <Select
          v-model="form.currency"
          input-id="bud-currency"
          :options="CURRENCIES"
          :invalid="!!errors.currency"
          fluid
        />
        <small v-if="errors.currency" class="error" data-test="error-currency">
          {{ errors.currency }}
        </small>
      </div>

      <div class="field">
        <label for="bud-limit">Лимит, {{ form.currency }}</label>
        <InputNumber
          v-model="form.limit"
          input-id="bud-limit"
          :min-fraction-digits="2"
          :max-fraction-digits="2"
          :min="0"
          locale="ru-RU"
          :invalid="!!errors.limit"
          fluid
        />
        <small v-if="errors.limit" class="error" data-test="error-limit">{{ errors.limit }}</small>
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

.period {
  margin: 0;
  color: var(--p-text-muted-color);
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
