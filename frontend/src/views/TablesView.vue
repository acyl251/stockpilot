<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-navy">Tables</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gestion des tables et prises de commande</p>
      </div>
      <div class="flex gap-2">
        <button @click="openEmporter"
          class="px-4 py-2 text-sm font-medium border border-navy text-navy rounded-lg hover:bg-navy hover:text-white transition-colors">
          🥡 À emporter
        </button>
        <button @click="openTableModal" class="btn-primary text-sm">+ Nouvelle table</button>
      </div>
    </div>

    <div v-if="loadingTables" class="text-center py-16 text-slate-400">Chargement des tables…</div>

    <div v-else-if="tables.length === 0" class="card text-center py-16">
      <p class="text-slate-400 mb-3">Aucune table configurée.</p>
      <button @click="openTableModal" class="btn-primary text-sm">+ Ajouter une table</button>
    </div>

    <!-- Tables grid -->
    <div v-else class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
      <div v-for="table in tables" :key="table.id"
        :class="['card cursor-pointer border-2 transition-all hover:shadow-md',
          table.statut === 'occupee'
            ? 'border-orange-400 hover:border-orange-500'
            : 'border-emerald-400 hover:border-emerald-500']"
        @click="openOrder(table)">
        <div class="flex items-start justify-between mb-2">
          <div>
            <p class="font-bold text-navy text-2xl">{{ table.numero }}</p>
            <p v-if="table.capacite" class="text-xs text-slate-400">{{ table.capacite }} places</p>
          </div>
          <span :class="['px-2 py-1 rounded-full text-xs font-semibold',
            table.statut === 'occupee' ? 'bg-orange-100 text-orange-700' : 'bg-emerald-100 text-emerald-700']">
            {{ table.statut === 'occupee' ? 'Occupée' : 'Libre' }}
          </span>
        </div>
        <template v-if="table.statut === 'occupee' && table.current_order">
          <div class="border-t border-slate-100 pt-2 mt-2">
            <p class="text-xs text-slate-500">{{ formatTime(table.current_order.created_at) }}</p>
            <p class="font-semibold text-navy mt-0.5">{{ money(table.current_order.total) }}</p>
            <p class="text-xs text-slate-400">{{ table.current_order.item_count }} article(s)</p>
          </div>
        </template>
      </div>
    </div>

    <!-- ─── Table creation modal ─────────────────────────────────── -->
    <div v-if="showTableModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-40 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm" @click.stop>
        <div class="flex items-center justify-between p-5 border-b">
          <h2 class="font-bold text-navy">Nouvelle table</h2>
          <button @click="showTableModal = false" class="text-slate-400 hover:text-slate-600 text-xl">×</button>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Numéro / Nom *</label>
            <input v-model="tableForm.numero" type="text" placeholder="ex: 1, Terrasse A…"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Capacité (places)</label>
            <input v-model.number="tableForm.capacite" type="number" min="1" max="99" placeholder="ex: 4"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
          </div>
        </div>
        <p v-if="tableModalError" class="px-5 pb-2 text-sm text-red-600">{{ tableModalError }}</p>
        <div class="flex gap-3 p-5 border-t">
          <button @click="showTableModal = false" class="flex-1 btn-secondary text-sm">Annuler</button>
          <button @click="saveTable" :disabled="!tableForm.numero || savingTable"
            class="flex-1 btn-primary text-sm disabled:opacity-50">
            {{ savingTable ? 'Enregistrement…' : 'Créer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ─── Warning stock modal ─────────────────────────────────── -->
    <div v-if="showWarningModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
        <div class="p-5 border-b flex items-center gap-3">
          <span class="text-2xl">⚠️</span>
          <h2 class="font-bold text-navy">Stock insuffisant</h2>
        </div>
        <div class="p-5 space-y-2">
          <p class="text-sm text-slate-600 mb-3">Les ingrédients suivants manquent pour cette commande :</p>
          <div v-for="w in kitchenWarnings" :key="w.ingredient"
            class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2.5 text-sm">
            <span class="text-amber-500 flex-shrink-0 mt-0.5">●</span>
            <div>
              <p class="font-semibold text-amber-800">{{ w.ingredient }}</p>
              <p class="text-xs text-amber-600">
                Nécessaire : {{ w.quantite_necessaire }} — Stock : {{ w.stock_actuel }}
                <span class="font-semibold"> (manque {{ w.manque }})</span>
              </p>
            </div>
          </div>
          <p class="text-sm text-slate-500 pt-2">Voulez-vous quand même envoyer en cuisine ?</p>
        </div>
        <div class="flex gap-3 p-5 border-t">
          <button @click="cancelKitchenSend"
            class="flex-1 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium hover:bg-slate-50">
            Annuler
          </button>
          <button @click="confirmKitchenSend" :disabled="sendingKitchen"
            class="flex-1 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold disabled:opacity-50">
            {{ sendingKitchen ? 'Envoi…' : 'Envoyer quand même' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ─── Order + payment modal ────────────────────────────────── -->
    <div v-if="showOrderModal" class="fixed inset-0 bg-black/50 flex items-stretch justify-end z-50">
      <div class="bg-white w-full max-w-4xl flex flex-col shadow-2xl" @click.stop>

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b bg-slate-50">
          <div>
            <h2 class="font-bold text-navy text-lg">
              {{ orderTable ? `Table ${orderTable.numero}` : 'À emporter' }}
            </h2>
            <p class="text-xs text-slate-500">
              <span v-if="showPayPanel" class="text-emerald-600">Encaissement</span>
              <span v-else-if="existingOrders.length > 0" class="text-amber-600">
                {{ existingOrders.length }} envoi(s) · Ajouter des articles ou encaisser
              </span>
              <span v-else>Nouvelle commande</span>
            </p>
          </div>
          <button @click="closeOrderModal" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="flex flex-1 overflow-hidden">

          <!-- Left: products + supplements (hidden in pay panel) -->
          <div v-show="!showPayPanel" class="flex-1 overflow-y-auto p-4 space-y-4 border-r">

            <!-- Previous orders (read only) -->
            <template v-if="existingOrders.length > 0">
              <div v-for="ord in existingOrders" :key="ord.id"
                class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <div class="flex items-center justify-between mb-2">
                  <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">
                    Envoi #{{ ord.id }} — {{ formatTime(ord.created_at) }}
                  </p>
                  <span class="text-xs font-semibold text-amber-700">{{ money(ord.total) }}</span>
                </div>
                <div v-for="item in ord.items" :key="item.id"
                  class="flex items-start justify-between text-sm text-amber-800 py-0.5 gap-2">
                  <span>{{ item.quantite }}× {{ item.designation }}
                    <span v-if="item.note_ligne" class="text-xs text-amber-600 ml-1">→ {{ item.note_ligne }}</span>
                  </span>
                  <span class="font-mono text-xs whitespace-nowrap">{{ money(item.prix_unitaire * item.quantite) }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between text-sm font-bold text-amber-800 border-t border-amber-200 pt-2">
                <span>Déjà commandé</span>
                <span>{{ money(existingTotal) }}</span>
              </div>
            </template>

            <!-- Products -->
            <div>
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                {{ existingOrders.length > 0 ? 'Ajouter au menu' : 'Menu' }}
              </p>
              <div v-if="loadingProducts" class="text-slate-400 text-sm">Chargement…</div>
              <div v-else-if="products.length === 0" class="text-slate-400 text-sm">Aucun produit composé disponible.</div>
              <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <button v-for="p in products" :key="p.id" @click="addToCart(p)"
                  class="text-left border border-slate-200 rounded-lg p-3 hover:border-gold hover:bg-gold/5 transition-all">
                  <p class="font-medium text-navy text-sm leading-tight">{{ p.nom }}</p>
                  <p class="text-gold font-semibold text-sm mt-0.5">{{ money(p.prix_vente_ttc) }}</p>
                </button>
              </div>
            </div>

            <!-- Supplements -->
            <div v-if="supplements.length > 0">
              <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Suppléments</p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                <button v-for="s in supplements" :key="s.id" @click="addSupplementToCart(s)"
                  class="text-left border border-amber-200 rounded-lg p-3 hover:border-amber-400 hover:bg-amber-50 transition-all">
                  <p class="font-medium text-navy text-sm leading-tight">{{ s.nom }}</p>
                  <p class="text-amber-600 font-semibold text-sm mt-0.5">{{ money(s.prix_vente) }}</p>
                </button>
              </div>
            </div>
          </div>

          <!-- Left (pay panel): order recap -->
          <div v-if="showPayPanel" class="flex-1 overflow-y-auto p-5 border-r">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Récapitulatif de la commande</p>
            <div v-for="ord in existingOrders" :key="ord.id" class="mb-4">
              <p class="text-xs text-slate-400 mb-1">Envoi #{{ ord.id }} — {{ formatTime(ord.created_at) }}</p>
              <div v-for="item in ord.items" :key="item.id"
                class="flex justify-between text-sm text-slate-700 py-1 border-b border-slate-50">
                <span>{{ item.quantite }}× {{ item.designation }}</span>
                <span class="font-mono text-xs">{{ money(item.prix_unitaire * item.quantite) }}</span>
              </div>
            </div>
            <div class="flex justify-between font-bold text-navy text-base border-t-2 border-navy pt-3 mt-2">
              <span>Total à payer</span>
              <span>{{ money(existingTotal) }}</span>
            </div>
          </div>

          <!-- Right: cart or payment panel -->
          <div class="w-80 flex flex-col bg-slate-50">

            <!-- ─ Cart panel ─ -->
            <template v-if="!showPayPanel">
              <div class="flex-1 overflow-y-auto p-4 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                  {{ existingOrders.length > 0 ? 'Nouvel envoi' : 'Commande' }}
                </p>
                <div v-if="cart.length === 0" class="text-center py-8 text-slate-400 text-sm">
                  Cliquez sur un article pour l'ajouter
                </div>
                <div v-for="(line, i) in cart" :key="i"
                  class="bg-white rounded-lg p-3 border border-slate-100 space-y-1">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-navy flex-1 leading-tight">{{ line.designation }}</p>
                    <button @click="removeFromCart(i)"
                      class="text-slate-300 hover:text-red-500 text-lg leading-none flex-shrink-0">×</button>
                  </div>
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1">
                      <button @click="changeQty(i, -1)"
                        class="w-6 h-6 rounded border border-slate-200 hover:bg-slate-100 text-sm flex items-center justify-center">−</button>
                      <span class="w-6 text-center text-sm font-semibold">{{ line.quantite }}</span>
                      <button @click="changeQty(i, 1)"
                        class="w-6 h-6 rounded border border-slate-200 hover:bg-slate-100 text-sm flex items-center justify-center">+</button>
                    </div>
                    <span class="text-sm font-semibold text-navy">{{ money(line.prix_unitaire * line.quantite) }}</span>
                  </div>
                  <input v-model="line.note_ligne" type="text" placeholder="Note (cuisson, allergie…)"
                    class="w-full text-xs border border-slate-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-gold" />
                </div>
              </div>

              <div class="p-4 border-t bg-white space-y-3">
                <div>
                  <label class="text-xs font-medium text-slate-500 uppercase tracking-wide">Note globale</label>
                  <input v-model="orderNote" type="text" placeholder="Note pour la commande…"
                    class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gold" />
                </div>
                <div class="flex justify-between text-base font-bold text-navy border-t pt-3">
                  <span>{{ existingOrders.length > 0 ? 'Cet envoi' : 'Total' }}</span>
                  <span>{{ money(cartTotal) }}</span>
                </div>
                <div v-if="existingOrders.length > 0" class="flex justify-between text-xs text-slate-500 -mt-2">
                  <span>Total table</span>
                  <span>{{ money(existingTotal + cartTotal) }}</span>
                </div>

                <!-- Encaisser (only when there are sent orders) -->
                <button v-if="existingOrders.length > 0"
                  @click="openPayPanel"
                  class="w-full py-2.5 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors">
                  💳 Encaisser {{ money(existingTotal + cartTotal) }}
                </button>

                <button @click="sendToKitchen"
                  :disabled="cart.length === 0 || sendingKitchen"
                  class="w-full btn-primary py-3 text-sm font-semibold disabled:opacity-40">
                  {{ sendingKitchen ? 'Envoi…' : '🍳 Envoyer en cuisine' }}
                </button>
                <button @click="closeOrderModal" class="w-full text-sm text-slate-500 hover:text-slate-700 py-1">
                  Annuler
                </button>
              </div>
            </template>

            <!-- ─ Payment panel ─ -->
            <template v-else>
              <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Mode de paiement</p>

                <!-- Mode buttons -->
                <div class="grid grid-cols-3 gap-2">
                  <button v-for="m in payModes" :key="m.value" @click="payMode = m.value as any"
                    :class="['py-2 rounded-lg text-xs font-semibold border transition-colors',
                      payMode === m.value
                        ? (m.value === 'credit' ? 'bg-amber-500 text-white border-amber-500' : 'bg-navy text-white border-navy')
                        : 'border-slate-300 text-slate-600 hover:bg-slate-50']">
                    {{ m.label }}
                  </button>
                </div>

                <!-- Espèces -->
                <div v-if="payMode === 'especes'" class="space-y-2">
                  <label class="text-xs font-medium text-slate-600">Montant reçu</label>
                  <input v-model.number="payMontantRecu" type="number" step="0.001" min="0"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
                  <div v-if="payMontantRecu > 0"
                    :class="['flex justify-between text-sm font-semibold rounded-lg px-3 py-2',
                      payRendu >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                    <span>{{ payRendu >= 0 ? 'Monnaie à rendre' : 'Manque' }}</span>
                    <span>{{ money(Math.abs(payRendu)) }}</span>
                  </div>
                </div>

                <!-- Carte bancaire -->
                <div v-if="payMode === 'carte'" class="space-y-3 bg-indigo-50 rounded-lg p-3">
                  <div class="text-center">
                    <p class="text-xs text-indigo-500 font-medium uppercase tracking-wide mb-1">Montant à encaisser sur le TPE</p>
                    <p class="text-3xl font-bold text-indigo-700">{{ money(existingTotal) }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">N° transaction TPE <span class="text-slate-400">(optionnel)</span></label>
                    <input v-model="payReferenceCarte" type="text" placeholder="ex: 000123456789"
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
                  </div>
                </div>

                <!-- Crédit -->
                <div v-if="payMode === 'credit'" class="space-y-3 bg-amber-50 rounded-lg p-3">
                  <p class="text-xs text-amber-700 font-medium">Vente à crédit — rattachée à un client</p>
                  <div v-if="loadingClients" class="text-xs text-slate-400">Chargement clients…</div>
                  <select v-else v-model="payClientId"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
                    <option :value="null">— Sélectionner un client —</option>
                    <option v-for="c in clients" :key="c.id" :value="c.id">
                      {{ c.nom }} {{ c.telephone ? `· ${c.telephone}` : '' }}
                    </option>
                  </select>
                  <label class="text-xs font-medium text-slate-600">Acompte versé (optionnel)</label>
                  <input v-model.number="payMontantRecu" type="number" step="0.001" min="0"
                    placeholder="0.000"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
                  <div class="flex justify-between text-sm font-semibold text-amber-700">
                    <span>Reste à devoir</span>
                    <span>{{ money(Math.max(0, existingTotal - (payMontantRecu || 0))) }}</span>
                  </div>
                </div>

                <p v-if="payError" class="text-red-600 text-xs font-medium">{{ payError }}</p>
              </div>

              <div class="p-4 border-t bg-white space-y-3">
                <div class="flex justify-between text-base font-bold text-navy">
                  <span>Total</span>
                  <span>{{ money(existingTotal) }}</span>
                </div>
                <button @click="confirmPayment"
                  :disabled="paying || (payMode === 'credit' && !payClientId)"
                  class="w-full py-3 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg disabled:opacity-40 transition-colors">
                  {{ paying ? 'Traitement…' : payMode === 'carte' ? '💳 Confirmer le paiement carte' : '✅ Valider le paiement' }}
                </button>
                <button @click="showPayPanel = false" class="w-full text-sm text-slate-500 hover:text-slate-700 py-1">
                  ← Retour
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { tablesApi, ordersApi, productsApi, supplementsApi, clientsApi, salesApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { printReceipt, printKitchenTicket } from '@/utils/print'

const auth = useAuthStore()

interface RestaurantTable {
  id: number; numero: string; capacite: number | null
  statut: 'libre' | 'occupee'; active: boolean
  current_order?: { id: number; created_at: string; total: number; item_count: number; statut: string } | null
}
interface OrderItem {
  id: number; product_id: number | null; supplement_id: number | null
  designation: string; quantite: number; prix_unitaire: number; note_ligne: string | null
}
interface Order {
  id: number; type: string; statut: string; note: string | null
  created_at: string; total: number
  table: { id: number; numero: string } | null; items: OrderItem[]
}
interface CartLine {
  product_id?: number; supplement_id?: number
  designation: string; quantite: number; prix_unitaire: number; note_ligne: string
}
interface Product { id: number; nom: string; prix_vente_ht: number; prix_vente_ttc: number; type: string }
interface Supplement { id: number; nom: string; prix_vente: number; active: boolean }
interface Client { id: number; nom: string; telephone?: string }

// ─── State ────────────────────────────────────────────────────────────────────
const tables          = ref<RestaurantTable[]>([])
const loadingTables   = ref(false)
const products        = ref<Product[]>([])
const supplements     = ref<Supplement[]>([])
const loadingProducts = ref(false)

const showTableModal  = ref(false)
const tableForm       = ref({ numero: '', capacite: null as number | null })
const savingTable     = ref(false)
const tableModalError = ref('')

const showOrderModal   = ref(false)
const orderTable       = ref<RestaurantTable | null>(null)
const cart             = ref<CartLine[]>([])
const orderNote        = ref('')
const existingOrders   = ref<Order[]>([])
const sendingKitchen   = ref(false)

// Stock warning
const showWarningModal = ref(false)
const kitchenWarnings  = ref<any[]>([])
const pendingOrderId   = ref<number | null>(null)

// Payment
const showPayPanel      = ref(false)
const payMode           = ref<'especes' | 'carte' | 'credit'>('especes')
const payMontantRecu    = ref(0)
const payReferenceCarte = ref('')
const payClientId       = ref<number | null>(null)
const clients           = ref<Client[]>([])
const loadingClients    = ref(false)
const paying            = ref(false)
const payError          = ref('')

const payModes = [
  { value: 'especes', label: 'Espèces' },
  { value: 'carte',   label: 'Carte' },
  { value: 'credit',  label: 'Crédit' },
]

// ─── Computed ─────────────────────────────────────────────────────────────────
const cartTotal = computed(() => cart.value.reduce((s, l) => s + l.prix_unitaire * l.quantite, 0))
const existingTotal = computed(() => existingOrders.value.reduce((s, o) => s + o.total, 0))
const payRendu = computed(() => (payMontantRecu.value || 0) - existingTotal.value)

// ─── Helpers ──────────────────────────────────────────────────────────────────
function money(v: number) { return (v ?? 0).toFixed(3) + ' DT' }
function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString('fr-TN', { hour: '2-digit', minute: '2-digit' })
}

// ─── Tables ───────────────────────────────────────────────────────────────────
async function fetchTables() {
  loadingTables.value = true
  try { tables.value = (await tablesApi.list()).data }
  finally { loadingTables.value = false }
}

async function fetchMenuItems() {
  loadingProducts.value = true
  try {
    const [prods, supps] = await Promise.all([
      productsApi.list({ type: 'compose', per_page: 100, actif: 1 }),
      supplementsApi.list(),
    ])
    products.value    = prods.data.data ?? prods.data
    supplements.value = (supps.data as Supplement[]).filter(s => s.active)
  } finally { loadingProducts.value = false }
}

// ─── Table creation ───────────────────────────────────────────────────────────
function openTableModal() {
  tableForm.value    = { numero: '', capacite: null }
  tableModalError.value = ''
  showTableModal.value  = true
}

async function saveTable() {
  if (!tableForm.value.numero) return
  savingTable.value     = true
  tableModalError.value = ''
  try {
    await tablesApi.create(tableForm.value)
    showTableModal.value = false
    await fetchTables()
  } catch (e: any) {
    const data = e.response?.data
    tableModalError.value =
      data?.errors?.numero?.[0] ??
      data?.message ??
      'Erreur lors de la création de la table.'
    console.error('[saveTable]', data ?? e)
  } finally {
    savingTable.value = false
  }
}

// ─── Order modal ──────────────────────────────────────────────────────────────
async function openOrder(table: RestaurantTable) {
  orderTable.value     = table
  cart.value           = []
  orderNote.value      = ''
  existingOrders.value = []
  showPayPanel.value   = false

  if (table.statut === 'occupee') {
    try {
      const { data } = await ordersApi.list({ table_id: table.id })
      existingOrders.value = Array.isArray(data) ? data : []
    } catch {}
  }
  showOrderModal.value = true
}

function openEmporter() {
  orderTable.value     = null
  cart.value           = []
  orderNote.value      = ''
  existingOrders.value = []
  showPayPanel.value   = false
  showOrderModal.value = true
}

function closeOrderModal() {
  showOrderModal.value = false
  showPayPanel.value   = false
  orderTable.value     = null
  existingOrders.value = []
  cart.value           = []
  payError.value       = ''
}

// ─── Cart ─────────────────────────────────────────────────────────────────────
function addToCart(p: Product) {
  const ex = cart.value.find(l => l.product_id === p.id && !l.supplement_id)
  if (ex) { ex.quantite++; return }
  cart.value.push({ product_id: p.id, designation: p.nom, quantite: 1, prix_unitaire: p.prix_vente_ttc, note_ligne: '' })
}

function addSupplementToCart(s: Supplement) {
  const ex = cart.value.find(l => l.supplement_id === s.id)
  if (ex) { ex.quantite++; return }
  cart.value.push({ supplement_id: s.id, designation: s.nom, quantite: 1, prix_unitaire: s.prix_vente, note_ligne: '' })
}

function removeFromCart(i: number) { cart.value.splice(i, 1) }
function changeQty(i: number, d: number) { cart.value[i].quantite = Math.max(1, cart.value[i].quantite + d) }

// ─── Send to kitchen ──────────────────────────────────────────────────────────
async function sendToKitchen() {
  if (cart.value.length === 0) return
  sendingKitchen.value = true
  try {
    const payload = {
      table_id: orderTable.value?.id ?? null,
      type:     orderTable.value ? 'sur_place' : 'emporter',
      note:     orderNote.value || null,
      items:    cart.value.map(l => ({
        product_id:    l.product_id    ?? null,
        supplement_id: l.supplement_id ?? null,
        designation:   l.designation,
        quantite:      l.quantite,
        prix_unitaire: l.prix_unitaire,
        note_ligne:    l.note_ligne || null,
      })),
    }
    const { data: order } = await ordersApi.create(payload)

    // Check ingredient stock before actually sending to kitchen
    const { data: check } = await ordersApi.checkIngredients(order.id)
    if (check.warnings?.length > 0) {
      kitchenWarnings.value = check.warnings
      pendingOrderId.value  = order.id
      showWarningModal.value = true
      sendingKitchen.value  = false
      return // wait for user confirmation
    }

    await _doSendKitchen(order.id)
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'Une erreur est survenue.')
  } finally {
    sendingKitchen.value = false
  }
}

async function _doSendKitchen(orderId: number) {
  const { data: sentOrder } = await ordersApi.sendKitchen(orderId)
  printKitchenTicket(sentOrder)
  if (!orderTable.value) {
    existingOrders.value.push(sentOrder)
    cart.value      = []
    orderNote.value = ''
  } else {
    closeOrderModal()
    await fetchTables()
  }
}

async function confirmKitchenSend() {
  if (!pendingOrderId.value) return
  sendingKitchen.value   = true
  showWarningModal.value = false
  try {
    await _doSendKitchen(pendingOrderId.value)
  } catch (e: any) {
    alert(e?.response?.data?.message ?? 'Erreur lors de l\'envoi en cuisine.')
  } finally {
    sendingKitchen.value  = false
    pendingOrderId.value  = null
    kitchenWarnings.value = []
  }
}

function cancelKitchenSend() {
  showWarningModal.value = false
  kitchenWarnings.value  = []
  pendingOrderId.value   = null
  // Order stays in en_cours — user can fix stock and retry
}

// ─── Payment panel ────────────────────────────────────────────────────────────
async function openPayPanel() {
  // If there are unsent cart items, send them to kitchen first
  if (cart.value.length > 0) {
    await sendToKitchen()
    if (existingOrders.value.length === 0) return // send failed
  }
  payMode.value           = 'especes'
  payMontantRecu.value    = parseFloat(existingTotal.value.toFixed(3))
  payReferenceCarte.value = ''
  payClientId.value       = null
  payError.value          = ''
  showPayPanel.value   = true
  fetchClientsList()
}

async function fetchClientsList() {
  if (clients.value.length > 0) return
  loadingClients.value = true
  try { clients.value = (await clientsApi.list({ per_page: 200 })).data.data ?? [] }
  finally { loadingClients.value = false }
}

async function confirmPayment() {
  if (existingOrders.value.length === 0) return
  payError.value = ''
  paying.value   = true

  try {
    const payload: Record<string, unknown> = { mode_paiement: payMode.value }
    if (payMode.value === 'especes') payload.montant_paye = payMontantRecu.value
    if (payMode.value === 'carte' && payReferenceCarte.value.trim())
      payload.reference_carte = payReferenceCarte.value.trim()
    if (payMode.value === 'credit') {
      payload.client_id    = payClientId.value
      payload.montant_paye = payMontantRecu.value || 0
    }

    const orderId = existingOrders.value[0].id
    const { data } = await ordersApi.pay(orderId, payload)

    const tLabel = orderTable.value ? `Table ${orderTable.value.numero}` : undefined
    await printReceiptFromSaleId(data.sale_id, tLabel)
    closeOrderModal()
    await fetchTables()
  } catch (e: any) {
    payError.value = e?.response?.data?.message ?? 'Erreur lors du paiement.'
  } finally {
    paying.value = false
  }
}

// ─── Print helpers ────────────────────────────────────────────────────────────
// printKitchenTicket and printReceipt are imported from @/utils/print

async function printReceiptFromSaleId(saleId: number, tableLabel?: string) {
  try {
    const { data: sale } = await salesApi.get(saleId)
    printReceipt(sale, {
      orgNom:       auth.user?.organisation?.nom,
      orgAdresse:   auth.user?.organisation?.adresse,
      orgTelephone: auth.user?.organisation?.telephone,
      cashierName:  [auth.user?.prenom, auth.user?.nom].filter(Boolean).join(' '),
      tableLabel,
    })
  } catch { /* non-fatal */ }
}

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(() => {
  fetchTables().catch(() => {})
  fetchMenuItems().catch(() => {})
})
</script>
