<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import AuthForm from '@/components/AuthForm.vue'
import { safeRedirect } from '@/router'
import { useAuthStore } from '@/stores/auth'
import type { Credentials } from '@/types/api'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

async function submit(credentials: Credentials) {
  await auth.login(credentials)
  await router.replace(safeRedirect(route.query.redirect))
}
</script>

<template>
  <AuthForm mode="login" :submit="submit" />
</template>
