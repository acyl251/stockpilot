import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/services/api'

interface User {
  id: number
  nom: string
  prenom: string
  email: string
  role: string
  organisation_id: number
  organisation?: {
    id: number
    nom: string
    secteur: string
    onboarding_complete: boolean
    plan: { ia_activee: boolean }
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user          = ref<User | null>(null)
  const accessToken   = ref<string | null>(localStorage.getItem('access_token'))

  const isAuthenticated = computed(() => !!accessToken.value)
  const hasAI           = computed(() => user.value?.organisation?.plan?.ia_activee ?? false)
  const isAdmin         = computed(() => ['admin', 'super_admin'].includes(user.value?.role ?? ''))
  const isSuperAdmin    = computed(() => user.value?.role === 'super_admin')

  async function setSession(data: any) {
    accessToken.value = data.access_token
    localStorage.setItem('access_token', data.access_token)
    user.value = data.user
    await fetchMe()
  }

  async function login(email: string, password: string) {
    const { data } = await authApi.login(email, password)
    await setSession(data)
  }

  async function verifyEmail(email: string, code: string) {
    const { data } = await authApi.verifyEmail(email, code)
    await setSession(data)
  }

  async function fetchMe() {
    const { data } = await authApi.me()
    user.value = data
  }

  async function logout() {
    try { await authApi.logout() } catch {}
    accessToken.value = null
    user.value        = null
    localStorage.removeItem('access_token')
  }

  // Rehydrate on app load — expose promise so router can await it
  const initPromise: Promise<void> = accessToken.value
    ? fetchMe().catch(() => logout())
    : Promise.resolve()

  return { user, accessToken, isAuthenticated, hasAI, isAdmin, isSuperAdmin, login, verifyEmail, logout, fetchMe, initPromise }
})
