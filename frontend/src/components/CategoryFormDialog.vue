<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import { computed, reactive, ref, watch } from 'vue'
import { toApiError } from '@/api/errors'
import { useCategoriesStore } from '@/stores/categories'
import type { Category, TransactionType } from '@/types/api'
import { CATEGORY_COLORS, CATEGORY_ICONS } from '@/utils/palette'

const props = defineProps<{
  visible: boolean
  /** Редактируемая категория; null — создание новой */
  category: Category | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: []
}>()

const store = useCategoriesStore()

const typeOptions = [
  { label: 'Расход', value: 'expense' },
  { label: 'Доход', value: 'income' },
]

const form = reactive<{
  name: string
  type: TransactionType
  color: string | null
  icon: string | null
}>({ name: '', type: 'expense', color: null, icon: null })

const errors = reactive<Record<string, string>>({})
const formError = ref('')
const saving = ref(false)

const isEdit = computed(() => props.category !== null)

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    Object.keys(errors).forEach((key) => delete errors[key])
    formError.value = ''

    const category = props.category
    Object.assign(
      form,
      category === null
        ? { name: '', type: 'expense', color: null, icon: null }
        : {
            name: category.name,
            type: category.type,
            color: category.color,
            icon: category.icon,
          },
    )
  },
  { immediate: true },
)

async function save() {
  formError.value = ''
  Object.keys(errors).forEach((key) => delete errors[key])

  if (form.name.trim() === '') {
    errors.name = 'Введите название.'
    return
  }

  const payload = {
    name: form.name.trim(),
    type: form.type,
    color: form.color,
    icon: form.icon,
  }

  saving.value = true
  try {
    if (props.category) {
      await store.update(props.category.id, payload)
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
    :header="isEdit ? 'Редактирование категории' : 'Новая категория'"
    :style="{ width: '26rem', maxWidth: '95vw' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form class="form" novalidate @submit.prevent="save">
      <Message v-if="formError" severity="error" :closable="false" data-test="form-error">
        {{ formError }}
      </Message>

      <div class="field">
        <label for="cat-name">Название</label>
        <InputText
          id="cat-name"
          v-model="form.name"
          maxlength="100"
          :invalid="!!errors.name"
          fluid
        />
        <small v-if="errors.name" class="error" data-test="error-name">{{ errors.name }}</small>
      </div>

      <div class="field">
        <label>Тип</label>
        <SelectButton
          v-model="form.type"
          :options="typeOptions"
          option-label="label"
          option-value="value"
          :allow-empty="false"
        />
        <small v-if="errors.type" class="error" data-test="error-type">{{ errors.type }}</small>
      </div>

      <div class="field">
        <label>Цвет</label>
        <div class="grid">
          <button
            type="button"
            class="swatch swatch--none"
            :class="{ 'swatch--active': form.color === null }"
            aria-label="Без цвета"
            data-test="color-none"
            @click="form.color = null"
          >
            <i class="pi pi-ban" />
          </button>
          <button
            v-for="color in CATEGORY_COLORS"
            :key="color"
            type="button"
            class="swatch"
            :class="{ 'swatch--active': form.color?.toLowerCase() === color.toLowerCase() }"
            :style="{ background: color }"
            :aria-label="`Цвет ${color}`"
            data-test="color"
            @click="form.color = color"
          />
        </div>
        <small v-if="errors.color" class="error" data-test="error-color">{{ errors.color }}</small>
      </div>

      <div class="field">
        <label>Иконка</label>
        <div class="grid">
          <button
            type="button"
            class="swatch swatch--none"
            :class="{ 'swatch--active': form.icon === null }"
            aria-label="Без иконки"
            data-test="icon-none"
            @click="form.icon = null"
          >
            <i class="pi pi-ban" />
          </button>
          <button
            v-for="icon in CATEGORY_ICONS"
            :key="icon"
            type="button"
            class="swatch swatch--none"
            :class="{ 'swatch--active': form.icon === icon }"
            :aria-label="`Иконка ${icon}`"
            data-test="icon"
            @click="form.icon = icon"
          >
            <i :class="['pi', icon]" />
          </button>
        </div>
        <small v-if="errors.icon" class="error" data-test="error-icon">{{ errors.icon }}</small>
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

.grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.swatch {
  display: inline-flex;
  width: 2rem;
  height: 2rem;
  align-items: center;
  justify-content: center;
  border: 2px solid transparent;
  border-radius: 50%;
  color: var(--p-text-color);
  cursor: pointer;
}

.swatch--none {
  border-color: var(--p-content-border-color);
  background: var(--p-content-background);
}

.swatch--active {
  border-color: var(--p-primary-color);
  box-shadow: 0 0 0 2px var(--p-primary-200, var(--p-primary-color));
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
