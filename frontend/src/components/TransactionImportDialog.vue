<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { computed, ref, watch } from 'vue'
import { importTransactions } from '@/api/transactions'
import { toApiError } from '@/api/errors'

const MAX_BYTES = 1024 * 1024

const props = defineProps<{ visible: boolean }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  imported: [count: number]
}>()

const file = ref<File | null>(null)
const uploading = ref(false)
const formError = ref('')
const rowErrors = ref<[string, string][]>([])

const fileLabel = computed(() => (file.value ? file.value.name : 'Файл не выбран'))

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    file.value = null
    formError.value = ''
    rowErrors.value = []
  },
  { immediate: true },
)

function onPick(event: Event) {
  const picked = (event.target as HTMLInputElement).files?.[0] ?? null
  file.value = picked
  formError.value = ''
  rowErrors.value = []

  if (picked && picked.size > MAX_BYTES) {
    formError.value = 'Файл слишком большой: максимум 1 МБ.'
    file.value = null
  }
}

async function upload() {
  if (!file.value) {
    formError.value = 'Выберите CSV-файл.'
    return
  }

  formError.value = ''
  rowErrors.value = []
  uploading.value = true

  try {
    const result = await importTransactions(file.value)
    emit('imported', result.imported)
    emit('update:visible', false)
  } catch (e) {
    const apiError = toApiError(e)
    formError.value = apiError.message
    rowErrors.value = Object.entries(apiError.errors)
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    header="Импорт операций из CSV"
    :style="{ width: '34rem', maxWidth: '95vw' }"
    @update:visible="emit('update:visible', $event)"
  >
    <div class="body">
      <p class="hint">
        Столбцы: <b>Дата, Тип, Сумма, Счёт, Категория</b>, по желанию <b>Валюта, Заметка</b>. Тип:
        «расход» или «доход». Счета и категории должны уже существовать, названия сверяются без
        учёта регистра. Подойдёт файл, который создаёт экспорт. Если в файле есть ошибки, не
        сохраняется ничего. Повторный импорт того же файла создаст дубликаты.
      </p>

      <div class="picker">
        <label class="picker__button">
          <input
            type="file"
            accept=".csv,text/csv"
            class="picker__input"
            data-test="file"
            @change="onPick"
          />
          <i class="pi pi-folder-open" /> Выбрать файл
        </label>
        <span class="picker__name" data-test="file-name">{{ fileLabel }}</span>
      </div>

      <Message v-if="formError" severity="error" :closable="false" data-test="form-error">
        {{ formError }}
      </Message>

      <ul v-if="rowErrors.length > 0" class="errors" data-test="row-errors">
        <li v-for="[row, message] in rowErrors" :key="row">
          <b>{{ row }}:</b> {{ message }}
        </li>
      </ul>

      <div class="actions">
        <Button
          type="button"
          label="Отмена"
          severity="secondary"
          text
          @click="emit('update:visible', false)"
        />
        <Button
          label="Импортировать"
          icon="pi pi-upload"
          :loading="uploading"
          data-test="upload"
          @click="upload"
        />
      </div>
    </div>
  </Dialog>
</template>

<style scoped>
.body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.hint {
  margin: 0;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}

.picker {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.picker__button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.875rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.5rem);
  cursor: pointer;
}

.picker__button:focus-within {
  outline: 2px solid var(--p-primary-color);
}

/* Нативное поле скрыто визуально, но остаётся доступным с клавиатуры */
.picker__input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
}

.picker__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--p-text-muted-color);
}

.errors {
  max-height: 14rem;
  margin: 0;
  padding: 0.5rem 0.75rem 0.5rem 1.5rem;
  overflow: auto;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.5rem);
  font-size: 0.875rem;
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
