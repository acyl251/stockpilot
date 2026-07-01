<template>
  <div class="flex h-screen bg-slate-50 overflow-hidden">

    <!-- Modal Limite de plan (global) -->
    <div v-if="limitStore.visible"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4"
      @click.self="limitStore.hide()">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-xl flex-shrink-0">🔒</div>
          <div>
            <h3 class="font-semibold text-navy text-base">Limite atteinte</h3>
            <p v-if="limitStore.planName" class="text-xs text-slate-400">Plan {{ limitStore.planName }}</p>
          </div>
        </div>
        <p class="text-slate-600 text-sm mb-5">{{ limitStore.message }}</p>
        <div class="flex gap-2">
          <RouterLink to="/app/config"
            @click="limitStore.hide()"
            class="flex-1 text-center bg-gold hover:bg-yellow-500 text-white rounded-lg py-2 text-sm font-medium transition-colors">
            Voir les plans
          </RouterLink>
          <button @click="limitStore.hide()"
            class="flex-1 border rounded-lg py-2 text-sm hover:bg-slate-50 transition-colors">
            Fermer
          </button>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <AppSidebar />

    <!-- Main content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Top bar -->
      <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-navy">{{ pageTitle }}</h1>
        <div class="flex items-center gap-4">
          <!-- Alert badge -->
          <RouterLink to="/alerts" class="relative">
            <span class="text-slate-500 hover:text-navy transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
            </span>
            <span v-if="alertCount > 0"
              class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
              {{ alertCount }}
            </span>
          </RouterLink>

          <!-- User menu -->
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-navy flex items-center justify-center text-white text-sm font-semibold">
              {{ userInitials }}
            </div>
            <span class="text-sm text-slate-600 hidden md:block">{{ auth.user?.prenom }}</span>
            <button @click="handleLogout" class="text-slate-400 hover:text-red-500 transition-colors text-sm">
              Déconnexion
            </button>
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore } from '@/stores/alerts'
import { useLimitStore } from '@/stores/limit'
import AppSidebar from './AppSidebar.vue'

const auth       = useAuthStore()
const alerts     = useAlertsStore()
const limitStore = useLimitStore()
const route  = useRoute()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'landing' })
}

const pageTitles: Record<string, string> = {
  dashboard: 'Tableau de bord',
  products:  'Catalogue produits',
  movements: 'Mouvements de stock',
  alerts:    'Alertes & IA',
  config:    'Configuration',
  users:     'Utilisateurs',
}

const pageTitle   = computed(() => pageTitles[route.name as string] ?? 'StockPilot')
const alertCount  = computed(() => alerts.totalAlerts())
const userInitials = computed(() => {
  const u = auth.user
  return u ? `${u.prenom[0]}${u.nom[0]}`.toUpperCase() : '?'
})

onMounted(() => alerts.fetchStockAlerts())
</script>
