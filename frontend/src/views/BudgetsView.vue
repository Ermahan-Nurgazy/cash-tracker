<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { toApiError } from '@/api/errors'
import BudgetFormDialog from '@/components/BudgetFormDialog.vue'
import { useBudgetsStore } from '@/stores/budgets'
import { useCategoriesStore } from '@/stores/categories'
import type { Budget, BudgetStatus } from '@/types/api'
import { formatMoney } from '@/utils/format'
import { currentMonth, monthLabel, shiftMonth } from '@/utils/months'

const store = useBudgetsStore()
const categories = useCategoriesStore()
const confirm = useConfirm()
const toast = useToast()

const dialogVisible = ref(false)
const editing = ref<Budget | null>(null)
const categoriesError = ref('')

const isCurrent = computed(() => store.month === currentMonth())

const statusInfo: Record<BudgetStatus, { label: string; icon: string }> = {
  ok: { label: 'В пределах', icon: 'pi pi-check-circle' },
  warning: { label: 'На исходе', icon: 'pi pi-exclamation-circle' },
  exceeded: { label: 'Превышен', icon: 'pi pi-times-circle' },
}

const hasExpenseCategories = computed(() => categories.byType('expense').length > 0)

async function loadCategories() {
  categoriesError.value = ''
  try {
    await categories.load()
  } catch (e) {
    categoriesError.value = toApiError(e).message
  }
}

function money(budget: Budget, value: string) {
  return formatMoney(value, budget.currency)
}

/** Длина полосы: расход относительно лимита, но не длиннее трека */
function fill(budget: Budget) {
  return Math.min(100, Math.max(budget.percent, budget.percent > 0 ? 2 : 0))
}

function openCreate() {
  editing.value = null
  dialogVisible.value = true
}

function openEdit(budget: Budget) {
  editing.value = budget
  dialogVisible.value = true
}

function onSaved() {
  toast.add({ severity: 'success', summary: 'Сохранено', life: 2500 })
}

function askDelete(budget: Budget) {
  confirm.require({
    header: 'Удаление бюджета',
    message: `Удалить бюджет «${budget.category.name}» на ${monthLabel(budget.month)}?`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Отмена',
    acceptLabel: 'Удалить',
    acceptProps: { severity: 'danger' },
    rejectProps: { severity: 'secondary', text: true },
    accept: async () => {
      try {
        await store.remove(budget.id)
        toast.add({ severity: 'success', summary: 'Бюджет удалён', life: 2500 })
      } catch (e) {
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

onMounted(() => {
  void store.load()
  void loadCategories()
})
</script>

<template>
  <div class="page">
    <div class="page__head">
      <h1 class="page__title">Бюджеты</h1>
      <Button
        label="Добавить"
        icon="pi pi-plus"
        :disabled="!hasExpenseCategories"
        data-test="add"
        @click="openCreate"
      />
    </div>

    <div class="months">
      <Button
        icon="pi pi-chevron-left"
        severity="secondary"
        text
        rounded
        aria-label="Предыдущий месяц"
        data-test="prev"
        @click="store.setMonth(shiftMonth(store.month, -1))"
      />
      <strong class="months__label" data-test="month">{{ monthLabel(store.month) }}</strong>
      <Button
        icon="pi pi-chevron-right"
        severity="secondary"
        text
        rounded
        aria-label="Следующий месяц"
        data-test="next"
        @click="store.setMonth(shiftMonth(store.month, 1))"
      />
      <Button
        v-if="!isCurrent"
        label="Текущий"
        size="small"
        severity="secondary"
        text
        data-test="current"
        @click="store.setMonth(currentMonth())"
      />
    </div>

    <Message v-if="store.error" severity="error" :closable="false" data-test="error">
      {{ store.error }}
      <Button label="Повторить" text size="small" @click="store.load()" />
    </Message>
    <Message v-if="categoriesError" severity="error" :closable="false" data-test="categories-error">
      {{ categoriesError }}
    </Message>

    <p v-if="categories.loaded && !hasExpenseCategories" class="muted" data-test="no-categories">
      Чтобы задать бюджет, сначала создайте категорию расходов.
    </p>

    <p
      v-else-if="!store.loading && !store.error && store.items.length === 0"
      class="muted"
      data-test="empty"
    >
      На этот месяц бюджетов нет.
    </p>

    <ul v-if="store.items.length > 0" class="list" data-test="list">
      <li
        v-for="budget in store.items"
        :key="budget.id"
        class="item"
        :class="`item--${budget.status}`"
        data-test="budget"
      >
        <div class="item__head">
          <span class="item__name">
            <i
              class="dot"
              :style="{ background: budget.category.color ?? 'var(--p-surface-400)' }"
            />
            <i v-if="budget.category.icon" :class="['pi', budget.category.icon]" />
            {{ budget.category.name }}
          </span>
          <span class="status" :class="`status--${budget.status}`" data-test="status">
            <i :class="statusInfo[budget.status].icon" />
            {{ statusInfo[budget.status].label }}
          </span>
          <span class="item__actions">
            <Button
              icon="pi pi-pencil"
              severity="secondary"
              text
              rounded
              aria-label="Редактировать"
              data-test="edit"
              @click="openEdit(budget)"
            />
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              rounded
              aria-label="Удалить"
              data-test="delete"
              @click="askDelete(budget)"
            />
          </span>
        </div>

        <div
          class="track"
          role="progressbar"
          :aria-valuenow="budget.percent"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="`Израсходовано ${budget.percent}% лимита`"
        >
          <span class="fill" :style="{ width: `${fill(budget)}%` }" />
        </div>

        <div class="item__nums">
          <span data-test="spent"
            >{{ money(budget, budget.spent) }} из {{ money(budget, budget.limit) }}</span
          >
          <span class="percent" data-test="percent">{{ budget.percent }}%</span>
          <span class="remaining" data-test="remaining">
            {{ Number(budget.remaining) < 0 ? 'Перерасход' : 'Осталось' }}
            {{ money(budget, String(Math.abs(Number(budget.remaining)))) }}
          </span>
        </div>
      </li>
    </ul>

    <BudgetFormDialog
      v-model:visible="dialogVisible"
      :month="store.month"
      :budget="editing"
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

.page__title {
  margin: 0;
  font-size: 1.5rem;
}

.months {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.months__label {
  min-width: 10rem;
  text-align: center;
}

.muted {
  margin: 1rem 0;
  text-align: center;
  color: var(--p-text-muted-color);
}

.list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius, 0.5rem);
  background: var(--p-content-background);
}

.item__head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.item__name {
  display: inline-flex;
  flex: 1;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
}

.item__actions {
  white-space: nowrap;
}

.dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
}

/* Статус передаётся значком и подписью, цвет только усиливает его */
.status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}

.status--warning {
  color: var(--p-orange-600);
}

.status--exceeded {
  color: var(--p-red-600);
}

.track {
  height: 0.5rem;
  overflow: hidden;
  border-radius: 0.25rem;
  background: var(--viz-grid, var(--p-surface-200));
}

.fill {
  display: block;
  height: 100%;
  border-radius: 0.25rem;
  background: var(--viz-series-1, var(--p-primary-color));
}

.item--warning .fill {
  background: var(--p-orange-500);
}

.item--exceeded .fill {
  background: var(--p-red-500);
}

.item__nums {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 1rem;
  font-size: 0.9rem;
}

.percent {
  font-weight: 600;
}

.remaining {
  margin-left: auto;
  color: var(--p-text-muted-color);
}
</style>
