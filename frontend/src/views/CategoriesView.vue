<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { toApiError } from '@/api/errors'
import CategoryFormDialog from '@/components/CategoryFormDialog.vue'
import { useCategoriesStore } from '@/stores/categories'
import type { Category, TransactionType } from '@/types/api'

const store = useCategoriesStore()
const confirm = useConfirm()
const toast = useToast()

const filterOptions = [
  { label: 'Все', value: null },
  { label: 'Расходы', value: 'expense' },
  { label: 'Доходы', value: 'income' },
]

const typeFilter = ref<TransactionType | null>(null)
const loadError = ref('')
const dialogVisible = ref(false)
const editing = ref<Category | null>(null)

const rows = computed(() =>
  typeFilter.value === null ? store.items : store.byType(typeFilter.value),
)

async function load() {
  loadError.value = ''
  try {
    await store.load(true)
  } catch (e) {
    loadError.value = toApiError(e).message
  }
}

function openCreate() {
  editing.value = null
  dialogVisible.value = true
}

function openEdit(category: Category) {
  editing.value = category
  dialogVisible.value = true
}

function onSaved() {
  toast.add({ severity: 'success', summary: 'Сохранено', life: 2500 })
}

function askDelete(category: Category) {
  confirm.require({
    header: 'Удаление категории',
    message: `Удалить категорию «${category.name}»?`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Отмена',
    acceptLabel: 'Удалить',
    acceptProps: { severity: 'danger' },
    rejectProps: { severity: 'secondary', text: true },
    accept: async () => {
      try {
        await store.remove(category.id)
        toast.add({ severity: 'success', summary: 'Категория удалена', life: 2500 })
      } catch (e) {
        // Категория с операциями удалить нельзя (409): сервер объясняет причину
        toast.add({
          severity: 'error',
          summary: 'Не удалось удалить',
          detail: toApiError(e).message,
          life: 6000,
        })
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page__head">
      <h1 class="page__title">Категории</h1>
      <Button label="Добавить" icon="pi pi-plus" data-test="add" @click="openCreate" />
    </div>

    <SelectButton
      v-model="typeFilter"
      :options="filterOptions"
      option-label="label"
      option-value="value"
      :allow-empty="false"
      data-test="type-filter"
    />

    <Message v-if="loadError" severity="error" :closable="false" data-test="load-error">
      {{ loadError }}
      <Button label="Повторить" text size="small" @click="load" />
    </Message>

    <DataTable :value="rows" data-key="id" :loading="store.loading" responsive-layout="scroll">
      <template #empty>
        <p class="empty" data-test="empty">Категорий нет.</p>
      </template>

      <Column header="Название">
        <template #body="{ data }: { data: Category }">
          <span class="name">
            <i
              class="dot"
              :style="{ background: data.color ?? 'var(--p-surface-400)' }"
              data-test="dot"
            />
            <i v-if="data.icon" :class="['pi', data.icon]" />
            {{ data.name }}
          </span>
        </template>
      </Column>
      <Column header="Тип">
        <template #body="{ data }: { data: Category }">
          <Tag
            :severity="data.type === 'income' ? 'success' : 'secondary'"
            :value="data.type === 'income' ? 'Доход' : 'Расход'"
          />
        </template>
      </Column>
      <Column header="" body-class="actions">
        <template #body="{ data }: { data: Category }">
          <Button
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            aria-label="Редактировать"
            data-test="edit"
            @click="openEdit(data)"
          />
          <Button
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            aria-label="Удалить"
            data-test="delete"
            @click="askDelete(data)"
          />
        </template>
      </Column>
    </DataTable>

    <CategoryFormDialog v-model:visible="dialogVisible" :category="editing" @saved="onSaved" />
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.page__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.page__title {
  margin: 0;
  font-size: 1.5rem;
}

.empty {
  margin: 1rem 0;
  text-align: center;
  color: var(--p-text-muted-color);
}

.name {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
}

:deep(.actions) {
  white-space: nowrap;
  text-align: right;
}
</style>
