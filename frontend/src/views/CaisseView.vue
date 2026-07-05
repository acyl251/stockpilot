<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 h-full">
    <!-- ── Catalogue (sélection produits) ─────────────────────────────── -->
    <div class="lg:col-span-2 space-y-4">

      <!-- Sélecteur PDV pour admin sans point rattaché -->
      <div v-if="needsPdvSelector" class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5">
        <span class="text-amber-700 text-sm font-medium whitespace-nowrap">Vendre depuis :</span>
        <select v-model="caissePointId" class="flex-1 border border-amber-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold bg-white">
          <option :value="null">— Choisir un point de vente —</option>
          <option v-for="pdv in pointsVente" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
        </select>
      </div>
      <!-- Info PDV pour admin (PDV assigné ou auto-sélectionné en mono-PDV) -->
      <div v-else-if="auth.isAdmin && effectivePdvNom" class="text-xs text-slate-400 px-1">
        Vente depuis : <span class="font-medium text-navy">{{ effectivePdvNom }}</span>
      </div>

      <div class="flex gap-3">
        <input v-model="search" @input="debouncedFetch"
          placeholder="Rechercher un produit par nom ou référence…"
          class="flex-1 border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        <input v-model="barcode" @keyup.enter="scanBarcode" ref="barcodeInput"
          placeholder="📷 Code-barres / réf. (Entrée)"
          class="w-64 border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
      </div>
      <p v-if="scanMsg" class="text-xs" :class="scanError ? 'text-red-500' : 'text-emerald-600'">{{ scanMsg }}</p>

      <div v-if="loading" class="text-center py-10 text-slate-400">Chargement…</div>
      <div v-else-if="products.length === 0" class="text-center py-10 text-slate-400">
        Aucun produit disponible.
      </div>

      <div v-else class="space-y-4">
        <!-- Plats -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
          <button v-for="p in products" :key="p.id"
            @click="addToCart(p)"
            :disabled="availableStock(p) <= 0"
            class="card p-3 text-left hover:ring-2 hover:ring-gold transition disabled:opacity-40 disabled:cursor-not-allowed flex flex-col">
            <p class="font-semibold text-navy text-sm leading-snug line-clamp-2">{{ p.nom }}</p>
            <p class="text-slate-400 text-xs font-mono mt-0.5">{{ p.reference }}</p>
            <div class="mt-auto pt-2 flex items-center justify-between">
              <span class="text-gold font-bold text-sm">{{ money(p.prix_vente_ttc) }}</span>
              <span class="text-xs"
                :class="!isCompose(p) && availableStock(p) <= 0 ? 'text-red-500' : 'text-slate-500'">
                {{ isCompose(p) ? 'Recette' : `Stock : ${availableStock(p)}` }}
              </span>
            </div>
          </button>
        </div>

        <!-- Suppléments (restauration) -->
        <template v-if="supplements.length > 0">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider pt-1">Suppléments</p>
          <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
            <button v-for="s in supplements" :key="`supp-${s.id}`"
              @click="addSupplementToCart(s)"
              class="card p-3 text-left hover:ring-2 hover:ring-amber-400 transition flex flex-col bg-amber-50/40">
              <p class="font-semibold text-navy text-sm leading-snug line-clamp-2">{{ s.nom }}</p>
              <div class="mt-auto pt-2 flex items-center justify-between">
                <span class="text-amber-600 font-bold text-sm">{{ money(s.prix_vente) }}</span>
                <span class="text-xs text-slate-400">{{ s.quantite }} {{ s.unite || '' }}</span>
              </div>
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Panier + paiement ──────────────────────────────────────────── -->
    <div class="card p-0 flex flex-col h-full sticky top-0">
      <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-bold text-navy">Panier</h2>
        <button v-if="cart.length" @click="cart = []"
          class="text-xs text-red-500 hover:text-red-700">Vider</button>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-slate-100 min-h-[120px]">
        <p v-if="cart.length === 0" class="text-center text-slate-400 text-sm py-10">
          Cliquez sur un produit pour l'ajouter.
        </p>
        <div v-for="line in cart" :key="line.id" class="px-4 py-2.5 flex items-center gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-navy truncate">{{ line.nom }}</p>
            <p class="text-xs text-slate-400">{{ money(line.prix_vente_ttc) }} × {{ line.qty }}</p>
          </div>
          <div class="flex items-center gap-1">
            <button @click="dec(line)" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-navy">−</button>
            <span class="w-6 text-center text-sm">{{ line.qty }}</span>
            <button @click="inc(line)" :disabled="line.qty >= line.stock"
              class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-navy disabled:opacity-40">+</button>
          </div>
          <span class="w-20 text-right text-sm font-semibold text-navy">{{ money(line.prix_vente_ttc * line.qty) }}</span>
          <button @click="removeLine(line)" class="text-slate-300 hover:text-red-500">✕</button>
        </div>
      </div>

      <!-- Totaux + paiement -->
      <div class="border-t border-slate-200 p-4 space-y-3">
        <div class="flex justify-between text-sm text-slate-500">
          <span>Total HT</span><span>{{ money(totalHt) }}</span>
        </div>
        <div class="flex justify-between text-sm text-slate-500">
          <span>TVA</span><span>{{ money(totalTva) }}</span>
        </div>
        <div class="flex justify-between text-sm text-slate-500">
          <span>Sous-total TTC</span><span>{{ money(grossTtc) }}</span>
        </div>

        <!-- Remise -->
        <div class="flex gap-2 items-center">
          <select v-model="remiseType"
            class="border border-slate-300 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
            <option :value="null">Sans remise</option>
            <option value="pourcentage">Remise %</option>
            <option value="montant">Remise TND</option>
          </select>
          <input v-if="remiseType" v-model.number="remiseValeur" type="number" min="0" step="0.001"
            :placeholder="remiseType === 'pourcentage' ? '%' : 'TND'"
            class="flex-1 w-20 border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
        <div v-if="remiseMontant > 0" class="flex justify-between text-sm text-red-500">
          <span>Remise</span><span>− {{ money(remiseMontant) }}</span>
        </div>

        <div class="flex justify-between text-lg font-bold text-navy">
          <span>Total TTC</span><span>{{ money(totalTtc) }}</span>
        </div>

        <div class="grid grid-cols-3 gap-2 pt-1">
          <button @click="mode = 'especes'"
            :class="['py-2 rounded-lg text-sm font-semibold border',
              mode === 'especes' ? 'bg-navy text-white border-navy' : 'border-slate-300 text-slate-600']">
            Espèces
          </button>
          <button @click="mode = 'carte'"
            :class="['py-2 rounded-lg text-sm font-semibold border',
              mode === 'carte' ? 'bg-navy text-white border-navy' : 'border-slate-300 text-slate-600']">
            Carte
          </button>
          <button @click="mode = 'credit'"
            :class="['py-2 rounded-lg text-sm font-semibold border',
              mode === 'credit' ? 'bg-amber-500 text-white border-amber-500' : 'border-slate-300 text-slate-600']">
            Plus tard
          </button>
        </div>

        <div v-if="mode === 'especes'" class="space-y-2">
          <input v-model.number="montantPaye" type="number" step="0.001" min="0"
            placeholder="Montant reçu"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
          <div v-if="montantPaye" class="flex justify-between text-sm font-semibold"
            :class="rendu >= 0 ? 'text-emerald-600' : 'text-red-600'">
            <span>Monnaie à rendre</span><span>{{ money(rendu) }}</span>
          </div>
        </div>

        <div v-if="mode === 'carte'" class="space-y-3 bg-indigo-50 rounded-lg p-3">
          <div class="text-center">
            <p class="text-xs text-indigo-500 font-medium uppercase tracking-wide mb-1">Montant à encaisser sur le TPE</p>
            <p class="text-3xl font-bold text-indigo-700">{{ money(totalTtc) }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">N° transaction TPE <span class="text-slate-400">(optionnel)</span></label>
            <input v-model="referenceCarte" type="text" placeholder="ex: 000123456789"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          </div>
        </div>

        <!-- Crédit : sélection / création du client -->
        <div v-if="mode === 'credit'" class="space-y-2 bg-amber-50 rounded-lg p-3">
          <p class="text-xs text-amber-700 font-medium">Vente à crédit — rattachée à un client</p>
          <div class="relative">
            <input v-model="clientSearch" @input="searchClients" @focus="searchClients"
              placeholder="Rechercher / saisir le nom du client…"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
            <ul v-if="clientResults.length && !selectedClient"
              class="absolute z-10 left-0 right-0 bg-white border border-slate-200 rounded-lg mt-1 max-h-40 overflow-y-auto shadow">
              <li v-for="c in clientResults" :key="c.id" @click="pickClient(c)"
                class="px-3 py-2 text-sm hover:bg-slate-50 cursor-pointer flex justify-between">
                <span>{{ c.nom }} <span class="text-slate-400">{{ c.telephone }}</span></span>
                <span v-if="Number(c.solde) > 0" class="text-red-500 text-xs">doit {{ money(c.solde) }}</span>
              </li>
            </ul>
          </div>
          <div v-if="selectedClient" class="text-xs text-emerald-700">
            Client : <strong>{{ selectedClient.nom }}</strong>
            <button @click="clearClient" class="text-slate-400 hover:text-red-500 ml-1">✕</button>
          </div>
          <input v-else v-model="clientTelephone" placeholder="Téléphone (optionnel, nouveau client)"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
          <input v-model.number="montantPaye" type="number" step="0.001" min="0"
            placeholder="Acompte versé maintenant (optionnel)"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
          <div class="flex justify-between text-sm font-semibold text-amber-700">
            <span>Reste à devoir</span><span>{{ money(Math.max(0, totalTtc - (montantPaye ?? 0))) }}</span>
          </div>
        </div>

        <p v-if="error" class="text-red-600 text-xs">{{ error }}</p>

        <button @click="validate" :disabled="!canValidate || submitting"
          class="btn-primary w-full py-2.5 disabled:opacity-50">
          {{ submitting ? 'Encaissement…' : mode === 'carte' ? 'Confirmer le paiement carte' : 'Encaisser' }}
        </button>
      </div>
    </div>

    <!-- ── Modal alerte stock insuffisant (restauration) ───────────────── -->
    <div v-if="showStockWarning" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="p-5 border-b border-slate-200 flex items-center gap-3">
          <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
          </div>
          <div>
            <h2 class="font-bold text-navy">Stock insuffisant</h2>
            <p class="text-xs text-slate-500 mt-0.5">Certains ingrédients manquent pour cette commande</p>
          </div>
        </div>
        <div class="p-5 space-y-2 max-h-64 overflow-y-auto">
          <div v-for="w in stockWarnings" :key="w.ingredient"
            class="flex items-start gap-2 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2 text-sm">
            <span class="text-orange-500 mt-0.5">⚠</span>
            <div>
              <span class="font-medium text-navy">{{ w.ingredient }}</span>
              <span class="text-slate-500"> — il manque </span>
              <span class="font-semibold text-red-600">{{ w.manque }} {{ w.unite }}</span>
              <span class="text-slate-400 text-xs block">Stock actuel : {{ w.stock_actuel }} {{ w.unite }} · Besoin : {{ w.quantite_necessaire }} {{ w.unite }}</span>
            </div>
          </div>
        </div>
        <div class="flex gap-3 p-5 border-t border-slate-200">
          <button @click="cancelStockWarning"
            class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium hover:bg-slate-50 transition-colors">
            Annuler
          </button>
          <button @click="confirmSaleAnyway" :disabled="submitting"
            class="flex-1 py-2.5 rounded-lg bg-orange-500 text-white font-medium hover:bg-orange-600 transition-colors disabled:opacity-50">
            {{ submitting ? 'Encaissement…' : 'Encaisser quand même' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Reçu (modal imprimable) ────────────────────────────────────── -->
    <div v-if="receipt" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg w-full max-w-sm max-h-[90vh] overflow-y-auto">
        <div id="receipt" class="p-6 text-sm">
          <div class="text-center mb-4">
            <p class="font-bold text-lg">{{ orgName }}</p>
            <p class="text-slate-500">Reçu de caisse</p>
            <p class="text-slate-500">{{ receipt.numero }}</p>
            <p class="text-slate-400 text-xs">{{ formatDate(receipt.date_vente) }}</p>
          </div>
          <table class="w-full mb-3">
            <tbody>
              <tr v-for="it in receipt.items" :key="it.id" class="align-top">
                <td class="py-1">{{ it.designation }}<br><span class="text-slate-400 text-xs">{{ Number(it.quantite) }} × {{ money(it.prix_unitaire_ttc) }}</span></td>
                <td class="py-1 text-right">{{ money(it.total_ligne_ttc) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="border-t border-dashed border-slate-300 pt-2 space-y-1">
            <div class="flex justify-between text-slate-500"><span>Total HT</span><span>{{ money(receipt.total_ht) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>TVA</span><span>{{ money(receipt.total_tva) }}</span></div>
            <div v-if="Number(receipt.remise_montant) > 0" class="flex justify-between text-slate-500"><span>Remise</span><span>− {{ money(receipt.remise_montant) }}</span></div>
            <div class="flex justify-between font-bold text-base"><span>Total TTC</span><span>{{ money(receipt.total_ttc) }}</span></div>
            <div class="flex justify-between">
              <span>Paiement</span>
              <span>{{ receipt.mode_paiement === 'credit' ? 'Crédit' : receipt.mode_paiement === 'carte' ? 'Carte bancaire' : 'Espèces' }}</span>
            </div>
            <div v-if="receipt.mode_paiement === 'carte' && receipt.reference_carte" class="flex justify-between text-slate-500">
              <span>Réf. TPE</span><span class="font-mono">{{ receipt.reference_carte }}</span>
            </div>
            <template v-if="receipt.mode_paiement === 'especes' && receipt.montant_paye">
              <div class="flex justify-between"><span>Reçu</span><span>{{ money(receipt.montant_paye) }}</span></div>
              <div class="flex justify-between"><span>Rendu</span><span>{{ money(receipt.monnaie_rendue) }}</span></div>
            </template>
            <template v-if="receipt.mode_paiement === 'credit'">
              <div v-if="receipt.client" class="flex justify-between"><span>Client</span><span>{{ receipt.client.nom }}</span></div>
              <div v-if="Number(receipt.montant_regle) > 0" class="flex justify-between"><span>Acompte</span><span>{{ money(receipt.montant_regle) }}</span></div>
              <div class="flex justify-between font-bold text-red-600"><span>Reste à payer</span><span>{{ money(receipt.reste_a_payer) }}</span></div>
            </template>
          </div>
          <p class="text-center text-slate-400 text-xs mt-4">Merci de votre visite !</p>
        </div>
        <div class="flex gap-2 p-4 border-t border-slate-200 no-print">
          <button @click="doPrint" class="btn-primary flex-1 py-2">Imprimer</button>
          <button @click="receipt = null" class="flex-1 py-2 rounded-lg border border-slate-300 text-slate-600">Fermer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { productsApi, salesApi, clientsApi, supplementsApi, pointsDeVenteApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { printReceipt } from '@/utils/print'

interface Product {
  id: number; nom: string; reference: string
  quantite: number; prix_vente_ttc: number; type?: string
}
interface Supplement {
  id: number; nom: string; prix_vente: number; active: boolean
  ingredient_id: number; quantite: number; unite: string | null
}
interface CartLine {
  id: number; nom: string; prix_vente_ttc: number
  qty: number; stock: number
  is_supplement?: true; supplement_id?: number
  // kept for HT calculation
  prix_vente_ht?: number
}
interface Client { id: number; nom: string; telephone?: string; solde?: number }

const auth = useAuthStore()
const orgName = computed(() => auth.user?.organisation?.nom ?? 'StockPilot')

// PDV de vente — résolution pour admin sans PDV assigné
interface Pdv { id: number; nom: string; type: string }
const pointsVente   = ref<Pdv[]>([])
const caissePointId = ref<number | null>(null)

// Sélecteur visible uniquement si : admin sans PDV assigné ET plusieurs PDVs disponibles
const needsPdvSelector = computed(() =>
  auth.isAdmin && !auth.pointDeVenteId && pointsVente.value.length > 1
)

// PDV effectif : priorité au PDV assigné, sinon au choix du sélecteur (ou auto-sélection mono-PDV)
const effectivePdvId = computed<number | null>(() =>
  auth.pointDeVenteId ?? caissePointId.value ?? null
)

// Nom du PDV effectif pour l'info-label
const effectivePdvNom = computed<string | null>(() => {
  if (auth.pointDeVente) return auth.pointDeVente.nom
  if (caissePointId.value)
    return pointsVente.value.find(p => p.id === caissePointId.value)?.nom ?? null
  return null
})

// Stock-warning modal state (restauration only)
const showStockWarning = ref(false)
const stockWarnings    = ref<any[]>([])
const pendingPayload   = ref<any>(null)

const products    = ref<Product[]>([])
const supplements = ref<Supplement[]>([])
const loading = ref(false)
const search = ref('')
const barcode = ref('')
const scanMsg = ref('')
const scanError = ref(false)
const cart = ref<CartLine[]>([])
const mode = ref<'especes' | 'carte' | 'credit'>('especes')
const montantPaye = ref<number | null>(null)
const referenceCarte = ref('')
const remiseType = ref<'pourcentage' | 'montant' | null>(null)
const remiseValeur = ref<number | null>(null)
const submitting = ref(false)
const error = ref('')
const receipt = ref<any>(null)

// Crédit / client
const clientSearch = ref('')
const clientTelephone = ref('')
const clientResults = ref<Client[]>([])
const selectedClient = ref<Client | null>(null)

let clientTimer: ReturnType<typeof setTimeout>
function searchClients() {
  if (selectedClient.value) return
  clearTimeout(clientTimer)
  clientTimer = setTimeout(async () => {
    const q = clientSearch.value.trim()
    if (!q) { clientResults.value = []; return }
    const { data } = await clientsApi.list({ search: q })
    clientResults.value = data
  }, 250)
}
function pickClient(c: Client) {
  selectedClient.value = c
  clientSearch.value = c.nom
  clientResults.value = []
}
function clearClient() {
  selectedClient.value = null
  clientResults.value = []
}

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchProducts, 300)
}

async function fetchProducts() {
  loading.value = true
  try {
    const [prodRes, suppRes] = await Promise.all([
      productsApi.list({
        search: search.value,
        per_page: 100,
        actif: 1,
        ...(auth.isRestauration ? { type: 'compose' } : {}),
      }),
      auth.isRestauration ? supplementsApi.list() : Promise.resolve({ data: [] }),
    ])
    products.value = prodRes.data.data
    supplements.value = (suppRes.data as Supplement[]).filter((s: Supplement) => s.active)
  } finally {
    loading.value = false
  }
}

// Produits composés (plats/recettes) : stock sans signification, toujours dispo
function isCompose(p: Product | CartLine): boolean {
  return (p as any).type === 'compose'
}

// Stock restant en tenant compte de ce qui est déjà dans le panier
function availableStock(p: Product): number {
  if (isCompose(p)) return Infinity
  const inCart = cart.value.find((l) => l.id === p.id)?.qty ?? 0
  return p.quantite - inCart
}

function addToCart(p: Product) {
  const line = cart.value.find((l) => l.id === p.id)
  if (line) {
    if (line.qty < line.stock) line.qty++
  } else {
    // Pour les composés, stock=Infinity — pas de plafond de quantité au panier
    cart.value.push({ ...p, qty: 1, stock: isCompose(p) ? Infinity : p.quantite })
  }
}
function addSupplementToCart(s: Supplement) {
  const cartKey = `supp-${s.id}`
  const line = cart.value.find((l) => l.is_supplement && l.supplement_id === s.id)
  if (line) {
    line.qty++
  } else {
    cart.value.push({
      id:             s.id,        // used as display key only
      nom:            s.nom,
      prix_vente_ttc: s.prix_vente,
      prix_vente_ht:  s.prix_vente, // restauration: TTC = HT
      qty:            1,
      stock:          Infinity,
      is_supplement:  true,
      supplement_id:  s.id,
    } as any)
  }
}
function inc(line: CartLine) { if (line.qty < line.stock) line.qty++ }
function dec(line: CartLine) { line.qty--; if (line.qty <= 0) removeLine(line) }
function removeLine(line: CartLine) {
  cart.value = cart.value.filter((l) =>
    l.is_supplement
      ? !(l.is_supplement && l.supplement_id === line.supplement_id)
      : l.id !== line.id
  )
}

const r3 = (n: number) => Math.round(n * 1000) / 1000

const grossTtc = computed(() =>
  r3(cart.value.reduce((s, l) => s + l.prix_vente_ttc * l.qty, 0)))
const totalHt = computed(() =>
  r3(cart.value.reduce((s, l) => {
    if (l.is_supplement) return s + (l.prix_vente_ht ?? l.prix_vente_ttc) * l.qty
    const p = products.value.find((x) => x.id === l.id)
    return s + (p ? (p as any).prix_vente_ht * l.qty : 0)
  }, 0)))
const totalTva = computed(() => r3(grossTtc.value - totalHt.value))

const remiseMontant = computed(() => {
  if (!remiseType.value || !remiseValeur.value || remiseValeur.value <= 0) return 0
  const m = remiseType.value === 'pourcentage'
    ? grossTtc.value * Math.min(remiseValeur.value, 100) / 100
    : remiseValeur.value
  return r3(Math.min(m, grossTtc.value))
})
const totalTtc = computed(() => r3(grossTtc.value - remiseMontant.value))
const rendu = computed(() => r3((montantPaye.value ?? 0) - totalTtc.value))

const canValidate = computed(() => {
  if (cart.value.length === 0) return false
  if (needsPdvSelector.value && !caissePointId.value) return false
  if (mode.value === 'especes' && montantPaye.value !== null && montantPaye.value < totalTtc.value) return false
  // Crédit : un client (existant ou nom saisi) est obligatoire
  if (mode.value === 'credit' && !selectedClient.value && !clientSearch.value.trim()) return false
  return true
})

async function scanBarcode() {
  const code = barcode.value.trim()
  if (!code) return
  scanMsg.value = ''
  scanError.value = false
  try {
    const { data } = await productsApi.list({
      search: code,
      per_page: 5,
      actif: 1,
      ...(auth.isRestauration ? { type: 'compose' } : {}),
    })
    const list: Product[] = data.data
    const match = list.find((p) => p.reference?.toLowerCase() === code.toLowerCase()) ?? list[0]
    if (!match) {
      scanError.value = true
      scanMsg.value = `Aucun produit pour « ${code} ».`
    } else if (!isCompose(match) && match.quantite <= 0) {
      scanError.value = true
      scanMsg.value = `« ${match.nom} » est en rupture.`
    } else {
      // S'assure que le produit est connu pour le calcul du HT
      if (!products.value.find((p) => p.id === match.id)) products.value.unshift(match)
      addToCart(match)
      scanMsg.value = `« ${match.nom} » ajouté.`
    }
  } catch {
    scanError.value = true
    scanMsg.value = 'Erreur de recherche.'
  } finally {
    barcode.value = ''
  }
}

async function validate() {
  error.value = ''
  submitting.value = true

  const items = cart.value.map((l) =>
    l.is_supplement
      ? { supplement_id: l.supplement_id, quantite: l.qty }
      : { product_id: l.id, quantite: l.qty }
  )

  // Vérification stock ingrédients (restauration uniquement, sans effet de bord)
  if (auth.isRestauration) {
    try {
      const { data: check } = await salesApi.checkIngredients({ items })
      if (check.warnings?.length > 0) {
        const payload: any = {
          items,
          mode_paiement:   mode.value,
          montant_paye:    mode.value === 'carte' ? null : montantPaye.value,
          reference_carte: mode.value === 'carte' ? (referenceCarte.value.trim() || null) : null,
          remise_type:     remiseType.value,
          remise_valeur:   remiseType.value ? remiseValeur.value : null,
        }
        if (mode.value === 'credit') {
          if (selectedClient.value) payload.client_id = selectedClient.value.id
          else {
            payload.client_nom       = clientSearch.value.trim()
            payload.client_telephone = clientTelephone.value.trim() || null
          }
        }
        stockWarnings.value    = check.warnings
        pendingPayload.value   = payload
        showStockWarning.value = true
        submitting.value       = false
        return // attendre confirmation
      }
    } catch {
      // Si le check échoue (réseau, etc.), on laisse passer et on continue
    }
  }

  await _doCreateSale({
    items,
    mode_paiement:   mode.value,
    montant_paye:    mode.value === 'carte' ? null : montantPaye.value,
    reference_carte: mode.value === 'carte' ? (referenceCarte.value.trim() || null) : null,
    remise_type:     remiseType.value,
    remise_valeur:   remiseType.value ? remiseValeur.value : null,
    ...(mode.value === 'credit' ? (selectedClient.value
      ? { client_id: selectedClient.value.id }
      : { client_nom: clientSearch.value.trim(), client_telephone: clientTelephone.value.trim() || null }
    ) : {}),
  })
}

async function confirmSaleAnyway() {
  showStockWarning.value = false
  submitting.value = true
  await _doCreateSale(pendingPayload.value)
  pendingPayload.value = null
}

function cancelStockWarning() {
  showStockWarning.value = false
  stockWarnings.value    = []
  pendingPayload.value   = null
}

async function _doCreateSale(payload: any) {
  // Injecter le PDV de vente si admin sans PDV assigné
  if (effectivePdvId.value) {
    payload.point_de_vente_id = effectivePdvId.value
  }
  try {
    const { data } = await salesApi.create(payload)
    receipt.value = data
    cart.value = []
    montantPaye.value = null
    referenceCarte.value = ''
    remiseType.value = null
    remiseValeur.value = null
    mode.value = 'especes'
    clearClient()
    clientSearch.value = ''
    clientTelephone.value = ''
    await fetchProducts()
  } catch (e: any) {
    const firstError = (Object.values(e.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
    error.value = e.response?.data?.message || firstError || 'Échec de l\'encaissement.'
  } finally {
    submitting.value = false
  }
}

function money(v: number | string | null | undefined): string {
  return Number(v ?? 0).toFixed(3) + ' TND'
}
function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR')
}
function doPrint() {
  if (!receipt.value) return
  printReceipt(receipt.value, {
    orgNom:       auth.user?.organisation?.nom,
    orgAdresse:   auth.user?.organisation?.adresse,
    orgTelephone: auth.user?.organisation?.telephone,
    cashierName:  [auth.user?.prenom, auth.user?.nom].filter(Boolean).join(' '),
  })
}

onMounted(async () => {
  // Charger PDVs en parallèle des produits pour tout admin sans PDV assigné
  const tasks: Promise<any>[] = [fetchProducts()]

  if (auth.isAdmin && !auth.pointDeVenteId) {
    tasks.push(
      pointsDeVenteApi.list({ type: 'point_vente' }).then(({ data }) => {
        pointsVente.value = data
        // Mono-PDV : auto-sélection silencieuse, pas de sélecteur affiché
        if (data.length === 1) caissePointId.value = data[0].id
      }).catch(() => {})
    )
  }

  await Promise.all(tasks)
})
</script>
