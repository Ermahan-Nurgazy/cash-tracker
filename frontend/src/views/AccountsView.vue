<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { toApiError } from '@/api/errors'
import AccountFormDialog from '@/components/AccountFormDialog.vue'
import { useAccountsStore } from '@/stores/accounts'
import type { Account } from '@/types/api'
import { formatMoney } from '@/utils/format'

const store = useAccountsStore()
const confirm = useConfirm()
const toast = useToast()

const loadError = ref('')
const dialogVisible = ref(false)
const editing = ref<Account | null>(null)

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

function openEdit(account: Account) {
  editing.value = account
  dialogVisible.value = true
}

function onSaved() {
  toast.add({ severity: 'success', summary: 'Сохранено', life: 2500 })
}

function askDelete(account: Account) {
  confirm.require({
    header: 'Удаление счёта',
    message: `Удалить счёт «${account.name}»?`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Отмена',
    acceptLabel: 'Удалить',
    acceptProps: { severity: 'danger' },
    rejectProps: { severity: 'secondary', text: true },
    accept: async () => {
      try {
        await store.remove(account.id)
        toast.add({ severity: 'success', summary: 'Счёт удалён', life: 2500 })
      } catch (e) {
        // Счёт с операциями удалить нельзя (409): сервер объясняет причину
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
      <h1 class="page__title">Счета</h1>
      <Button label="Добавить" icon="pi pi-plus" data-test="add" @click="openCreate" />
    </div>

    <Message v-if="loadError" severity="error" :closable="false" data-test="load-error">
      {{ loadError }}
      <Button label="Повторить" text size="small" @click="load" />
    </Message>

    <DataTable
      :value="store.items"
      data-key="id"
      :loading="store.loading"
      responsive-layout="scroll"
    >
      <template #empty>
        <p class="empty" data-test="empty">
          Счетов пока нет. Добавьте первый, чтобы вести операции.
        </p>
      </template>

      <Column field="name" header="Название" />
      <Column field="currency" header="Валюта" />
      <Column header="Начальный остаток" header-class="text-right" body-class="text-right">
        <template #body="{ data }: { data: Account }">
          {{ formatMoney(data.initial_balance, data.currency) }}
        </template>
      </Column>
      <Column header="" body-class="actions">
        <template #body="{ data }: { data: Account }">
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

    <AccountFormDialog v-model:visible="dialogVisible" :account="editing" @saved="onSaved" />
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

:deep(.text-right) {
  text-align: right;
}

:deep(.text-right .p-datatable-column-header-content) {
  justify-content: flex-end;
}

:deep(.actions) {
  white-space: nowrap;
  text-align: right;
}
</style>
