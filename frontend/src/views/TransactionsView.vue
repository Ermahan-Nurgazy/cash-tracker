<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { toApiError } from '@/api/errors'
import { exportTransactions } from '@/api/transactions'
import TransactionFormDialog from '@/components/TransactionFormDialog.vue'
import TransactionImportDialog from '@/components/TransactionImportDialog.vue'
import { useAccountsStore } from '@/stores/accounts'
import { useCategoriesStore } from '@/stores/categories'
import { PER_PAGE, useTransactionsStore } from '@/stores/transactions'
import type { Transaction, TransactionFilters, TransactionType } from '@/types/api'
import { saveBlob } from '@/utils/download'
import { formatDate, formatMoney, fromIsoDate, toIsoDate } from '@/utils/format'

const store = useTransactionsStore()
const accounts = useAccountsStore()
const categories = useCategoriesStore()
const confirm = useConfirm()
const toast = useToast()

const dialogVisible = ref(false)
const editing = ref<Transaction | null>(null)
const referenceError = ref('')
const importVisible = ref(false)
const exporting = ref(false)

const typeOptions = [
  { label: 'Расходы', value: 'expense' },
  { label: 'Доходы', value: 'income' },
]

// --- Фильтры ---

const dateRange = computed<Date[] | null>({
  get: () =>
    store.query.dateFrom
      ? [
          fromIsoDate(store.query.dateFrom),
          ...(store.query.dateTo ? [fromIsoDate(store.query.dateTo)] : []),
        ]
      : null,
  set: (value) => {
    const [from, to] = value ?? []
    // Диапазон считается выбранным, когда есть обе даты (или поле очищено)
    if (value !== null && from && !to) return
    void store.setQuery({
      dateFrom: from ? toIsoDate(from) : null,
      dateTo: to ? toIsoDate(to) : null,
    })
  },
})

function setAccount(value: number | null) {
  void store.setQuery({ accountId: value })
}

function setCategory(value: number | null) {
  void store.setQuery({ categoryId: value })
}

function setType(value: TransactionType | null) {
  void store.setQuery({ type: value })
}

// Поиск отправляется с задержкой, чтобы не делать запрос на каждую букву
const search = ref(store.query.q)
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void store.setQuery({ q: value }), 400)
})

watch(
  () => store.query.q,
  (value) => {
    if (value !== search.value) search.value = value
  },
)

onBeforeUnmount(() => clearTimeout(searchTimer))

// --- Таблица ---

const sortField = computed(() => store.sort.replace(/^-/, ''))
const sortOrder = computed(() => (store.sort.startsWith('-') ? -1 : 1))

function onSort(event: DataTableSortEvent) {
  const field = event.sortField as string
  const sort = `${event.sortOrder === -1 ? '-' : ''}${field}` as NonNullable<
    TransactionFilters['sort']
  >
  void store.setSort(sort)
}

function onPage(event: DataTablePageEvent) {
  void store.setPage(event.page + 1)
}

// --- Действия ---

function openCreate() {
  editing.value = null
  dialogVisible.value = true
}

function openEdit(transaction: Transaction) {
  editing.value = transaction
  dialogVisible.value = true
}

function onSaved() {
  toast.add({ severity: 'success', summary: 'Сохранено', life: 2500 })
}

async function exportCsv() {
  exporting.value = true
  try {
    const blob = await exportTransactions(store.filterParams())
    saveBlob(blob, `transactions-${toIsoDate(new Date())}.csv`)
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Не удалось выгрузить',
      detail: toApiError(e).message,
      life: 6000,
    })
  } finally {
    exporting.value = false
  }
}

async function onImported(count: number) {
  toast.add({ severity: 'success', summary: `Импортировано операций: ${count}`, life: 4000 })
  await store.load()
}

function askDelete(transaction: Transaction) {
  confirm.require({
    header: 'Удаление операции',
    message: `Удалить операцию «${transaction.category.name}» на ${formatMoney(transaction.amount, transaction.account.currency)}?`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Отмена',
    acceptLabel: 'Удалить',
    acceptProps: { severity: 'danger' },
    rejectProps: { severity: 'secondary', text: true },
    accept: async () => {
      try {
        await store.remove(transaction.id)
        toast.add({ severity: 'success', summary: 'Операция удалена', life: 2500 })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Не удалось удалить',
          detail: toApiError(e).message,
          life: 5000,
        })
      }
    },
  })
}

async function loadReferences() {
  referenceError.value = ''
  try {
    await Promise.all([accounts.load(), categories.load()])
  } catch (e) {
    referenceError.value = toApiError(e).message
  }
}

onMounted(() => {
  void loadReferences()
  void store.load()
})
</script>

<template>
  <div class="page">
    <div class="page__head">
      <h1 class="page__title">Операции</h1>
      <div class="page__actions">
        <Button
          label="Импорт"
          icon="pi pi-upload"
          severity="secondary"
          outlined
          data-test="import"
          @click="importVisible = true"
        />
        <Button
          label="Экспорт"
          icon="pi pi-download"
          severity="secondary"
          outlined
          :loading="exporting"
          data-test="export"
          @click="exportCsv"
        />
        <Button
          label="Добавить"
          icon="pi pi-plus"
          :disabled="accounts.loaded && accounts.items.length === 0"
          data-test="add"
          @click="openCreate"
        />
      </div>
    </div>

    <Message v-if="referenceError" severity="error" :closable="false" data-test="reference-error">
      Не удалось загрузить счета и категории: {{ referenceError }}
      <Button label="Повторить" text size="small" @click="loadReferences" />
    </Message>

    <Message
      v-else-if="accounts.loaded && accounts.items.length === 0"
      severity="info"
      :closable="false"
      data-test="no-accounts"
    >
      Чтобы добавлять операции, сначала заведите счёт в разделе
      <RouterLink :to="{ name: 'accounts' }">«Счета»</RouterLink>.
    </Message>

    <div class="filters" data-test="filters">
      <DatePicker
        v-model="dateRange"
        selection-mode="range"
        date-format="dd.mm.yy"
        placeholder="Период"
        show-icon
        show-button-bar
        :manual-input="false"
        class="filters__item"
      />
      <Select
        :model-value="store.query.accountId"
        :options="accounts.items"
        option-label="name"
        option-value="id"
        placeholder="Все счета"
        show-clear
        class="filters__item"
        @update:model-value="setAccount"
      />
      <Select
        :model-value="store.query.categoryId"
        :options="categories.items"
        option-label="name"
        option-value="id"
        placeholder="Все категории"
        show-clear
        filter
        class="filters__item"
        @update:model-value="setCategory"
      />
      <Select
        :model-value="store.query.type"
        :options="typeOptions"
        option-label="label"
        option-value="value"
        placeholder="Все типы"
        show-clear
        class="filters__item"
        @update:model-value="setType"
      />
      <IconField class="filters__item">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Поиск по заметке" maxlength="100" fluid />
      </IconField>
      <Button
        v-if="store.hasFilters"
        label="Сбросить"
        icon="pi pi-filter-slash"
        severity="secondary"
        text
        data-test="reset"
        @click="store.resetQuery()"
      />
    </div>

    <Message v-if="store.error" severity="error" :closable="false" data-test="list-error">
      {{ store.error }}
      <Button label="Повторить" text size="small" @click="store.load()" />
    </Message>

    <DataTable
      :value="store.items"
      data-key="id"
      lazy
      paginator
      :rows="PER_PAGE"
      :total-records="store.total"
      :first="(store.page - 1) * PER_PAGE"
      :loading="store.loading"
      :sort-field="sortField"
      :sort-order="sortOrder"
      removable-sort
      responsive-layout="scroll"
      @page="onPage"
      @sort="onSort"
    >
      <template #empty>
        <p class="empty" data-test="empty">
          {{ store.hasFilters ? 'По выбранным фильтрам ничего не найдено.' : 'Операций пока нет.' }}
        </p>
      </template>

      <Column field="occurred_at" header="Дата" sortable>
        <template #body="{ data }: { data: Transaction }">{{
          formatDate(data.occurred_at)
        }}</template>
      </Column>
      <Column header="Категория">
        <template #body="{ data }: { data: Transaction }">
          <span class="category">
            <i
              class="category__dot"
              :style="{ background: data.category.color ?? 'var(--p-surface-400)' }"
            />
            {{ data.category.name }}
          </span>
        </template>
      </Column>
      <Column header="Счёт">
        <template #body="{ data }: { data: Transaction }">{{ data.account.name }}</template>
      </Column>
      <Column header="Заметка">
        <template #body="{ data }: { data: Transaction }">
          <span class="note">{{ data.note }}</span>
        </template>
      </Column>
      <Column
        field="amount"
        header="Сумма"
        sortable
        header-class="text-right"
        body-class="text-right"
      >
        <template #body="{ data }: { data: Transaction }">
          <span :class="data.type === 'income' ? 'amount amount--income' : 'amount'">
            {{ formatMoney(data.amount, data.account.currency, data.type) }}
          </span>
        </template>
      </Column>
      <Column header="" body-class="actions">
        <template #body="{ data }: { data: Transaction }">
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

    <TransactionImportDialog v-model:visible="importVisible" @imported="onImported" />

    <TransactionFormDialog
      v-model:visible="dialogVisible"
      :transaction="editing"
      @saved="onSaved"
    />
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

.page__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

.page__title {
  margin: 0;
  font-size: 1.5rem;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.filters__item {
  min-width: 11rem;
  flex: 1 1 11rem;
}

.empty {
  margin: 1rem 0;
  text-align: center;
  color: var(--p-text-muted-color);
}

.category {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.category__dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
}

.note {
  display: inline-block;
  max-width: 18rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: bottom;
  color: var(--p-text-muted-color);
}

.amount {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.amount--income {
  color: var(--p-green-500);
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
