<script setup lang="ts">
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Textarea from 'primevue/textarea'
import { computed, reactive, ref, watch } from 'vue'
import { toApiError } from '@/api/errors'
import { useAccountsStore } from '@/stores/accounts'
import { useCategoriesStore } from '@/stores/categories'
import { useTransactionsStore } from '@/stores/transactions'
import type { Transaction, TransactionType } from '@/types/api'
import { fromIsoDate, toIsoDate, toMoneyString } from '@/utils/format'

const props = defineProps<{
  visible: boolean
  /** Редактируемая операция; null — создание новой */
  transaction: Transaction | null
  /** Тип новой операции при открытии (быстрые действия «Расход» / «Доход») */
  initialType?: TransactionType
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: []
}>()

const accounts = useAccountsStore()
const categories = useCategoriesStore()
const store = useTransactionsStore()

const typeOptions = [
  { label: 'Расход', value: 'expense' },
  { label: 'Доход', value: 'income' },
]

interface FormState {
  type: TransactionType
  accountId: number | null
  categoryId: number | null
  amount: number | null
  date: Date
  note: string
}

function emptyForm(): FormState {
  return {
    type: props.initialType ?? 'expense',
    accountId: accounts.items[0]?.id ?? null,
    categoryId: null,
    amount: null,
    date: new Date(),
    note: '',
  }
}

const form = reactive<FormState>(emptyForm())
const errors = reactive<Record<string, string>>({})
const formError = ref('')
const saving = ref(false)

const isEdit = computed(() => props.transaction !== null)
const categoryOptions = computed(() => categories.byType(form.type))
const currency = computed(
  () => accounts.items.find((account) => account.id === form.accountId)?.currency ?? '',
)

// Диалог переиспользуется: при каждом открытии форма заполняется заново
watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    Object.keys(errors).forEach((key) => delete errors[key])
    formError.value = ''

    const tx = props.transaction
    Object.assign(
      form,
      tx === null
        ? emptyForm()
        : {
            type: tx.type,
            accountId: tx.account_id,
            categoryId: tx.category_id,
            amount: Number(tx.amount),
            date: fromIsoDate(tx.occurred_at),
            note: tx.note ?? '',
          },
    )
  },
  { immediate: true },
)

// Категория другого типа не подходит: при смене типа выбор сбрасывается
watch(
  () => form.type,
  () => {
    if (!categoryOptions.value.some((category) => category.id === form.categoryId)) {
      form.categoryId = null
    }
  },
)

function validate(): boolean {
  Object.keys(errors).forEach((key) => delete errors[key])

  if (form.accountId === null) errors.account_id = 'Выберите счёт.'
  if (form.categoryId === null) errors.category_id = 'Выберите категорию.'
  if (form.amount === null || form.amount <= 0) errors.amount = 'Введите сумму больше нуля.'

  return Object.keys(errors).length === 0
}

async function save() {
  formError.value = ''
  if (!validate()) return

  const payload = {
    account_id: form.accountId as number,
    category_id: form.categoryId as number,
    amount: toMoneyString(form.amount as number),
    type: form.type,
    occurred_at: toIsoDate(form.date),
    note: form.note.trim() === '' ? null : form.note.trim(),
  }

  saving.value = true
  try {
    if (props.transaction) {
      await store.update(props.transaction.id, payload)
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
    :header="isEdit ? 'Редактирование операции' : 'Новая операция'"
    :style="{ width: '28rem', maxWidth: '95vw' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form class="form" novalidate @submit.prevent="save">
      <Message v-if="formError" severity="error" :closable="false" data-test="form-error">
        {{ formError }}
      </Message>

      <SelectButton
        v-model="form.type"
        :options="typeOptions"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        data-test="type"
      />

      <div class="field">
        <label for="tx-account">Счёт</label>
        <Select
          v-model="form.accountId"
          input-id="tx-account"
          :options="accounts.items"
          option-label="name"
          option-value="id"
          placeholder="Выберите счёт"
          :invalid="!!errors.account_id"
          fluid
        />
        <small v-if="errors.account_id" class="error" data-test="error-account_id">
          {{ errors.account_id }}
        </small>
      </div>

      <div class="field">
        <label for="tx-category">Категория</label>
        <Select
          v-model="form.categoryId"
          input-id="tx-category"
          :options="categoryOptions"
          option-label="name"
          option-value="id"
          placeholder="Выберите категорию"
          :invalid="!!errors.category_id"
          filter
          fluid
        />
        <small v-if="errors.category_id" class="error" data-test="error-category_id">
          {{ errors.category_id }}
        </small>
      </div>

      <div class="field">
        <label for="tx-amount">Сумма{{ currency ? `, ${currency}` : '' }}</label>
        <InputNumber
          v-model="form.amount"
          input-id="tx-amount"
          locale="ru-RU"
          :min-fraction-digits="2"
          :max-fraction-digits="2"
          :min="0"
          :max="999999999999.99"
          :invalid="!!errors.amount"
          fluid
        />
        <small v-if="errors.amount" class="error" data-test="error-amount">
          {{ errors.amount }}
        </small>
      </div>

      <div class="field">
        <label for="tx-date">Дата</label>
        <DatePicker v-model="form.date" input-id="tx-date" date-format="dd.mm.yy" show-icon fluid />
        <small v-if="errors.occurred_at" class="error" data-test="error-occurred_at">
          {{ errors.occurred_at }}
        </small>
      </div>

      <div class="field">
        <label for="tx-note">Заметка</label>
        <Textarea id="tx-note" v-model="form.note" rows="2" maxlength="2000" auto-resize fluid />
        <small v-if="errors.note" class="error" data-test="error-note">{{ errors.note }}</small>
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
