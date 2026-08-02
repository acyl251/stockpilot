<template>
  <aside class="w-64 bg-navy flex flex-col h-full shadow-xl">
    <!-- Logo -->
    <div class="px-6 py-5 border-b border-navy-light">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-gold rounded-xl flex items-center justify-center shadow-sm">
          <span class="text-navy font-bold text-base">S</span>
        </div>
        <div>
          <p class="text-white font-bold text-lg leading-none">StockPilot</p>
          <p class="text-slate-400 text-xs mt-1 truncate max-w-[9rem]">{{ orgName }}</p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1.5">
      <SidebarLink v-for="item in navItems" :key="item.to"
        :to="item.to" :icon="item.icon" :label="item.label"
        :badge="item.badge" />
    </nav>

    <!-- Plan badge -->
    <div class="px-4 py-4 border-t border-navy-light">
      <div class="bg-navy-light rounded-xl px-4 py-3">
        <p class="text-slate-400 text-xs uppercase tracking-wide">Plan actuel</p>
        <p class="text-gold font-semibold text-sm mt-0.5">{{ planName }}</p>
        <p v-if="hasAI" class="text-emerald-400 text-xs mt-1 flex items-center gap-1">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> IA activée
        </p>
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

// Liens communs aux deux secteurs, insérés après les liens spécifiques
const commonTailItems = computed(() => [
  { to: '/app/clients',  icon: 'clients',   label: 'Clients', badge: undefined },
  { to: '/app/movements', icon: 'movements', label: 'Mouvements', badge: undefined },
  ...(!auth.isRestrictedOperateur ? [{ to: '/app/fournisseurs', icon: 'fournisseurs', label: 'Fournisseurs', badge: undefined }] : []),
  { to: '/app/alerts',   icon: 'alerts',    label: 'Alertes', badge: alerts.totalAlerts() || undefined },
  ...(auth.isAdmin && auth.isMultiPDV ? [{ to: '/app/points-de-vente', icon: 'ventes',    label: 'Points de vente', badge: undefined }] : []),
  ...(auth.isAdmin && auth.isMultiPDV ? [{ to: '/app/transferts',      icon: 'movements', label: 'Transferts',      badge: undefined }] : []),
  ...(auth.chaineVisible ? [{ to: '/app/chaine', icon: 'chaine', label: 'Vue chaîne', badge: undefined }] : []),
  { to: '/app/config',   icon: 'config',    label: 'Configuration', badge: undefined },
  ...(auth.isAdmin ? [{ to: '/app/users', icon: 'users', label: 'Utilisateurs', badge: undefined }] : []),
  ...(auth.isAdmin ? [{ to: '/app/logs',  icon: 'logs',  label: 'Activité',     badge: undefined }] : []),
])

const restaurationItems = computed(() => [
  { to: '/app',               icon: 'dashboard',    label: 'Tableau de bord', badge: undefined },
  ...(auth.canViewAnalytics ? [{ to: '/app/analytique', icon: 'analytique', label: 'Analytique', badge: undefined }] : []),
  { to: '/app/products',      icon: 'products',     label: 'Catalogue', badge: undefined },
  { to: '/app/menu',          icon: 'menu',          label: 'Menu', badge: undefined },
  { to: '/app/supplements',   icon: 'supplements',   label: 'Suppléments', badge: undefined },
  { to: '/app/tables',        icon: 'tables',        label: 'Tables', badge: undefined },
  { to: '/app/consommation',  icon: 'consommation',  label: 'Consommation', badge: undefined },
  { to: '/app/caisse',        icon: 'caisse',        label: 'Caisse', badge: undefined },
  { to: '/app/ventes',        icon: 'ventes',        label: 'Ventes', badge: undefined },
  ...commonTailItems.value,
])

const commerceItems = computed(() => [
  { to: '/app',               icon: 'dashboard',    label: 'Tableau de bord', badge: undefined },
  ...(auth.canViewAnalytics ? [{ to: '/app/analytique', icon: 'analytique', label: 'Analytique', badge: undefined }] : []),
  { to: '/app/products',      icon: 'products',     label: 'Catalogue', badge: undefined },
  { to: '/app/caisse',        icon: 'caisse',        label: 'Caisse', badge: undefined },
  { to: '/app/ventes',        icon: 'ventes',        label: 'Ventes', badge: undefined },
  { to: '/app/commandes-clients', icon: 'commandes', label: 'Commandes', badge: undefined },
  ...commonTailItems.value,
])

const navItems = computed(() => {
  if (auth.isSuperAdmin) {
    return [
      { to: '/app/super-admin', icon: 'superadmin', label: 'Plateforme', badge: undefined },
    ]
  }
  // Secteur non défini → comportement commerce par défaut (plus générique)
  return auth.isRestauration ? restaurationItems.value : commerceItems.value
})
</script>
