<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { toApiError } from '@/api/errors'
import CategoryFormDialog from '@/components/CategoryFormDialog.vue'
import MonthlyChart from '@/components/MonthlyChart.vue'
import RecentTransactions from '@/components/RecentTransactions.vue'
import TransactionFormDialog from '@/components/TransactionFormDialog.vue'
import { useAccountsStore } from '@/stores/accounts'
import { useCategoriesStore } from '@/stores/categories'
import { useDashboardStore } from '@/stores/dashboard'
import { useRecentTransactionsStore } from '@/stores/recentTransactions'
import type { TransactionType } from '@/types/api'
import {
  buildCategoryBars,
  collectCurrencies,
  expenseChangePercent,
  hasActivity,
  monthlyRows,
  summarizeMonth,
} from '@/utils/dashboard'
import { EXPENSE_LABEL, INCOME_LABEL } from '@/utils/charts'
import { formatMoney } from '@/utils/format'
import { currentMonth, monthLabel, shiftMonth } from '@/utils/months'

const store = useDashboardStore()

const selectedCurrency = ref<string | null>(null)
const showTable = ref(false)

const currencies = computed(() =>
  collectCurrencies(store.monthly, store.categories, store.balances),
)

// Валюта по умолчанию — первая; если выбранная исчезла из данных, берём первую из оставшихся
watch(
  currencies,
  (list) => {
    if (selectedCurrency.value === null || !list.includes(selectedCurrency.value)) {
      selectedCurrency.value = list[0] ?? null
    }
  },
  { immediate: true },
)

const currency = computed(() => selectedCurrency.value ?? '')

const summary = computed(() => summarizeMonth(store.monthly, currency.value, store.month))
const change = computed(() => expenseChangePercent(summary.value))
const bars = computed(() => buildCategoryBars(store.categories, currency.value))
const rows = computed(() => monthlyRows(store.monthly, currency.value))
const accounts = computed(
  () => store.balances?.accounts.filter((account) => account.currency === currency.value) ?? [],
)
const accountsTotal = computed(
  () => store.balances?.totals.find((item) => item.currency === currency.value)?.balance ?? '0.00',
)

const isCurrent = computed(() => store.month >= currentMonth())
const hasData = computed(() => currencies.value.length > 0)

const money = (amount: string) => formatMoney(amount, currency.value)

function go(delta: number) {
  void store.setMonth(shiftMonth(store.month, delta))
}

function goCurrent() {
  void store.setMonth(currentMonth())
}

// --- Быстрые действия: новая операция или категория прямо с главного экрана ---

const accountsStore = useAccountsStore()
const categoriesStore = useCategoriesStore()
const recent = useRecentTransactionsStore()
const toast = useToast()

const referenceError = ref('')
const transactionDialog = ref(false)
const transactionType = ref<TransactionType>('expense')
const categoryDialog = ref(false)

const noAccounts = computed(() => accountsStore.loaded && accountsStore.items.length === 0)
/** Пока справочники не загружены, форма операции открылась бы с пустыми списками счетов и категорий */
const referencesReady = computed(() => accountsStore.loaded && categoriesStore.loaded)

/** Справочники нужны форме операции: счета и категории грузятся один раз */
async function loadReferences(): Promise<boolean> {
  referenceError.value = ''
  try {
    await Promise.all([accountsStore.load(), categoriesStore.load()])
    return true
  } catch (e) {
    referenceError.value = toApiError(e).message
    return false
  }
}

async function addTransaction(type: TransactionType) {
  if (!(await loadReferences()) || !referencesReady.value || noAccounts.value) return
  transactionType.value = type
  transactionDialog.value = true
}

function onTransactionSaved() {
  toast.add({ severity: 'success', summary: 'Операция добавлена', life: 2500 })
  void store.load()
  // Новая операция попадает в начало списка: возвращаемся на первую страницу, иначе её не видно
  void recent.setPage(1)
}

function onCategorySaved() {
  toast.add({ severity: 'success', summary: 'Категория добавлена', life: 2500 })
}

onMounted(() => {
  void store.load()
  void loadReferences()
})
</script>

<template>
  <div class="page">
    <div class="page__head">
      <h1 class="page__title">Дашборд</h1>

      <div class="month" role="group" aria-label="Выбор месяца">
        <Button
          icon="pi pi-chevron-left"
          severity="secondary"
          text
          rounded
          aria-label="Предыдущий месяц"
          data-test="prev"
          @click="go(-1)"
        />
        <span class="month__label" data-test="month">{{ monthLabel(store.month) }}</span>
        <Button
          icon="pi pi-chevron-right"
          severity="secondary"
          text
          rounded
          aria-label="Следующий месяц"
          :disabled="isCurrent"
          data-test="next"
          @click="go(1)"
        />
        <Button
          v-if="!isCurrent"
          label="Текущий"
          severity="secondary"
          text
          size="small"
          data-test="current"
          @click="goCurrent"
        />
      </div>

      <SelectButton
        v-if="currencies.length > 1"
        v-model="selectedCurrency"
        :options="currencies"
        :allow-empty="false"
        aria-label="Валюта"
        data-test="currency"
      />
    </div>

    <div class="quick" role="group" aria-label="Быстрые действия">
      <Button
        label="Расход"
        icon="pi pi-minus-circle"
        :disabled="noAccounts || !referencesReady"
        data-test="add-expense"
        @click="addTransaction('expense')"
      />
      <Button
        label="Доход"
        icon="pi pi-plus-circle"
        severity="success"
        :disabled="noAccounts || !referencesReady"
        data-test="add-income"
        @click="addTransaction('income')"
      />
      <Button
        label="Категория"
        icon="pi pi-tag"
        severity="secondary"
        outlined
        data-test="add-category"
        @click="categoryDialog = true"
      />
      <span v-if="noAccounts" class="muted quick__hint" data-test="no-accounts">
        Чтобы добавлять операции, сначала заведите
        <RouterLink :to="{ name: 'accounts' }">счёт</RouterLink>.
      </span>
    </div>

    <Message v-if="referenceError" severity="error" :closable="false" data-test="reference-error">
      Не удалось загрузить счета и категории: {{ referenceError }}
      <Button label="Повторить" text size="small" @click="loadReferences" />
    </Message>

    <Message v-if="store.error" severity="error" :closable="false" data-test="error">
      {{ store.error }}
      <Button label="Повторить" text size="small" @click="store.load()" />
    </Message>

    <p v-else-if="store.loading && !store.monthly" class="muted" data-test="loading">Загрузка…</p>

    <Message
      v-else-if="!hasData && !store.loading"
      severity="info"
      :closable="false"
      data-test="empty"
    >
      Данных пока нет. Заведите счёт в разделе
      <RouterLink :to="{ name: 'accounts' }">«Счета»</RouterLink> и добавьте первые
      <RouterLink :to="{ name: 'transactions' }">операции</RouterLink>.
    </Message>

    <template v-if="hasData">
      <section class="tiles viz" :class="{ 'is-loading': store.loading }" aria-label="Итоги месяца">
        <div class="tile" data-test="tile-income">
          <span class="tile__label">{{ INCOME_LABEL }}</span>
          <span class="tile__value">{{ money(summary.income) }}</span>
        </div>
        <div class="tile" data-test="tile-expense">
          <span class="tile__label">{{ EXPENSE_LABEL }}</span>
          <span class="tile__value">{{ money(summary.expense) }}</span>
          <span v-if="change !== null" class="tile__delta" data-test="delta">
            <i
              :class="
                change > 0 ? 'pi pi-arrow-up' : change < 0 ? 'pi pi-arrow-down' : 'pi pi-minus'
              "
            />
            {{ change > 0 ? '+' : '' }}{{ change }}% к прошлому месяцу
          </span>
        </div>
        <div class="tile tile--hero" data-test="tile-balance">
          <span class="tile__label">Итог месяца</span>
          <span class="tile__value tile__value--hero">{{ money(summary.balance) }}</span>
        </div>
      </section>
    </template>

    <!-- Последние операции не зависят от выбранного месяца и валюты -->
    <RecentTransactions />

    <template v-if="hasData">
      <div class="grid viz" :class="{ 'is-loading': store.loading }">
        <section class="card" aria-labelledby="by-category">
          <h2 id="by-category" class="card__title">Расходы по категориям</h2>
          <p v-if="bars.length === 0" class="muted" data-test="no-expenses">
            В этом месяце расходов нет.
          </p>
          <ul v-else class="bars" data-test="bars">
            <li
              v-for="bar in bars"
              :key="bar.key"
              class="bars__row"
              :title="`${bar.name}: ${money(bar.total)} (${bar.share}%, операций: ${bar.count})`"
            >
              <span class="bars__name">{{ bar.name }}</span>
              <span class="bars__track">
                <span class="bars__fill" :style="{ width: `${bar.width}%` }" />
              </span>
              <span class="bars__value">{{ money(bar.total) }}</span>
              <span class="bars__share">{{ bar.share }}%</span>
            </li>
          </ul>
        </section>

        <section class="card" aria-labelledby="dynamics">
          <div class="card__head">
            <h2 id="dynamics" class="card__title">Динамика за 12 месяцев</h2>
            <Button
              :label="showTable ? 'График' : 'Таблица'"
              :icon="showTable ? 'pi pi-chart-bar' : 'pi pi-table'"
              severity="secondary"
              text
              size="small"
              data-test="toggle-table"
              @click="showTable = !showTable"
            />
          </div>

          <p v-if="!hasActivity(rows)" class="muted" data-test="no-dynamics">
            За этот период операций нет.
          </p>
          <template v-else>
            <ul class="legend" aria-label="Обозначения">
              <li><i class="legend__swatch legend__swatch--income" />{{ INCOME_LABEL }}</li>
              <li><i class="legend__swatch legend__swatch--expense" />{{ EXPENSE_LABEL }}</li>
            </ul>

            <table v-if="showTable" class="table" data-test="table">
              <thead>
                <tr>
                  <th>Месяц</th>
                  <th>{{ INCOME_LABEL }}</th>
                  <th>{{ EXPENSE_LABEL }}</th>
                  <th>Итог</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in rows" :key="row.month">
                  <td>{{ row.month }}</td>
                  <td>{{ money(row.income) }}</td>
                  <td>{{ money(row.expense) }}</td>
                  <td>{{ money(row.balance) }}</td>
                </tr>
              </tbody>
            </table>
            <MonthlyChart v-else :rows="rows" :currency="currency" data-test="chart" />
          </template>
        </section>
      </div>

      <section class="card" aria-labelledby="balances">
        <h2 id="balances" class="card__title">Остатки по счетам</h2>
        <p v-if="accounts.length === 0" class="muted">Нет счетов в этой валюте.</p>
        <ul v-else class="accounts" data-test="accounts">
          <li v-for="account in accounts" :key="account.id" class="accounts__row">
            <span>{{ account.name }}</span>
            <span class="accounts__value">{{ money(account.balance) }}</span>
          </li>
          <li class="accounts__row accounts__row--total">
            <span>Всего</span>
            <span class="accounts__value" data-test="accounts-total">{{
              money(accountsTotal)
            }}</span>
          </li>
        </ul>
      </section>
    </template>

    <TransactionFormDialog
      v-model:visible="transactionDialog"
      :transaction="null"
      :initial-type="transactionType"
      @saved="onTransactionSaved"
    />
    <CategoryFormDialog
      v-model:visible="categoryDialog"
      :category="null"
      @saved="onCategorySaved"
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
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
}

.page__title {
  margin: 0;
  margin-right: auto;
  font-size: 1.5rem;
}

.month {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.month__label {
  min-width: 9rem;
  text-align: center;
  font-weight: 600;
}

.quick {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.quick__hint {
  margin-left: 0.25rem;
}

.muted {
  margin: 0;
  color: var(--p-text-muted-color);
}

.is-loading {
  opacity: 0.6;
  transition: opacity 0.15s;
}

.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
  gap: 1rem;
}

.tile,
.card {
  padding: 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius);
  background: var(--p-content-background);
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.tile__label {
  color: var(--p-text-muted-color);
}

.tile__value {
  font-size: 1.5rem;
  font-weight: 600;
}

/* Одна главная цифра экрана: итог месяца */
.tile__value--hero {
  font-size: 2rem;
}

.tile__delta {
  color: var(--p-text-muted-color);
  font-size: 0.875rem;
}

.grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}

@media (min-width: 1200px) {
  .grid {
    grid-template-columns: 1fr 1fr;
  }
}

.card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card__title {
  margin: 0 0 0.75rem;
  font-size: 1.125rem;
}

.card__head .card__title {
  margin-bottom: 0;
}

/* Ранжированные полосы: одна системная синяя серия, подписи цветом текста, а не данных */
.bars {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.bars__row {
  display: grid;
  grid-template-columns: minmax(5rem, 9rem) 1fr auto 3rem;
  align-items: center;
  gap: 0.75rem;
}

.bars__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bars__track {
  height: 1.25rem;
}

.bars__fill {
  display: block;
  height: 100%;
  max-height: 1.25rem;
  border-radius: 0 4px 4px 0;
  background: var(--viz-series-1);
}

.bars__value {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.bars__share {
  color: var(--p-text-muted-color);
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.legend {
  display: flex;
  gap: 1rem;
  margin: 0 0 0.5rem;
  padding: 0;
  list-style: none;
  color: var(--p-text-muted-color);
}

.legend li {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.legend__swatch {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 2px;
}

.legend__swatch--income {
  background: var(--viz-series-1);
}

.legend__swatch--expense {
  background: var(--viz-series-2);
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-variant-numeric: tabular-nums;
}

.table th,
.table td {
  padding: 0.375rem 0.5rem;
  border-bottom: 1px solid var(--p-content-border-color);
  text-align: right;
}

.table th:first-child,
.table td:first-child {
  text-align: left;
}

.accounts {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.accounts__row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--p-content-border-color);
}

.accounts__row--total {
  border-bottom: 0;
  font-weight: 600;
}

.accounts__value {
  font-variant-numeric: tabular-nums;
}
</style>
