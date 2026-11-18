<script setup lang="ts">
import { useRouter } from 'vue-router'
import AuthForm from '@/components/AuthForm.vue'
import { useAuthStore } from '@/stores/auth'
import type { Credentials } from '@/types/api'

const auth = useAuthStore()
const router = useRouter()

async function submit(credentials: Credentials) {
  await auth.register(credentials)
  await router.replace('/')
}
</script>

<template>
  <AuthForm mode="register" :submit="submit" />
</template>
