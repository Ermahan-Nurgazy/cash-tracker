<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable, { type DataTablePageEvent } from 'primevue/datatable'
import Message from 'primevue/message'
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { RECENT_PER_PAGE, useRecentTransactionsStore } from '@/stores/recentTransactions'
import type { Transaction } from '@/types/api'
import { formatDate, formatMoney } from '@/utils/format'

const store = useRecentTransactionsStore()

function onPage(event: DataTablePageEvent) {
  void store.setPage(event.page + 1)
}

onMounted(() => void store.load())
</script>

<template>
  <section class="card" aria-labelledby="recent">
    <div class="card__head">
      <h2 id="recent" class="card__title">Последние операции</h2>
      <RouterLink :to="{ name: 'transactions' }" class="card__link" data-test="all-transactions">
        Все операции <i class="pi pi-arrow-right" />
      </RouterLink>
    </div>

    <Message v-if="store.error" severity="error" :closable="false" data-test="recent-error">
      {{ store.error }}
      <Button label="Повторить" text size="small" @click="store.load()" />
    </Message>

    <DataTable
      v-else
      :value="store.items"
      data-key="id"
      lazy
      :paginator="store.total > RECENT_PER_PAGE"
      :rows="RECENT_PER_PAGE"
      :total-records="store.total"
      :first="(store.page - 1) * RECENT_PER_PAGE"
      :loading="store.loading"
      responsive-layout="scroll"
      data-test="recent"
      @page="onPage"
    >
      <template #empty>
        <p class="empty" data-test="recent-empty">Операций пока нет.</p>
      </template>

      <Column header="Дата">
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
      <Column header="Сумма" header-class="text-right" body-class="text-right">
        <template #body="{ data }: { data: Transaction }">
          <span :class="data.type === 'income' ? 'amount amount--income' : 'amount'">
            {{ formatMoney(data.amount, data.account.currency, data.type) }}
          </span>
        </template>
      </Column>
    </DataTable>
  </section>
</template>

<style scoped>
.card {
  padding: 1rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius);
  background: var(--p-content-background);
}

.card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.card__title {
  margin: 0;
  font-size: 1.125rem;
}

.card__link {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--p-primary-color);
  text-decoration: none;
  white-space: nowrap;
}

.card__link:hover {
  text-decoration: underline;
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
</style>
