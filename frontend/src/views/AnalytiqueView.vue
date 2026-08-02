<template>
  <div class="space-y-5">
    <!-- Header + sélecteur période -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold text-navy">Analytique</h1>
      <div class="flex gap-1 bg-slate-100 rounded-lg p-1">
        <button v-for="p in periodes" :key="p.key" @click="changePeriode(p.key)"
          :class="['px-4 py-1.5 rounded-md text-sm font-medium transition-colors',
            periode === p.key ? 'bg-gold text-white shadow-sm' : 'text-slate-500 hover:text-slate-700']">
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- Skeleton loading -->
    <div v-if="loading" class="space-y-5">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="card h-24 animate-pulse bg-slate-100"></div>
      </div>
      <div class="card h-64 animate-pulse bg-slate-100"></div>
    </div>

    <template v-else>
      <!-- BLOC 1 — KPIs -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
          <p class="text-xs text-slate-500">Chiffre d'affaires</p>
          <p class="text-2xl font-bold text-navy mt-1">{{ formatPrice(kpis.ca) }}</p>
          <p v-if="kpis.ca_variation !== null" class="text-xs mt-1" :class="varClass(kpis.ca_variation)">
            {{ varLabel(kpis.ca_variation) }} vs période précédente
          </p>
        </div>
        <div class="card">
          <p class="text-xs text-slate-500">Nombre de ventes</p>
          <p class="text-2xl font-bold text-navy mt-1">{{ kpis.nb_ventes }}</p>
          <p v-if="kpis.nb_ventes_variation !== null" class="text-xs mt-1" :class="varClass(kpis.nb_ventes_variation)">
            {{ varLabel(kpis.nb_ventes_variation) }}
          </p>
        </div>
        <div class="card">
          <p class="text-xs text-slate-500">Ticket moyen</p>
          <p class="text-2xl font-bold text-navy mt-1">{{ formatPrice(kpis.ticket_moyen) }}</p>
          <p v-if="kpis.ticket_moyen_variation !== null" class="text-xs mt-1" :class="varClass(kpis.ticket_moyen_variation)">
            {{ varLabel(kpis.ticket_moyen_variation) }}
          </p>
        </div>
        <div class="card">
          <p class="text-xs text-slate-500">Nouveaux clients</p>
          <p class="text-2xl font-bold text-navy mt-1">{{ kpis.nouveaux_clients }}</p>
          <p class="text-xs text-slate-400 mt-1">{{ kpis.total_clients }} clients au total</p>
        </div>
      </div>

      <!-- BLOC 2 — CA par jour -->
      <div class="card">
        <h3 class="font-semibold text-navy mb-4">CA par jour</h3>
        <div v-if="!hasData(caParJour.current)" class="empty-state">
          <p class="text-3xl mb-2">📈</p>
          <p>Aucune donnée pour cette période.</p>
        </div>
        <div v-else class="h-64">
          <Line :data="caParJourChartData" :options="lineOptions" />
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- BLOC 3 — Top 10 produits -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Top 10 produits vendus</h3>
          <div v-if="topProduits.length === 0" class="empty-state">
            <p class="text-3xl mb-2">📦</p>
            <p>Aucune vente pour cette période.</p>
          </div>
          <div v-else class="h-72">
            <Bar :data="topProduitsChartData" :options="horizontalBarOptions" />
          </div>
        </div>

        <!-- BLOC 4 — Répartition paiements -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Répartition des paiements</h3>
          <div v-if="paiements.length === 0" class="empty-state">
            <p class="text-3xl mb-2">💳</p>
            <p>Aucune vente pour cette période.</p>
          </div>
          <div v-else class="flex items-center gap-6">
            <div class="w-48 h-48 flex-shrink-0">
              <Doughnut :data="paiementsChartData" :options="doughnutOptions" />
            </div>
            <div class="flex-1 space-y-2">
              <div v-for="p in paiements" :key="p.mode" class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: paiementColor(p.mode) }"></span>
                  {{ paiementLabel(p.mode) }}
                </span>
                <span class="font-semibold text-navy">{{ formatPrice(p.montant) }} <span class="text-slate-400 font-normal">({{ p.pourcentage }}%)</span></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- BLOC 5 — Clients avec ardoise -->
      <div class="card">
        <h3 class="font-semibold text-navy mb-4">Clients avec ardoise</h3>
        <div v-if="clientsArdoise.length === 0" class="empty-state">
          <p class="text-3xl mb-2">✅</p>
          <p>Aucun client débiteur.</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead class="text-slate-500 border-b border-slate-200">
            <tr>
              <th class="text-left py-2">Client</th>
              <th class="text-right py-2">Solde dû</th>
              <th class="text-right py-2">Dernière vente</th>
              <th class="py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in clientsArdoise" :key="c.id" class="border-b border-slate-100">
              <td class="py-2 font-medium text-navy">{{ c.nom }}</td>
              <td class="py-2 text-right text-red-600 font-semibold">{{ formatPrice(c.solde) }}</td>
              <td class="py-2 text-right text-slate-500">{{ c.derniere_vente ? fmtDate(c.derniere_vente) : '—' }}</td>
              <td class="py-2 text-right">
                <RouterLink to="/app/clients" class="text-gold hover:underline text-xs font-medium">Voir</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ═══ SECTION RESTAURATION ═══════════════════════════════════════════ -->
      <template v-if="auth.isRestauration">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- BLOC 6 — CA par service -->
          <div class="card">
            <h3 class="font-semibold text-navy mb-4">CA par service</h3>
            <div class="h-56"><Bar :data="caParServiceChartData" :options="barOptions" /></div>
          </div>

          <!-- BLOC 7 — Heures de pointe -->
          <div class="card">
            <h3 class="font-semibold text-navy mb-4">Heures de pointe</h3>
            <p v-if="heuresPointe.heure_pointe" class="text-sm text-slate-500 mb-3">
              Heure de pointe : <strong class="text-navy">{{ heuresPointe.heure_pointe.heure }}</strong>
              ({{ heuresPointe.heure_pointe.pourcentage }}% du CA)
            </p>
            <div class="h-56"><Bar :data="heuresPointeChartData" :options="barOptions" /></div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- BLOC 8 — Food cost global -->
          <div class="card">
            <h3 class="font-semibold text-navy mb-4">Food cost global</h3>
            <div v-if="foodCost.food_cost_global === null" class="empty-state">
              <p class="text-3xl mb-2">🍽</p>
              <p>Aucun plat vendu pour cette période.</p>
            </div>
            <template v-else>
              <div class="flex items-center gap-4 mb-4">
                <div class="text-3xl font-bold" :class="foodCostClass(foodCost.food_cost_global)">
                  {{ foodCost.food_cost_global }}%
                </div>
                <div class="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-3 rounded-full transition-all" :class="foodCostBg(foodCost.food_cost_global)"
                    :style="{ width: Math.min(foodCost.food_cost_global, 100) + '%' }" />
                </div>
              </div>
              <p class="text-xs text-slate-500 mb-2">Plats à surveiller (food cost le plus élevé)</p>
              <ul class="space-y-1">
                <li v-for="p in foodCost.top3_a_surveiller" :key="p.nom" class="flex justify-between text-sm py-1 border-b border-slate-100">
                  <span class="text-slate-700">{{ p.nom }}</span>
                  <span class="font-semibold" :class="foodCostClass(p.food_cost)">{{ p.food_cost }}%</span>
                </li>
              </ul>
            </template>
          </div>

          <!-- BLOC 9 — Tables -->
          <div class="card">
            <h3 class="font-semibold text-navy mb-4">Tables</h3>
            <div class="grid grid-cols-2 gap-3">
              <div class="bg-slate-50 rounded-xl p-3">
                <p class="text-xs text-slate-500">Taux d'occupation</p>
                <p class="text-xl font-bold text-navy">{{ tables.taux_occupation }}%</p>
                <p class="text-xs text-slate-400">{{ tables.tables_occupees }} / {{ tables.tables_total }}</p>
              </div>
              <div class="bg-slate-50 rounded-xl p-3">
                <p class="text-xs text-slate-500">Durée moyenne</p>
                <p class="text-xl font-bold text-navy">{{ tables.duree_moyenne_min ? Math.round(tables.duree_moyenne_min) + ' min' : '—' }}</p>
              </div>
              <div class="bg-slate-50 rounded-xl p-3 col-span-2">
                <p class="text-xs text-slate-500">Table la plus rentable</p>
                <p v-if="tables.table_plus_rentable" class="text-lg font-bold text-navy">
                  Table {{ tables.table_plus_rentable.numero }}
                  <span class="text-sm font-normal text-emerald-600">{{ formatPrice(tables.table_plus_rentable.ca) }}</span>
                </p>
                <p v-else class="text-sm text-slate-400">Aucune donnée</p>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ═══ SECTION COMMERCE ═══════════════════════════════════════════════ -->
      <template v-else-if="auth.isCommerce">
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Ventes détail vs gros</h3>
          <div v-if="!hasData(ventesDetailGros.detail) && !hasData(ventesDetailGros.gros)" class="empty-state">
            <p class="text-3xl mb-2">📊</p>
            <p>Aucune donnée pour cette période.</p>
          </div>
          <div v-else class="h-64"><Bar :data="detailGrosChartData" :options="barOptions" /></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- BLOC 7 — Rotation des stocks -->
          <div class="card">
            <h3 class="font-semibold text-navy mb-4">Rotation des stocks</h3>
            <div class="space-y-4">
              <div>
                <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide mb-2">Rotation rapide</p>
                <div v-for="p in rotationStocks.rotation_rapide" :key="p.nom" class="flex justify-between text-sm py-1 border-b border-slate-100">
                  <span class="text-slate-700">{{ p.nom }}</span>
                  <span class="font-medium text-slate-500">{{ p.jours_ecoulement }} j</span>
                </div>
                <p v-if="rotationStocks.rotation_rapide.length === 0" class="text-xs text-slate-400">Aucune donnée</p>
              </div>
              <div>
                <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide mb-2">Rotation lente</p>
                <div v-for="p in rotationStocks.rotation_lente" :key="p.nom" class="flex justify-between text-sm py-1 border-b border-slate-100">
                  <span class="text-slate-700">{{ p.nom }}</span>
                  <span class="font-medium text-slate-500">{{ p.jours_ecoulement ?? '—' }} {{ p.jours_ecoulement ? 'j' : '' }}</span>
                </div>
                <p v-if="rotationStocks.rotation_lente.length === 0" class="text-xs text-slate-400">Aucune donnée</p>
              </div>
            </div>
          </div>

          <!-- BLOC 8 — Marge brute par produit -->
          <div class="card">
            <h3 class="font-semibold text-navy mb-4">Marge brute par produit</h3>
            <div v-if="margeProduits.length === 0" class="empty-state">
              <p class="text-3xl mb-2">💰</p>
              <p>Aucune vente pour cette période.</p>
            </div>
            <table v-else class="w-full text-sm">
              <thead class="text-slate-500 border-b border-slate-200">
                <tr><th class="text-left py-2">Produit</th><th class="text-right py-2">Marge</th><th class="text-right py-2">%</th></tr>
              </thead>
              <tbody>
                <tr v-for="p in margeProduits" :key="p.nom" class="border-b border-slate-100">
                  <td class="py-2 text-slate-700">{{ p.nom }}</td>
                  <td class="py-2 text-right font-semibold text-emerald-600">{{ formatPrice(p.marge_dt) }}</td>
                  <td class="py-2 text-right text-slate-500">{{ p.marge_pct }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- BLOC 9 — Clients fidèles -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Clients fidèles</h3>
          <div v-if="clientsFideles.length === 0" class="empty-state">
            <p class="text-3xl mb-2">👥</p>
            <p>Aucun client sur cette période.</p>
          </div>
          <table v-else class="w-full text-sm">
            <thead class="text-slate-500 border-b border-slate-200">
              <tr>
                <th class="text-left py-2">Client</th>
                <th class="text-right py-2">Nb achats</th>
                <th class="text-right py-2">CA total</th>
                <th class="text-right py-2">Dernière visite</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in clientsFideles" :key="c.id" class="border-b border-slate-100">
                <td class="py-2 font-medium text-navy">{{ c.nom }}</td>
                <td class="py-2 text-right text-slate-500">{{ c.nb_achats }}</td>
                <td class="py-2 text-right font-semibold text-navy">{{ formatPrice(c.ca_total) }}</td>
                <td class="py-2 text-right text-slate-500">{{ fmtDate(c.derniere_visite) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS, Title, Tooltip, Legend, LineElement, PointElement,
  CategoryScale, LinearScale, BarElement, ArcElement,
} from 'chart.js'
import { useAuthStore } from '@/stores/auth'
import { analytiqueApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, BarElement, ArcElement)

const auth = useAuthStore()

const GOLD  = '#F59E0B'
const NAVY  = '#0F172A'
const GREY  = '#94A3B8'
const GREEN = '#10B981'
const RED   = '#EF4444'

const periodes: { key: 'today' | 'week' | 'month'; label: string }[] = [
  { key: 'today', label: "Aujourd'hui" },
  { key: 'week',  label: 'Cette semaine' },
  { key: 'month', label: 'Ce mois' },
]
const periode = ref<'today' | 'week' | 'month'>('today')
const loading = ref(true)

const kpis            = ref<any>({ ca: 0, ca_variation: null, nb_ventes: 0, nb_ventes_variation: null, ticket_moyen: 0, ticket_moyen_variation: null, nouveaux_clients: 0, total_clients: 0 })
const caParJour        = ref<any>({ labels: [], current: [], previous: [] })
const topProduits      = ref<any[]>([])
const paiements        = ref<any[]>([])
const clientsArdoise    = ref<any[]>([])
const caParService      = ref<any>({ midi: { ca: 0, nb: 0 }, soir: { ca: 0, nb: 0 }, autre: { ca: 0, nb: 0 } })
const heuresPointe      = ref<any>({ heures: [], heure_pointe: null })
const foodCost          = ref<any>({ food_cost_global: null, top3_a_surveiller: [] })
const tables            = ref<any>({ taux_occupation: 0, tables_occupees: 0, tables_total: 0, duree_moyenne_min: null, table_plus_rentable: null })
const ventesDetailGros  = ref<any>({ labels: [], detail: [], gros: [] })
const rotationStocks    = ref<any>({ rotation_rapide: [], rotation_lente: [] })
const margeProduits     = ref<any[]>([])
const clientsFideles    = ref<any[]>([])

function hasData(arr: number[]): boolean {
  return arr.some((v) => v > 0)
}

async function loadAll() {
  loading.value = true
  try {
    const common = [
      analytiqueApi.kpis(periode.value).then((r) => (kpis.value = r.data)),
      analytiqueApi.caParJour(periode.value === 'today' ? 'week' : periode.value).then((r) => (caParJour.value = r.data)),
      analytiqueApi.topProduits(periode.value).then((r) => (topProduits.value = r.data)),
      analytiqueApi.paiements(periode.value).then((r) => (paiements.value = r.data)),
      analytiqueApi.clientsArdoise().then((r) => (clientsArdoise.value = r.data)),
    ]

    const sector = auth.isRestauration
      ? [
          analytiqueApi.caParService(periode.value).then((r) => (caParService.value = r.data)),
          analytiqueApi.heuresPointe(periode.value).then((r) => (heuresPointe.value = r.data)),
          analytiqueApi.foodCost(periode.value).then((r) => (foodCost.value = r.data)),
          analytiqueApi.tables(periode.value).then((r) => (tables.value = r.data)),
        ]
      : [
          analytiqueApi.ventesDetailGros(periode.value).then((r) => (ventesDetailGros.value = r.data)),
          analytiqueApi.rotationStocks().then((r) => (rotationStocks.value = r.data)),
          analytiqueApi.margeProduits(periode.value).then((r) => (margeProduits.value = r.data)),
          analytiqueApi.clientsFideles(periode.value === 'today' ? 'month' : periode.value).then((r) => (clientsFideles.value = r.data)),
        ]

    await Promise.all([...common, ...sector])
  } finally {
    loading.value = false
  }
}

function changePeriode(p: 'today' | 'week' | 'month') {
  periode.value = p
  loadAll()
}

// ── Chart datasets ───────────────────────────────────────────────────────────
const caParJourChartData = computed(() => ({
  labels: caParJour.value.labels,
  datasets: [
    { label: 'Période actuelle', data: caParJour.value.current, borderColor: GOLD, backgroundColor: GOLD, tension: 0.3 },
    { label: 'Période précédente', data: caParJour.value.previous, borderColor: GREY, backgroundColor: GREY, tension: 0.3, borderDash: [4, 4] },
  ],
}))
const lineOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' as const } } }

const topProduitsChartData = computed(() => ({
  labels: topProduits.value.map((p) => p.nom),
  datasets: [{ label: 'CA', data: topProduits.value.map((p) => p.ca), backgroundColor: GOLD, borderRadius: 4 }],
}))
const horizontalBarOptions = { indexAxis: 'y' as const, responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' as const } } }

function paiementColor(mode: string) {
  return { especes: GREEN, carte: NAVY, credit: RED }[mode] ?? GREY
}
function paiementLabel(mode: string) {
  return { especes: 'Espèces', carte: 'Carte', credit: 'Crédit' }[mode] ?? mode
}
const paiementsChartData = computed(() => ({
  labels: paiements.value.map((p) => paiementLabel(p.mode)),
  datasets: [{ data: paiements.value.map((p) => p.montant), backgroundColor: paiements.value.map((p) => paiementColor(p.mode)) }],
}))
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }

const caParServiceChartData = computed(() => ({
  labels: ['Midi', 'Soir', 'Autre'],
  datasets: [{
    label: 'CA',
    data: [caParService.value.midi.ca, caParService.value.soir.ca, caParService.value.autre.ca],
    backgroundColor: [GOLD, NAVY, GREY],
    borderRadius: 4,
  }],
}))

const heuresPointeChartData = computed(() => ({
  labels: heuresPointe.value.heures.map((h: any) => h.heure),
  datasets: [{ label: 'CA', data: heuresPointe.value.heures.map((h: any) => h.ca), backgroundColor: GOLD, borderRadius: 3 }],
}))

const detailGrosChartData = computed(() => ({
  labels: ventesDetailGros.value.labels,
  datasets: [
    { label: 'Détail', data: ventesDetailGros.value.detail, backgroundColor: NAVY, borderRadius: 4 },
    { label: 'Gros', data: ventesDetailGros.value.gros, backgroundColor: GOLD, borderRadius: 4 },
  ],
}))

// ── Helpers ──────────────────────────────────────────────────────────────────
function varClass(v: number | null) {
  if (v === null) return 'text-slate-400'
  return v >= 0 ? 'text-emerald-600' : 'text-red-500'
}
function varLabel(v: number | null) {
  if (v === null) return ''
  return `${v >= 0 ? '▲' : '▼'} ${v >= 0 ? '+' : ''}${v}%`
}
function foodCostClass(pct: number) {
  return pct < 35 ? 'text-emerald-600' : pct <= 45 ? 'text-amber-600' : 'text-red-600'
}
function foodCostBg(pct: number) {
  return pct < 35 ? 'bg-emerald-500' : pct <= 45 ? 'bg-amber-500' : 'bg-red-500'
}
function fmtDate(d: string) {
  return d ? new Date(d).toLocaleDateString('fr-FR') : '—'
}

onMounted(loadAll)
</script>

<style scoped>
.empty-state {
  @apply text-center py-10 text-slate-400 text-sm;
}
</style>
