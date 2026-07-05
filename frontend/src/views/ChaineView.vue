<template>
  <div class="p-6 space-y-6">

    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-navy">Vue Chaîne</h1>
        <p class="text-slate-500 text-sm mt-1">Dashboard consolidé de tous vos points de vente</p>
      </div>
      <button @click="reload" :disabled="anyLoading"
        class="text-sm text-slate-400 hover:text-gold transition disabled:opacity-40">
        ↻ Actualiser
      </button>
    </div>

    <!-- ── BLOC 1 — Cartes récap ────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

      <!-- CA Total jour -->
      <div class="card p-5">
        <div v-if="loadingCA" class="animate-pulse space-y-3">
          <div class="h-3 bg-slate-200 rounded w-1/2"></div>
          <div class="h-8 bg-slate-200 rounded w-3/4"></div>
          <div class="h-3 bg-slate-200 rounded w-1/3"></div>
        </div>
        <template v-else>
          <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">CA total — Aujourd'hui</p>
          <p class="text-3xl font-bold text-navy mt-1">{{ money(ca.summary.ca_total_jour) }}</p>
          <p class="text-sm mt-1">
            <span v-if="ca.summary.delta_pct === null" class="text-slate-400">Hier : —</span>
            <template v-else>
              <span :class="ca.summary.delta_pct >= 0 ? 'text-emerald-600' : 'text-red-500'" class="font-semibold">
                {{ ca.summary.delta_pct >= 0 ? '+' : '' }}{{ ca.summary.delta_pct }}%
              </span>
              <span class="text-slate-400"> vs hier</span>
            </template>
          </p>
        </template>
      </div>

      <!-- Nb ventes jour -->
      <div class="card p-5">
        <div v-if="loadingCA" class="animate-pulse space-y-3">
          <div class="h-3 bg-slate-200 rounded w-1/2"></div>
          <div class="h-8 bg-slate-200 rounded w-1/3"></div>
          <div class="h-3 bg-slate-200 rounded w-2/3"></div>
        </div>
        <template v-else>
          <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Ventes — Aujourd'hui</p>
          <p class="text-3xl font-bold text-navy mt-1">{{ ca.summary.nb_ventes_total_jour }}</p>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            <span v-for="(r, i) in ca.summary.repartition" :key="r.nom">
              {{ r.nom }}&nbsp;: <strong class="text-slate-600">{{ r.nb_ventes }}</strong>
              <span v-if="i < ca.summary.repartition.length - 1"> · </span>
            </span>
          </p>
        </template>
      </div>

      <!-- Niveau entrepôt -->
      <div class="card p-5">
        <div v-if="loadingStock" class="animate-pulse space-y-3">
          <div class="h-3 bg-slate-200 rounded w-1/2"></div>
          <div class="h-8 bg-slate-200 rounded w-1/2"></div>
          <div class="h-3 bg-slate-200 rounded w-1/3"></div>
        </div>
        <template v-else>
          <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Niveau entrepôt</p>
          <div class="flex items-center gap-3 mt-1">
            <p class="text-3xl font-bold"
              :class="stock.entrepot_alertes_count > 0 ? 'text-red-600' : 'text-emerald-600'">
              {{ stock.entrepot_alertes_count }}
            </p>
            <span class="text-sm px-2 py-0.5 rounded-full font-medium"
              :class="stock.entrepot_alertes_count > 0
                ? 'bg-red-100 text-red-700'
                : 'bg-emerald-100 text-emerald-700'">
              {{ stock.entrepot_alertes_count > 0 ? 'alerte(s)' : 'Tout OK' }}
            </span>
          </div>
          <RouterLink to="/app/alerts"
            class="text-xs text-gold hover:underline mt-1 inline-block">
            Voir les alertes →
          </RouterLink>
        </template>
      </div>
    </div>

    <!-- ── BLOC 2 — CA par point de vente ────────────────────────────────── -->
    <div class="card p-5">
      <h2 class="text-base font-bold text-navy mb-4">CA par point de vente</h2>

      <div v-if="loadingCA" class="space-y-4">
        <div v-for="i in 3" :key="i" class="animate-pulse flex items-center gap-3">
          <div class="h-3 bg-slate-200 rounded w-28"></div>
          <div class="flex-1 h-6 bg-slate-200 rounded-full"></div>
          <div class="h-3 bg-slate-200 rounded w-20"></div>
        </div>
      </div>

      <div v-else-if="ca.points.length === 0" class="text-slate-400 text-sm py-4 text-center">
        Aucune vente aujourd'hui.
      </div>

      <div v-else class="space-y-3">
        <!-- Header légende -->
        <div class="flex items-center gap-3 text-xs text-slate-400 mb-1">
          <span class="w-32 text-right">Point de vente</span>
          <div class="flex-1 flex items-center gap-2 text-xs">
            <span class="inline-block w-3 h-3 rounded-sm bg-gold/80"></span> Cette semaine
            <span class="inline-block w-3 h-3 rounded-sm bg-slate-300 ml-2"></span> Semaine précédente
          </div>
          <span class="w-24 text-right">Aujourd'hui</span>
          <span class="w-24 text-right">Cette semaine</span>
        </div>

        <div v-for="p in ca.points" :key="p.id" class="flex items-center gap-3">
          <span class="w-32 text-sm text-right text-slate-600 truncate font-medium">{{ p.nom }}</span>

          <!-- Barre -->
          <div class="flex-1 relative h-6 bg-slate-100 rounded-full overflow-hidden">
            <!-- Semaine précédente (gris, derrière) -->
            <div class="absolute inset-y-0 left-0 bg-slate-300 rounded-full transition-all duration-500"
                 :style="{ width: barWidthSem(p.ca_semaine_precedente) + '%' }"></div>
            <!-- Cette semaine (or, devant) -->
            <div class="absolute inset-y-0 left-0 bg-gold/80 rounded-full transition-all duration-500"
                 :style="{ width: barWidthSem(p.ca_semaine) + '%' }"></div>
            <!-- Aujourd'hui (navy, fin) -->
            <div class="absolute inset-y-0 left-0 bg-navy/60 rounded-full transition-all duration-500"
                 :style="{ width: barWidthSem(p.ca_jour) + '%' }"></div>
          </div>

          <span class="w-24 text-right text-sm font-semibold text-navy">{{ money(p.ca_jour) }}</span>
          <span class="w-24 text-right text-sm text-slate-500">{{ money(p.ca_semaine) }}</span>
        </div>
      </div>
    </div>

    <!-- ── BLOC 3 — Stock critique par point ──────────────────────────────── -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-bold text-navy">Stock critique par point</h2>
        <button @click="showAllStock = !showAllStock"
          class="text-xs text-gold hover:underline font-medium">
          {{ showAllStock ? 'Masquer les produits OK' : 'Tout afficher' }}
        </button>
      </div>

      <div v-if="loadingStock" class="animate-pulse space-y-2">
        <div class="h-4 bg-slate-200 rounded w-full"></div>
        <div v-for="i in 4" :key="i" class="h-8 bg-slate-100 rounded w-full"></div>
      </div>

      <div v-else-if="displayedProducts.length === 0 && !showAllStock"
        class="text-emerald-600 text-sm py-4 text-center font-medium">
        ✓ Aucun produit en alerte ou rupture sur l'ensemble des points de vente.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm min-w-[500px]">
          <thead>
            <tr class="text-left border-b border-slate-200">
              <th class="pb-2 pr-4 text-slate-500 font-semibold">Produit</th>
              <th v-for="pdv in stock.points" :key="pdv.id"
                class="pb-2 px-2 text-center text-slate-500 font-semibold whitespace-nowrap">
                <span class="text-xs">
                  {{ pdv.nom }}
                  <span v-if="pdv.type === 'entrepot'"
                    class="ml-1 px-1 bg-slate-200 text-slate-500 rounded text-xs">E</span>
                </span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in displayedProducts" :key="p.id"
              class="border-b border-slate-50 hover:bg-slate-50">
              <td class="py-2 pr-4">
                <span class="font-medium text-navy">{{ p.nom }}</span>
                <span class="text-slate-400 text-xs ml-1">{{ p.unite }}</span>
                <span v-if="p.seuil_alerte > 0" class="text-slate-400 text-xs ml-2">
                  seuil: {{ p.seuil_alerte }}
                </span>
              </td>
              <td v-for="pdv in stock.points" :key="pdv.id" class="py-2 px-2 text-center">
                <template v-if="p.stock[pdv.id]">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold"
                    :class="{
                      'bg-red-100 text-red-700':    p.stock[pdv.id].statut === 'rupture',
                      'bg-orange-100 text-orange-700': p.stock[pdv.id].statut === 'alerte',
                      'bg-green-100 text-green-700':   p.stock[pdv.id].statut === 'ok',
                    }">
                    {{ p.stock[pdv.id].quantite }}
                  </span>
                </template>
                <span v-else class="text-slate-300 text-xs">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── BLOC 4 + 5 — Top plats + Transferts récents ────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

      <!-- Top 5 plats -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-navy">Top 5 plats</h2>
          <div class="flex gap-2 text-xs">
            <button @click="topPeriod = 'jour'"
              :class="topPeriod === 'jour'
                ? 'bg-navy text-white px-2 py-0.5 rounded'
                : 'text-slate-400 hover:text-navy px-2 py-0.5'">
              Aujourd'hui
            </button>
            <button @click="topPeriod = 'semaine'"
              :class="topPeriod === 'semaine'
                ? 'bg-navy text-white px-2 py-0.5 rounded'
                : 'text-slate-400 hover:text-navy px-2 py-0.5'">
              Cette semaine
            </button>
          </div>
        </div>

        <div v-if="loadingPlats" class="animate-pulse space-y-3">
          <div v-for="i in 5" :key="i" class="flex items-center gap-3">
            <div class="h-3 bg-slate-200 rounded flex-1"></div>
            <div class="h-3 bg-slate-200 rounded w-12"></div>
          </div>
        </div>

        <div v-else-if="currentTopPlats.length === 0"
          class="text-slate-400 text-sm py-4 text-center">
          Aucune vente{{ topPeriod === 'jour' ? " aujourd'hui" : ' cette semaine' }}.
        </div>

        <div v-else class="space-y-3">
          <div v-for="(plat, idx) in currentTopPlats" :key="plat.product_id"
            class="flex items-start gap-3">
            <span class="text-lg font-bold text-slate-200 w-6 text-center leading-none mt-0.5">
              {{ idx + 1 }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between">
                <span class="font-semibold text-navy truncate text-sm">{{ plat.nom }}</span>
                <span class="text-gold font-bold text-sm ml-2 flex-shrink-0">
                  {{ plat.total }} vente{{ plat.total !== 1 ? 's' : '' }}
                </span>
              </div>
              <!-- Mini barres par PDV -->
              <div class="mt-1 flex flex-wrap gap-1">
                <span v-for="pp in plat.par_point" :key="pp.nom"
                  class="text-xs text-slate-500">
                  <span class="text-slate-400">{{ pp.nom }}</span>&nbsp;
                  <strong class="text-slate-600">{{ pp.vendu }}</strong>
                </span>
              </div>
              <!-- Barre proportionnelle -->
              <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-gold rounded-full transition-all duration-500"
                     :style="{ width: platBarWidth(plat.total) + '%' }"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Transferts récents -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-bold text-navy">Transferts récents</h2>
          <RouterLink to="/app/transferts"
            class="text-xs text-gold hover:underline font-medium">
            Voir tous →
          </RouterLink>
        </div>

        <div v-if="loadingTransferts" class="animate-pulse space-y-3">
          <div v-for="i in 5" :key="i" class="h-10 bg-slate-100 rounded"></div>
        </div>

        <div v-else-if="transferts.length === 0"
          class="text-slate-400 text-sm py-4 text-center">
          Aucun transfert effectué.
        </div>

        <div v-else class="space-y-2">
          <div v-for="t in transferts" :key="t.id"
            class="flex items-start gap-3 py-2 border-b border-slate-50 last:border-0">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-navy">
                {{ t.point_source?.nom }}
                <span class="text-slate-400 mx-1">→</span>
                {{ t.point_dest?.nom }}
              </p>
              <p class="text-xs text-slate-400 mt-0.5">
                {{ formatDate(t.created_at) }}
                · {{ t.items_count }} produit{{ t.items_count !== 1 ? 's' : '' }}
                · {{ t.created_by?.prenom }} {{ t.created_by?.nom }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { chaineApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

// ── Types ──────────────────────────────────────────────────────────────────────
interface CaPoint {
  id: number; nom: string
  ca_jour: number; ca_hier: number
  ca_semaine: number; ca_semaine_precedente: number
  nb_ventes_jour: number; nb_ventes_semaine: number
}
interface CaData {
  summary: {
    ca_total_jour: number; ca_total_hier: number
    delta_pct: number | null
    nb_ventes_total_jour: number
    repartition: { nom: string; nb_ventes: number }[]
  }
  points: CaPoint[]
}
interface StockEntry  { quantite: number; statut: 'ok' | 'alerte' | 'rupture' }
interface StockProduit {
  id: number; nom: string; unite: string; seuil_alerte: number
  stock: Record<number, StockEntry>; has_alert: boolean
}
interface StockData {
  points: { id: number; nom: string; type: string }[]
  produits: StockProduit[]
  entrepot_alertes_count: number
}
interface TopPlat {
  product_id: number; nom: string; total: number
  par_point: { point_id: number | null; nom: string; vendu: number }[]
}
interface TopData  { top_jour: TopPlat[]; top_semaine: TopPlat[] }

// ── State ──────────────────────────────────────────────────────────────────────
const loadingCA        = ref(true)
const loadingStock     = ref(true)
const loadingPlats     = ref(true)
const loadingTransferts = ref(true)

const anyLoading = computed(() =>
  loadingCA.value || loadingStock.value || loadingPlats.value || loadingTransferts.value
)

const ca = ref<CaData>({
  summary: { ca_total_jour: 0, ca_total_hier: 0, delta_pct: null, nb_ventes_total_jour: 0, repartition: [] },
  points:  [],
})
const stock      = ref<StockData>({ points: [], produits: [], entrepot_alertes_count: 0 })
const plats      = ref<TopData>({ top_jour: [], top_semaine: [] })
const transferts = ref<any[]>([])

const showAllStock = ref(false)
const topPeriod    = ref<'jour' | 'semaine'>('jour')

// ── Computed ───────────────────────────────────────────────────────────────────
const displayedProducts = computed(() =>
  showAllStock.value
    ? stock.value.produits
    : stock.value.produits.filter(p => p.has_alert)
)

const currentTopPlats = computed(() =>
  topPeriod.value === 'jour' ? plats.value.top_jour : plats.value.top_semaine
)

// ── Bar chart helpers ──────────────────────────────────────────────────────────
const maxCaSem = computed(() =>
  Math.max(...ca.value.points.map(p => Math.max(p.ca_semaine, p.ca_semaine_precedente)), 1)
)
function barWidthSem(v: number): number {
  return Math.max(0, Math.min(100, (v / maxCaSem.value) * 100))
}

const maxTopTotal = computed(() =>
  Math.max(...currentTopPlats.value.map(p => p.total), 1)
)
function platBarWidth(v: number): number {
  return Math.max(0, Math.min(100, (v / maxTopTotal.value) * 100))
}

// ── Formatters ─────────────────────────────────────────────────────────────────
function money(v: number | null | undefined): string {
  return formatPrice(v)
}
function formatDate(d: string): string {
  const dt = new Date(d)
  const now = new Date()
  const isToday = dt.toDateString() === now.toDateString()
  const yesterday = new Date(now)
  yesterday.setDate(now.getDate() - 1)
  const isYesterday = dt.toDateString() === yesterday.toDateString()

  const time = dt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  if (isToday)     return `Aujourd'hui ${time}`
  if (isYesterday) return `Hier ${time}`
  return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) + ' ' + time
}

// ── Data loading ───────────────────────────────────────────────────────────────
async function loadCA() {
  loadingCA.value = true
  try {
    const { data } = await chaineApi.caParPoint()
    ca.value = data
  } catch {} finally {
    loadingCA.value = false
  }
}

async function loadStock() {
  loadingStock.value = true
  try {
    const { data } = await chaineApi.stockParPoint({ show_all: showAllStock.value ? 1 : 0 })
    stock.value = data
  } catch {} finally {
    loadingStock.value = false
  }
}

async function loadPlats() {
  loadingPlats.value = true
  try {
    const { data } = await chaineApi.topPlats()
    plats.value = data
  } catch {} finally {
    loadingPlats.value = false
  }
}

async function loadTransferts() {
  loadingTransferts.value = true
  try {
    const { data } = await chaineApi.transfertsRecents()
    transferts.value = data
  } catch {} finally {
    loadingTransferts.value = false
  }
}

async function reload() {
  await Promise.all([loadCA(), loadStock(), loadPlats(), loadTransferts()])
}

onMounted(reload)
</script>
