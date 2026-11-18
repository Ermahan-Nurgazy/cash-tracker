import type { AuthResponse, Credentials, User } from '@/types/api'
import { http } from './http'

export async function register(credentials: Credentials): Promise<AuthResponse> {
  const { data } = await http.post<AuthResponse>('/auth/register', credentials)
  return data
}

export async function login(credentials: Credentials): Promise<AuthResponse> {
  const { data } = await http.post<AuthResponse>('/auth/login', credentials)
  return data
}

export async function fetchMe(): Promise<User> {
  const { data } = await http.get<User>('/auth/me')
  return data
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout')
}
