<template>
  <aside class="w-64 bg-navy flex flex-col h-full shadow-xl">
    <!-- Logo -->
    <div class="px-6 py-5 border-b border-navy-light">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-gold rounded-lg flex items-center justify-center">
          <span class="text-white font-bold text-sm">S</span>
        </div>
        <div>
          <p class="text-white font-bold text-lg leading-none">StockPilot</p>
          <p class="text-slate-400 text-xs mt-0.5">{{ orgName }}</p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-1">
      <SidebarLink v-for="item in navItems" :key="item.to"
        :to="item.to" :icon="item.icon" :label="item.label"
        :badge="item.badge" />
    </nav>

    <!-- Plan badge -->
    <div class="px-6 py-4 border-t border-navy-light">
      <div class="bg-navy-light rounded-lg px-3 py-2">
        <p class="text-slate-400 text-xs">Plan actuel</p>
        <p class="text-gold font-semibold text-sm">{{ planName }}</p>
        <p v-if="hasAI" class="text-emerald-400 text-xs mt-0.5">IA activée</p>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore } from '@/stores/alerts'
import SidebarLink from './SidebarLink.vue'

const auth   = useAuthStore()
const alerts = useAlertsStore()

const orgName  = computed(() => auth.user?.organisation?.nom ?? '')
const planName = computed(() => (auth.user?.organisation as any)?.plan?.nom ?? 'Starter')
const hasAI    = computed(() => auth.hasAI)

const navItems = computed(() => {
  if (auth.isSuperAdmin) {
    return [
      { to: '/app/super-admin', icon: 'superadmin', label: 'Plateforme', badge: undefined },
    ]
  }
  return [
    { to: '/app',          icon: 'dashboard', label: 'Tableau de bord', badge: undefined },
    { to: '/app/products', icon: 'products',  label: 'Catalogue', badge: undefined },
    { to: '/app/movements',icon: 'movements', label: 'Mouvements', badge: undefined },
    { to: '/app/alerts',   icon: 'alerts',    label: 'Alertes & IA',
      badge: alerts.totalAlerts() || undefined },
    { to: '/app/config',   icon: 'config',    label: 'Configuration', badge: undefined },
    ...(auth.isAdmin ? [{ to: '/app/users', icon: 'users', label: 'Utilisateurs', badge: undefined }] : []),
  ]
})
</script>
