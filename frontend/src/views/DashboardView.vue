<template>
  <div class="space-y-6">
    <!-- Welcome banner — catalog pre-filled by AI at org creation -->
    <div v-if="showWelcome" class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-navy to-navy-dark text-white p-6 shadow-lg">
      <div class="absolute -right-6 -top-6 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
      <div class="absolute right-10 bottom-0 w-24 h-24 bg-gold/5 rounded-full blur-xl"></div>
      <button @click="dismissWelcome"
        class="absolute top-4 right-4 text-white/60 hover:text-white text-lg leading-none">✕</button>
      <div class="relative flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-gold/20 flex items-center justify-center text-2xl flex-shrink-0">✨</div>
        <div>
          <h2 class="text-lg font-bold flex items-center gap-2">
            Votre catalogue a été préparé par l'IA
            <span class="text-xs bg-gold/20 text-gold px-2 py-0.5 rounded-full font-medium">GPT-4o mini</span>
          </h2>
          <p class="text-sm text-white/80 mt-1">
            <template v-if="bienvenueIA.secteur">
              Pour le secteur <strong class="text-gold">{{ bienvenueIA.secteur }}</strong>,
            </template>
            <strong class="text-white">{{ bienvenueIA.nb_produits }} produits</strong>
            ont été importés automatiquement avec leurs catégories. Vous pouvez les modifier ou en ajouter à tout moment.
          </p>
          <div class="flex gap-3 mt-3">
            <RouterLink to="/app/products"
              class="bg-gold hover:bg-yellow-500 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
              Voir mon catalogue →
            </RouterLink>
            <button @click="dismissWelcome"
              class="text-white/70 hover:text-white text-sm px-3 py-1.5">Plus tard</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Real-time KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <KpiCard label="Total produits"      :value="kpis.total_produits"        icon="📦" color="navy" />
      <KpiCard label="Valeur du stock"     :value="formatCurrency(kpis.valeur_stock)" icon="💰" color="gold" />
      <KpiCard label="Ruptures de stock"   :value="kpis.total_ruptures"        icon="🔴" color="red" />
      <KpiCard label="En alerte"           :value="kpis.total_alertes"         icon="⚠️" color="amber" />
    </div>

    <!-- AI Predictive KPIs (plan Pro/Enterprise) -->
    <div v-if="auth.hasAI && kpisIA" class="card">
      <div class="flex items-center gap-2 mb-4">
        <span class="text-gold font-semibold">✨ KPIs prédictifs IA</span>
        <span class="text-xs bg-gold/10 text-gold px-2 py-0.5 rounded-full">GPT-4o mini</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="kpi in kpisIA" :key="kpi.label" class="bg-slate-50 rounded-lg p-4">
          <p class="text-sm text-slate-500">{{ kpi.label }}</p>
          <p class="text-xl font-bold text-navy mt-1">{{ kpi.valeur }}</p>
          <p v-if="kpi.tendance" class="text-xs mt-1"
            :class="kpi.tendance === 'hausse' ? 'text-emerald-600' : 'text-red-500'">
            {{ kpi.tendance === 'hausse' ? '↗ Hausse' : '↘ Baisse' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Chiffre d'affaires -->
    <div class="card">
      <h3 class="font-semibold text-navy mb-4">Chiffre d'affaires</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <!-- KPI CA mois -->
        <div class="bg-emerald-50 rounded-xl p-4 flex flex-col gap-1">
          <span class="text-sm text-slate-500">CA du mois</span>
          <span class="text-2xl font-bold text-emerald-700">{{ formatCurrency(kpis.ca_mois) }}</span>
          <span class="text-xs text-slate-400">Sorties × prix vente HT</span>
        </div>
        <!-- KPI CA 7j -->
        <div class="bg-sky-50 rounded-xl p-4 flex flex-col gap-1">
          <span class="text-sm text-slate-500">CA — 7 derniers jours</span>
          <span class="text-2xl font-bold text-sky-700">{{ formatCurrency(kpis.ca_7j) }}</span>
          <span class="text-xs text-slate-400">Sorties × prix vente HT</span>
        </div>
        <!-- Graphique CA 7j -->
        <div class="flex flex-col justify-between">
          <span class="text-sm text-slate-500 mb-2">Évolution CA / jour</span>
          <div class="h-24 flex items-end gap-1">
            <div v-for="day in ca7jDetail" :key="day.date" class="flex-1 flex flex-col items-center gap-1">
              <div class="w-full bg-emerald-400 rounded-t transition-all"
                :style="{ height: caBarHeight(day.ca) }" :title="formatCurrency(day.ca)" />
              <span class="text-xs text-slate-400">{{ formatDate(day.date) }}</span>
            </div>
            <div v-if="ca7jDetail.length === 0" class="text-slate-400 text-xs text-center w-full py-4">
              Aucune vente
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Movements chart + Recent alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Movement chart (7 days) -->
      <div class="card lg:col-span-2">
        <h3 class="font-semibold text-navy mb-4">Mouvements — 7 derniers jours</h3>
        <div class="h-48 flex items-end gap-2">
          <div v-for="day in mouvements7j" :key="day.date"
            class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full flex gap-0.5 h-36 items-end">
              <div class="flex-1 bg-emerald-400 rounded-t transition-all"
                :style="{ height: barHeight(day.entrees) }" />
              <div class="flex-1 bg-red-400 rounded-t transition-all"
                :style="{ height: barHeight(day.sorties) }" />
            </div>
            <span class="text-xs text-slate-400">{{ formatDate(day.date) }}</span>
          </div>
        </div>
        <div class="flex gap-4 mt-3 text-xs text-slate-500">
          <span class="flex items-center gap-1"><span class="w-3 h-3 bg-emerald-400 rounded-sm inline-block"></span>Entrées</span>
          <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-400 rounded-sm inline-block"></span>Sorties</span>
        </div>
      </div>

      <!-- Recent stock alerts -->
      <div class="card">
        <h3 class="font-semibold text-navy mb-4">Alertes récentes</h3>
        <div v-if="alerts.alertes.length === 0 && alerts.ruptures.length === 0"
          class="text-slate-400 text-sm text-center py-6">Aucune alerte</div>
        <ul class="space-y-2">
          <li v-for="p in [...alerts.ruptures.slice(0,3), ...alerts.alertes.slice(0,3)]"
            :key="p.id"
            class="flex items-center justify-between text-sm py-1 border-b border-slate-100 last:border-0">
            <span class="text-slate-700 truncate">{{ p.nom }}</span>
            <span :class="p.quantite <= 0 ? 'badge-rupture' : 'badge-alerte'">
              {{ p.quantite <= 0 ? 'Rupture' : 'Alerte' }}
            </span>
          </li>
        </ul>
        <RouterLink to="/alerts" class="text-gold text-xs font-medium mt-3 block hover:underline">
          Voir toutes les alertes →
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useAlertsStore } from '@/stores/alerts'
import { dashboardApi } from '@/services/api'
import KpiCard from '@/components/KpiCard.vue'
import { format } from 'date-fns'
import { fr } from 'date-fns/locale'

const auth   = useAuthStore()
const alerts = useAlertsStore()

const kpis         = ref<any>({})
const kpisIA       = ref<any[]|null>(null)
const mouvements7j = ref<any[]>([])
const ca7jDetail   = ref<any[]>([])
const loading      = ref(true)

const bienvenueIA  = ref<any|null>(null)
const welcomeDismissed = ref(false)

const welcomeKey = computed(() => `sp_welcome_dismissed_${auth.user?.organisation_id ?? 'x'}`)

const showWelcome = computed(() =>
  !!bienvenueIA.value && bienvenueIA.value.nb_produits > 0 && !welcomeDismissed.value
)

function dismissWelcome() {
  welcomeDismissed.value = true
  localStorage.setItem(welcomeKey.value, '1')
}

const maxTotal = computed(() => {
  return Math.max(...mouvements7j.value.map(d => Number(d.entrees) + Number(d.sorties)), 1)
})

function barHeight(val: number): string {
  return `${(val / maxTotal.value) * 100}%`
}

const maxCa = computed(() => Math.max(...ca7jDetail.value.map(d => Number(d.ca)), 1))

function caBarHeight(val: number): string {
  return `${(Number(val) / maxCa.value) * 100}%`
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-TN', { style: 'currency', currency: 'TND' }).format(v ?? 0)
}

function formatDate(d: string) {
  return format(new Date(d), 'EEE', { locale: fr })
}

onMounted(async () => {
  const [dashRes] = await Promise.all([
    dashboardApi.index(),
    alerts.fetchStockAlerts(),
  ])
  kpis.value         = dashRes.data.kpis
  mouvements7j.value = dashRes.data.mouvements_7j ?? []
  ca7jDetail.value   = dashRes.data.ca_7j_detail ?? []
  kpisIA.value       = dashRes.data.kpis_ia ?? null
  bienvenueIA.value  = dashRes.data.bienvenue_ia ?? null
  welcomeDismissed.value = localStorage.getItem(welcomeKey.value) === '1'
  loading.value = false
})
</script>
