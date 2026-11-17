<script setup lang="ts">
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import { computed, onMounted } from 'vue'
import { useHealthStore } from '@/stores/health'

const store = useHealthStore()

const severity = computed(() => {
  if (store.error || store.health?.status === 'degraded') return 'danger'
  return store.health ? 'success' : 'secondary'
})

const label = computed(() => {
  if (store.error) return store.error
  if (!store.health) return 'Проверка...'
  return store.health.status === 'ok' ? 'API работает' : 'API работает, БД недоступна'
})

onMounted(() => store.load())
</script>

<template>
  <main class="home">
    <Card>
      <template #title>Cash Tracker</template>
      <template #subtitle>Трекер расходов</template>
      <template #content>
        <Tag :severity="severity" :value="label" />
      </template>
      <template #footer>
        <Button label="Проверить снова" icon="pi pi-refresh" :loading="store.loading" @click="store.load()" />
      </template>
    </Card>
  </main>
</template>

<style scoped>
.home {
  display: flex;
  min-height: 100vh;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
</style>
