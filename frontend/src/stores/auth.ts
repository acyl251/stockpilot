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
  point_de_vente_id?: number | null
  point_de_vente?: { id: number; nom: string; type: string } | null
  organisation?: {
    id: number
    nom: string
    secteur: string
    slug?: string
    adresse?: string
    telephone?: string
    onboarding_complete: boolean
    points_de_vente_count?: number
    plan: { ia_activee: boolean }
  }
}

export const useAuthStore = defineStore('auth', () => {
  const user          = ref<User | null>(null)
  const accessToken   = ref<string | null>(localStorage.getItem('access_token'))

  const isAuthenticated   = computed(() => !!accessToken.value)
  const hasAI             = computed(() => user.value?.organisation?.plan?.ia_activee ?? false)
  const isAdmin           = computed(() => ['admin', 'super_admin'].includes(user.value?.role ?? ''))
  const isSuperAdmin      = computed(() => user.value?.role === 'super_admin')
  const secteur           = computed(() => user.value?.organisation?.secteur ?? null)
  const isRestauration    = computed(() => user.value?.organisation?.secteur === 'restauration')
  const pointDeVenteId    = computed(() => user.value?.point_de_vente_id ?? null)
  const pointDeVente      = computed(() => user.value?.point_de_vente ?? null)
  // Vue Chaîne : visible uniquement si admin + au moins 2 PDVs actifs
  const chaineVisible     = computed(() =>
    isAdmin.value && (user.value?.organisation?.points_de_vente_count ?? 0) >= 2
  )
  // Multi-PDV : org avec plusieurs points de vente actifs
  const isMultiPDV        = computed(() => (user.value?.organisation?.points_de_vente_count ?? 0) > 1)
  // Opérateur restreint : opérateur dans une org multi-PDV (catalogue/stock en lecture seule)
  const isRestrictedOperateur = computed(() => isMultiPDV.value && !isAdmin.value)

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

  return { user, accessToken, isAuthenticated, hasAI, isAdmin, isSuperAdmin, secteur, isRestauration, pointDeVenteId, pointDeVente, chaineVisible, isMultiPDV, isRestrictedOperateur, login, logout, fetchMe, initPromise }
})
