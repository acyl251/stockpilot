<template>
  <div class="p-6 space-y-6">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-navy">Transferts de stock</h1>
        <p class="text-slate-500 text-sm mt-1">Déplacez du stock entre vos points de vente</p>
      </div>
      <button v-if="!auth.isRestrictedOperateur" @click="openModal" class="btn-primary">+ Nouveau transfert</button>
    </div>

    <!-- ── Filtres ───────────────────────────────────────────────────── -->
    <div class="flex flex-wrap gap-3 items-center">
      <!-- Presets -->
      <div class="flex gap-1">
        <button v-for="p in presets" :key="p.key" @click="applyPreset(p.key)"
          :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition border',
            activePreset === p.key
              ? 'bg-navy text-white border-navy'
              : 'bg-white border-slate-200 text-slate-600 hover:border-gold hover:text-gold']">
          {{ p.label }}
        </button>
      </div>
      <!-- Du / Au -->
      <div class="flex items-center gap-2">
        <input v-model="filterDateDebut" type="date" @change="onFilterChange"
          class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        <span class="text-slate-400 text-sm">→</span>
        <input v-model="filterDateFin" type="date" @change="onFilterChange"
          class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
      </div>
      <!-- PDV filter -->
      <select v-if="points.length > 0" v-model="filterPoint" @change="onFilterChange"
        class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
        <option value="">Tous les points</option>
        <option v-for="pdv in points" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
      </select>
    </div>

    <!-- ── Historique ─────────────────────────────────────────────────── -->
    <div class="card p-0 overflow-hidden">
      <div v-if="loading" class="text-center py-12 text-slate-400">Chargement…</div>
      <div v-else-if="transferts.length === 0" class="text-center py-12 text-slate-400">
        Aucun transfert effectué.
      </div>
      <template v-else>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 text-slate-600 font-semibold">Date</th>
              <th class="text-left px-4 py-3 text-slate-600 font-semibold">Source → Destination</th>
              <th class="text-center px-4 py-3 text-slate-600 font-semibold">Produits</th>
              <th class="text-left px-4 py-3 text-slate-600 font-semibold">Par</th>
              <th class="text-left px-4 py-3 text-slate-600 font-semibold">Note</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in transferts" :key="t.id"
              class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
              <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(t.created_at) }}</td>
              <td class="px-4 py-3 font-medium text-navy">
                {{ t.point_source?.nom }}
                <span class="text-slate-400 mx-1">→</span>
                {{ t.point_dest?.nom }}
              </td>
              <td class="px-4 py-3 text-center text-slate-600">{{ t.items_count }}</td>
              <td class="px-4 py-3 text-slate-500 text-xs">
                {{ t.created_by?.prenom }} {{ t.created_by?.nom }}
              </td>
              <td class="px-4 py-3 text-slate-400 text-xs truncate max-w-xs">{{ t.note || '—' }}</td>
              <td class="px-4 py-3 text-right">
                <button @click="openDetail(t.id)"
                  class="text-gold hover:underline text-xs font-medium">Détail</button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
          <span>{{ pagination.total }} transfert{{ pagination.total !== 1 ? 's' : '' }}</span>
          <div class="flex gap-2">
            <button @click="changePage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Précédent</button>
            <span class="px-2 py-1">{{ pagination.current_page }} / {{ pagination.last_page || 1 }}</span>
            <button @click="changePage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Suivant</button>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Modal Nouveau transfert ────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">

        <!-- Header modal -->
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="text-lg font-bold text-navy">Nouveau transfert</h2>
          <button @click="closeModal" class="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
        </div>

        <div class="px-6 py-4 overflow-y-auto flex-1 space-y-4">

          <!-- Source / Destination -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label">Point source</label>
              <select v-model="form.point_source_id" @change="onSourceChange" class="input">
                <option value="">— Sélectionner —</option>
                <option v-for="pdv in points" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Point destination</label>
              <select v-model="form.point_dest_id" class="input">
                <option value="">— Sélectionner —</option>
                <option v-for="pdv in destPoints" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
              </select>
            </div>
          </div>
          <p v-if="form.point_source_id && form.point_dest_id && form.point_source_id === form.point_dest_id"
            class="text-red-500 text-xs -mt-2">
            La source et la destination doivent être différentes.
          </p>

          <!-- Lignes produits -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="form-label mb-0">Produits à transférer</label>
              <button @click="addLine" type="button"
                class="text-xs text-gold hover:underline font-medium">+ Ajouter un produit</button>
            </div>

            <div v-if="form.items.length === 0" class="text-slate-400 text-sm py-2">
              Cliquez sur "+ Ajouter un produit" pour commencer.
            </div>

            <div v-for="(line, idx) in form.items" :key="idx"
              class="flex gap-2 items-start mb-2">

              <!-- Sélecteur produit -->
              <div class="flex-1">
                <select v-model="line.product_id" @change="onProductChange(line)" class="input text-sm">
                  <option value="">— Produit —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">
                    {{ p.nom }} ({{ p.reference }})
                  </option>
                </select>
                <!-- Stock dispo sur la source -->
                <p v-if="line.product_id && form.point_source_id" class="text-xs mt-0.5"
                  :class="stockDispo(line) < line.quantite ? 'text-red-500' : 'text-slate-400'">
                  Dispo sur source : {{ stockDispo(line) }} {{ line.unite }}
                </p>
              </div>

              <!-- Quantité -->
              <div class="w-28">
                <input v-model.number="line.quantite" type="number" min="0.001" step="0.001"
                  class="input text-sm"
                  :class="stockDispo(line) < line.quantite && line.quantite > 0 ? 'border-red-400' : ''"
                  placeholder="Qté" />
              </div>

              <!-- Unité -->
              <div class="w-20">
                <input v-model="line.unite" class="input text-sm bg-slate-50" placeholder="unité" readonly />
              </div>

              <!-- Supprimer ligne -->
              <button @click="removeLine(idx)" type="button"
                class="mt-1 text-red-400 hover:text-red-600 text-lg leading-none">×</button>
            </div>
          </div>

          <!-- Note -->
          <div>
            <label class="form-label">Note (optionnel)</label>
            <textarea v-model="form.note" class="input" rows="2" placeholder="ex: Réassort PDV du marché"></textarea>
          </div>

          <!-- Erreur -->
          <p v-if="formError" class="text-red-600 text-sm bg-red-50 rounded-lg px-3 py-2">{{ formError }}</p>
        </div>

        <!-- Footer modal -->
        <div class="px-6 py-4 border-t border-slate-200 flex gap-3">
          <button @click="submitTransfert" :disabled="saving || !canSubmit"
            class="btn-primary flex-1 disabled:opacity-60">
            {{ saving ? 'Transfert en cours…' : 'Transférer' }}
          </button>
          <button @click="closeModal" class="btn-secondary flex-1">Annuler</button>
        </div>
      </div>
    </div>

    <!-- ── Modal Détail d'un transfert ────────────────────────────────── -->
    <div v-if="detail" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold text-navy">
              {{ detail.point_source?.nom }}
              <span class="text-slate-400 mx-1">→</span>
              {{ detail.point_dest?.nom }}
            </h2>
            <p class="text-slate-400 text-xs mt-0.5">{{ formatDate(detail.created_at) }}
              · par {{ detail.created_by?.prenom }} {{ detail.created_by?.nom }}</p>
          </div>
          <button @click="detail = null" class="text-slate-400 hover:text-slate-600 text-xl">×</button>
        </div>
        <div class="px-6 py-4 overflow-y-auto flex-1">
          <p v-if="detail.note" class="text-slate-500 text-sm mb-3 italic">{{ detail.note }}</p>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-slate-500 border-b text-xs uppercase">
                <th class="pb-2">Produit</th>
                <th class="pb-2 text-right">Quantité</th>
                <th class="pb-2 text-right">Unité</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in detail.items" :key="item.id" class="border-b last:border-0">
                <td class="py-2 font-medium text-navy">{{ item.product?.nom }}</td>
                <td class="py-2 text-right font-semibold text-emerald-700">{{ item.quantite }}</td>
                <td class="py-2 text-right text-slate-500">{{ item.unite || item.product?.unite_mesure }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-6 py-3 border-t border-slate-100">
          <button @click="detail = null" class="btn-secondary w-full py-2">Fermer</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { transfertsApi, pointsDeVenteApi, productsApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

// ── Types ──────────────────────────────────────────────────────────────────────
interface Pdv   { id: number; nom: string }
interface Prod  { id: number; nom: string; reference: string; unite_mesure: string }
interface Line  { product_id: number | ''; quantite: number; unite: string }
interface StockEntry { product_id: number; quantite: number; unite_mesure: string }

// ── State ──────────────────────────────────────────────────────────────────────
const auth        = useAuthStore()
const transferts  = ref<any[]>([])
const pagination  = ref({ current_page: 1, last_page: 1, total: 0 })
const loading     = ref(true)
const page        = ref(1)

// ── Filtres ────────────────────────────────────────────────────────────────────
const filterDateDebut = ref('')
const filterDateFin   = ref('')
const filterPoint     = ref<number | ''>('')
const activePreset    = ref('all')

const presets = [
  { key: 'today', label: "Aujourd'hui" },
  { key: 'week',  label: 'Cette semaine' },
  { key: 'all',   label: 'Tout' },
]

function applyPreset(key: string) {
  activePreset.value = key
  const now = new Date()
  const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  if (key === 'today') {
    filterDateDebut.value = todayStr
    filterDateFin.value   = todayStr
  } else if (key === 'week') {
    const mon = new Date(now)
    mon.setDate(now.getDate() - ((now.getDay() + 6) % 7))
    filterDateDebut.value = `${mon.getFullYear()}-${String(mon.getMonth() + 1).padStart(2, '0')}-${String(mon.getDate()).padStart(2, '0')}`
    filterDateFin.value   = todayStr
  } else {
    filterDateDebut.value = ''
    filterDateFin.value   = ''
  }
  page.value = 1
  fetchTransferts()
}

function onFilterChange() {
  activePreset.value = 'custom'
  page.value = 1
  fetchTransferts()
}

const points      = ref<Pdv[]>([])
const products    = ref<Prod[]>([])
const sourceStock = ref<StockEntry[]>([]) // stock du point source sélectionné

const showModal   = ref(false)
const saving      = ref(false)
const formError   = ref('')
const detail      = ref<any>(null)

const form = ref<{
  point_source_id: number | ''
  point_dest_id:   number | ''
  items:           Line[]
  note:            string
}>({
  point_source_id: '',
  point_dest_id:   '',
  items:           [],
  note:            '',
})

// ── Computed ───────────────────────────────────────────────────────────────────
const destPoints = computed(() =>
  points.value.filter(p => p.id !== form.value.point_source_id)
)

const canSubmit = computed(() => {
  if (!form.value.point_source_id || !form.value.point_dest_id) return false
  if (form.value.point_source_id === form.value.point_dest_id) return false
  if (form.value.items.length === 0) return false
  return form.value.items.every(l =>
    l.product_id !== '' && l.quantite > 0 && stockDispo(l) >= l.quantite
  )
})

// ── Helpers ────────────────────────────────────────────────────────────────────
function stockDispo(line: Line): number {
  if (!line.product_id || !form.value.point_source_id) return 0
  const entry = sourceStock.value.find(s => s.product_id === line.product_id)
  return entry ? entry.quantite : 0
}

function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

// ── Data loading ───────────────────────────────────────────────────────────────
async function fetchTransferts() {
  loading.value = true
  try {
    const { data } = await transfertsApi.list({
      page:         page.value,
      date_debut:   filterDateDebut.value || undefined,
      date_fin:     filterDateFin.value   || undefined,
      point_id:     filterPoint.value     || undefined,
    })
    transferts.value = data.data
    pagination.value = { current_page: data.current_page, last_page: data.last_page, total: data.total }
  } finally {
    loading.value = false
  }
}

async function loadSourceStock(pdvId: number) {
  sourceStock.value = []
  try {
    const { data } = await pointsDeVenteApi.stock(pdvId)
    sourceStock.value = data.stock
  } catch {}
}

async function loadProducts() {
  if (products.value.length) return
  try {
    // Load all active products (no pagination) for the select
    const { data } = await productsApi.list({ per_page: 500, actif: 1 })
    products.value = data.data ?? data
  } catch {}
}

// ── Event handlers ─────────────────────────────────────────────────────────────
async function onSourceChange() {
  // Reset dest if same as new source
  if (form.value.point_dest_id === form.value.point_source_id) {
    form.value.point_dest_id = ''
  }
  // Reset per-line stock data
  form.value.items.forEach(l => { /* stock will be recomputed from sourceStock */ })
  if (form.value.point_source_id) {
    await loadSourceStock(form.value.point_source_id as number)
  }
}

function onProductChange(line: Line) {
  const p = products.value.find(p => p.id === line.product_id)
  line.unite = p?.unite_mesure ?? ''
}

function addLine() {
  form.value.items.push({ product_id: '', quantite: 0, unite: '' })
}

function removeLine(idx: number) {
  form.value.items.splice(idx, 1)
}

async function openModal() {
  await loadProducts()
  form.value = { point_source_id: '', point_dest_id: '', items: [{ product_id: '', quantite: 0, unite: '' }], note: '' }
  formError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  sourceStock.value = []
}

async function openDetail(id: number) {
  try {
    const { data } = await transfertsApi.get(id)
    detail.value = data
  } catch {
    alert('Impossible de charger le détail.')
  }
}

function changePage(p: number) {
  if (p < 1 || p > pagination.value.last_page) return
  page.value = p
  fetchTransferts()
}

async function submitTransfert() {
  formError.value = ''
  saving.value    = true
  try {
    await transfertsApi.create({
      point_source_id: form.value.point_source_id,
      point_dest_id:   form.value.point_dest_id,
      items:           form.value.items.map(l => ({
        product_id: l.product_id,
        quantite:   l.quantite,
      })),
      note: form.value.note || null,
    })
    closeModal()
    page.value = 1
    await fetchTransferts()
  } catch (e: any) {
    const errors = e.response?.data?.errors as Record<string, string[]> | undefined
    const msg = e.response?.data?.message
      ?? (errors ? Object.values(errors)[0]?.[0] : undefined)
      ?? 'Erreur lors du transfert.'
    formError.value = msg
  } finally {
    saving.value = false
  }
}

// ── Init ───────────────────────────────────────────────────────────────────────
onMounted(async () => {
  const [, pdvRes] = await Promise.all([
    fetchTransferts(),
    pointsDeVenteApi.list(),
  ])
  points.value = pdvRes.data
})
</script>
