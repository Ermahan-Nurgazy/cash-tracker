<script setup lang="ts">
import Card from 'primevue/card'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <Card>
    <template #title>{{ route.meta.title }}</template>
    <template #content>
      <p class="m-0">
        Раздел в разработке{{ route.meta.step ? ` (шаг ${route.meta.step})` : '' }}.
      </p>
    </template>
  </Card>
</template>
