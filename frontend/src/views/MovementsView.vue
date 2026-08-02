<template>
  <div class="space-y-5">
    <!-- Mode scan rapide -->
    <div v-if="!auth.isRestrictedOperateur" class="card border-2 border-gold/30 bg-gold/5">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-navy flex items-center gap-2">📷 Mode scan rapide</h3>
        <span v-if="scanCount > 0" class="text-xs font-medium text-slate-500">{{ scanCount }} produit{{ scanCount > 1 ? 's' : '' }} scanné{{ scanCount > 1 ? 's' : '' }}</span>
      </div>

      <!-- Étape 1 : scan du code-barres -->
      <div v-if="scanStep === 'scan'">
        <input ref="scanInputRef" v-model="scanCode" @keyup.enter="onScanEnter"
          placeholder="Scannez un produit… (ou tapez le code + Entrée)"
          class="w-full border border-slate-300 rounded-lg px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-gold" />
        <p class="text-xs text-slate-400 mt-1">Ou appuyez sur le lecteur code-barres.</p>
      </div>

      <!-- Étape 2 : produit trouvé → quantité -->
      <div v-else-if="scanStep === 'quantity' && scanProduct" class="space-y-2">
        <div class="flex items-center justify-between bg-white rounded-lg px-4 py-2.5 border border-slate-200">
          <div>
            <p class="font-medium text-navy">{{ scanProduct.nom }}</p>
            <p class="text-xs text-slate-400">Stock actuel : {{ scanProduct.quantite }} {{ scanProduct.unite_mesure }}</p>
          </div>
          <button @click="resetScan" class="text-slate-400 hover:text-red-500 text-sm">Annuler</button>
        </div>
        <input ref="scanQtyRef" v-model.number="scanQuantite" type="number" min="0.001" step="0.001"
          @keyup.enter="confirmScanEntry"
          :placeholder="`Quantité à ajouter (${scanProduct.unite_mesure})`"
          class="w-full border border-slate-300 rounded-lg px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-gold" />
      </div>

      <!-- Produit inconnu -->
      <div v-if="scanNotFoundCode" class="mt-2 flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
        <span class="text-sm text-amber-700">⚠ Produit inconnu pour le code « {{ scanNotFoundCode }} ».</span>
        <button @click="createFromScan" class="text-xs font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 rounded-lg px-3 py-1.5 whitespace-nowrap">
          Créer ce produit
        </button>
      </div>

      <p v-if="scanToast" class="text-sm mt-2" :class="scanToastError ? 'text-red-500' : 'text-emerald-600 font-medium'">{{ scanToast }}</p>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div class="flex gap-3 flex-wrap">
        <select v-model="filterType" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les types</option>
          <option value="entree">Entrées</option>
          <option value="sortie">Sorties</option>
          <option value="ajustement">Ajustements</option>
        </select>
        <select v-if="auth.isAdmin && pointsDeVente.length > 0"
          v-model="filterPdv" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les points de vente</option>
          <option v-for="pdv in pointsDeVente" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
        </select>
        <input v-model="filterDateFrom" type="date" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />
        <input v-model="filterDateTo" type="date" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />
      </div>
      <button v-if="!auth.isRestrictedOperateur" @click="showForm = true" class="btn-primary">+ Nouveau mouvement</button>
    </div>

    <!-- Bandeau info opérateur multi-PDV -->
    <div v-if="auth.isRestrictedOperateur"
      class="flex items-center gap-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl px-4 py-3 text-sm">
      <span class="text-lg">ℹ️</span>
      <span>Le stock de votre point de vente est géré par l'administrateur via les transferts.</span>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Date</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Produit</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Type</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Quantité</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Avant → Après</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Opérateur</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Note</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.loading">
            <td colspan="7" class="text-center py-10 text-slate-400">Chargement…</td>
          </tr>
          <tr v-else-if="store.movements.length === 0">
            <td colspan="7" class="text-center py-10 text-slate-400">Aucun mouvement trouvé.</td>
          </tr>
          <tr v-for="m in store.movements" :key="m.id"
            class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(m.date_mouvement) }}</td>
            <td class="px-4 py-3 font-medium text-navy">{{ m.product?.nom }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold',
                m.type_mouvement === 'entree'     ? 'bg-emerald-100 text-emerald-700' :
                m.type_mouvement === 'sortie'     ? 'bg-red-100 text-red-700' :
                                                    'bg-blue-100 text-blue-700']">
                {{ { entree: 'Entrée', sortie: 'Sortie', ajustement: 'Ajust.' }[m.type_mouvement] }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold"
              :class="m.type_mouvement === 'entree' ? 'text-emerald-600' : 'text-red-600'">
              {{ m.type_mouvement === 'sortie' ? '-' : '+' }}{{ m.quantite }} {{ m.product?.unite_mesure }}
            </td>
            <td class="px-4 py-3 text-right text-slate-500 text-xs">
              {{ m.quantite_avant }} → {{ m.quantite_apres }}
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs">
              {{ m.user?.prenom }} {{ m.user?.nom }}
            </td>
            <td class="px-4 py-3 text-slate-400 text-xs truncate max-w-xs">{{ m.note }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Movement drawer -->
    <MovementDrawer v-if="showForm" @close="showForm = false" @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMovementsStore } from '@/stores/movements'
import { useAuthStore } from '@/stores/auth'
import { pointsDeVenteApi, productsApi, movementsApi } from '@/services/api'
import MovementDrawer from '@/components/MovementDrawer.vue'
import { format } from 'date-fns'
import { fr } from 'date-fns/locale'

const store          = useMovementsStore()
const auth           = useAuthStore()
const router         = useRouter()
const filterType     = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')
const filterPdv      = ref<number | ''>('')
const showForm       = ref(false)
const pointsDeVente  = ref<any[]>([])

// ── Mode scan rapide ─────────────────────────────────────────────────────────
const scanStep         = ref<'scan' | 'quantity'>('scan')
const scanCode         = ref('')
const scanQuantite     = ref<number | null>(null)
const scanProduct      = ref<any>(null)
const scanNotFoundCode = ref('')
const scanCount        = ref(0)
const scanToast        = ref('')
const scanToastError   = ref(false)
const scanInputRef     = ref<HTMLInputElement | null>(null)
const scanQtyRef       = ref<HTMLInputElement | null>(null)

function focusScanInput() {
  requestAnimationFrame(() => scanInputRef.value?.focus())
}

function resetScan() {
  scanStep.value     = 'scan'
  scanCode.value      = ''
  scanQuantite.value  = null
  scanProduct.value   = null
  scanNotFoundCode.value = ''
  focusScanInput()
}

async function onScanEnter() {
  const code = scanCode.value.trim()
  if (!code) return
  scanToast.value = ''
  scanNotFoundCode.value = ''

  try {
    const { data } = await productsApi.byBarcode(code)
    scanProduct.value  = data
    scanStep.value      = 'quantity'
    scanQuantite.value  = null
    await nextTick()
    scanQtyRef.value?.focus()
  } catch {
    scanNotFoundCode.value = code
    scanCode.value = ''
    focusScanInput()
  }
}

async function confirmScanEntry() {
  if (!scanProduct.value || !scanQuantite.value || scanQuantite.value <= 0) return
  try {
    await movementsApi.create({
      product_id:     scanProduct.value.id,
      type_mouvement: 'entree',
      quantite:       scanQuantite.value,
    })
    scanCount.value++
    scanToastError.value = false
    scanToast.value = `✅ +${scanQuantite.value} ${scanProduct.value.unite_mesure} de ${scanProduct.value.nom} ajouté`
    fetchMovements()
  } catch (e: any) {
    scanToastError.value = true
    scanToast.value = e.response?.data?.message ?? 'Erreur lors de l\'ajout au stock.'
  } finally {
    resetScan()
  }
}

function createFromScan() {
  router.push({ name: 'products', query: { create_barcode: scanNotFoundCode.value } })
}

function fetchMovements() {
  store.fetchMovements({
    type_mouvement:    filterType.value     || undefined,
    date_from:         filterDateFrom.value || undefined,
    date_to:           filterDateTo.value   || undefined,
    point_de_vente_id: filterPdv.value      || undefined,
  })
}

function formatDate(d: string) {
  return format(new Date(d), 'dd/MM/yyyy HH:mm', { locale: fr })
}

function onSaved() {
  showForm.value = false
  fetchMovements()
}

onMounted(async () => {
  if (auth.isAdmin) {
    try { const { data } = await pointsDeVenteApi.list(); pointsDeVente.value = data } catch {}
  }
  fetchMovements()
  focusScanInput()
})
</script>
