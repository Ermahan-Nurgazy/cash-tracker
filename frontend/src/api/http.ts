import axios from 'axios'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  timeout: 15000,
  headers: { Accept: 'application/json' },
})

// Подстановка Bearer-токена и обработка 401 появятся на шаге 4.1
