<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold text-navy">Commandes clients</h1>
      <button @click="openNewCommande" class="btn-primary text-sm">+ Nouvelle commande</button>
    </div>

    <!-- Filtres -->
    <div class="flex flex-wrap gap-3">
      <select v-model="filtreStatut" @change="loadCommandes"
        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
        <option value="">Tous les statuts</option>
        <option value="en_preparation">En préparation</option>
        <option value="prete">Prête</option>
        <option value="livree">Livrée</option>
        <option value="payee">Payée</option>
        <option value="annulee">Annulée</option>
      </select>
      <input v-model="filtreDateFrom" @change="loadCommandes" type="date"
        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
      <input v-model="filtreDateTo" @change="loadCommandes" type="date"
        class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
    </div>

    <!-- Liste -->
    <div v-if="loading" class="text-center py-10 text-slate-400">Chargement…</div>
    <div v-else-if="commandes.length === 0" class="text-center py-16 text-slate-400">
      <p class="text-4xl mb-3">📋</p>
      <p>Aucune commande pour ces critères.</p>
    </div>
    <div v-else class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 font-semibold text-slate-600">N° bon</th>
            <th class="text-left px-4 py-3 font-semibold text-slate-600">Client</th>
            <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden sm:table-cell">Date</th>
            <th class="text-right px-4 py-3 font-semibold text-slate-600">Total</th>
            <th class="text-center px-4 py-3 font-semibold text-slate-600">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in commandes" :key="c.id"
            class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ c.numero_bon }}</td>
            <td class="px-4 py-3 font-medium text-navy">{{ c.client?.nom ?? c.nom_client }}</td>
            <td class="px-4 py-3 text-slate-500 hidden sm:table-cell">{{ fmtDate(c.created_at) }}</td>
            <td class="px-4 py-3 text-right font-semibold text-navy">{{ formatPrice(c.total_ttc) }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statutClass(c.statut)]">
                {{ statutIcon(c.statut) }} {{ statutLabel(c.statut) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2 justify-end flex-wrap">
                <button v-if="c.statut === 'en_preparation'" @click="changerStatut(c, 'prete')"
                  class="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">Marquer prête</button>
                <button v-if="c.statut === 'prete'" @click="changerStatut(c, 'livree')"
                  class="text-xs text-emerald-600 hover:text-emerald-800 font-medium whitespace-nowrap">Marquer livrée</button>
                <button v-if="c.statut === 'livree'" @click="openTransformer(c)"
                  class="text-xs text-gold hover:text-yellow-600 font-medium whitespace-nowrap">Transformer en vente</button>
                <button @click="telechargerBon(c)"
                  class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">Bon PDF</button>
                <button v-if="!['payee','annulee'].includes(c.statut)" @click="changerStatut(c, 'annulee')"
                  class="text-xs text-red-500 hover:text-red-700 font-medium whitespace-nowrap">Annuler</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       MODAL NOUVELLE COMMANDE
  ═════════════════════════════════════════════════ -->
  <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
      <div class="p-5 border-b border-slate-200 flex-shrink-0">
        <h2 class="font-bold text-navy">Nouvelle commande client</h2>
      </div>
      <div class="p-5 space-y-4 overflow-y-auto flex-1">
        <!-- Client -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Client existant</label>
            <select v-model="form.client_id" class="input-field w-full">
              <option value="">— Client de passage —</option>
              <option v-for="cl in clients" :key="cl.id" :value="cl.id">{{ cl.nom }}</option>
            </select>
          </div>
          <template v-if="!form.client_id">
            <div>
              <label class="block text-xs text-slate-500 mb-1">Nom client *</label>
              <input v-model="form.nom_client" class="input-field w-full" placeholder="Nom du client" />
            </div>
            <div>
              <label class="block text-xs text-slate-500 mb-1">Téléphone</label>
              <input v-model="form.telephone_client" class="input-field w-full" placeholder="Ex: 27 650 255" />
            </div>
          </template>
          <div class="col-span-2">
            <label class="block text-xs text-slate-500 mb-1">Adresse de livraison</label>
            <input v-model="form.adresse_livraison" class="input-field w-full" />
          </div>
        </div>

        <!-- Produits -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-slate-700">Produits</h3>
            <button @click="addItem" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Ajouter un produit</button>
          </div>
          <div v-for="(item, idx) in form.items" :key="idx"
            class="border border-slate-200 rounded-xl p-3 mb-2 space-y-2">
            <div class="grid grid-cols-12 gap-2 items-center">
              <div class="col-span-6">
                <select v-model="item.product_id" @change="onProductChange(item)" class="input-field w-full text-xs">
                  <option value="">— Produit —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.nom }}</option>
                </select>
              </div>
              <div class="col-span-3">
                <input v-model.number="item.quantite" type="number" min="0.001" step="0.001"
                  placeholder="Qté" class="input-field w-full text-xs" />
              </div>
              <div class="col-span-2 text-xs text-slate-500 text-right">
                {{ item.stock !== null ? `Stock: ${item.stock}` : '' }}
              </div>
              <div class="col-span-1 flex justify-center">
                <button @click="form.items.splice(idx, 1)" class="text-red-400 hover:text-red-600 text-lg leading-none">×</button>
              </div>
            </div>
            <div v-if="item.prix_gros" class="flex gap-1">
              <button @click="item.type_prix = 'detail'"
                :class="['px-2 py-1 text-xs rounded font-medium transition-colors',
                  item.type_prix === 'detail' ? 'bg-navy text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']">
                Détail ({{ formatPrice(item.prix_detail) }})
              </button>
              <button @click="item.type_prix = 'gros'"
                :class="['px-2 py-1 text-xs rounded font-medium transition-colors',
                  item.type_prix === 'gros' ? 'bg-navy text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']">
                Gros ({{ formatPrice(item.prix_gros) }})
              </button>
            </div>
          </div>
          <div v-if="totalEstime > 0" class="text-right text-sm font-semibold text-navy mt-2">
            Total : {{ formatPrice(totalEstime) }}
          </div>
        </div>

        <div>
          <label class="block text-xs text-slate-500 mb-1">Note</label>
          <textarea v-model="form.note" rows="2" class="input-field w-full resize-none"></textarea>
        </div>
        <p v-if="formError" class="text-red-500 text-xs">{{ formError }}</p>
      </div>
      <div class="flex gap-3 p-5 border-t border-slate-200 flex-shrink-0">
        <button @click="showModal = false"
          class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium">Annuler</button>
        <button @click="saveCommande" :disabled="saving"
          class="flex-1 py-2.5 rounded-lg bg-navy text-white font-medium disabled:opacity-50">
          {{ saving ? 'Création…' : 'Créer la commande' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       MODAL TRANSFORMER EN VENTE
  ═════════════════════════════════════════════════ -->
  <div v-if="showTransformModal && transformCommande" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
      <div class="p-5 border-b border-slate-200">
        <h2 class="font-bold text-navy">Transformer en vente</h2>
        <p class="text-xs text-slate-500 mt-0.5">{{ transformCommande.numero_bon }} — {{ formatPrice(transformCommande.total_ttc) }}</p>
      </div>
      <div class="p-5 space-y-3">
        <label class="block text-xs text-slate-500 mb-1">Mode de paiement</label>
        <div class="grid grid-cols-2 gap-2">
          <button @click="transformMode = 'cash'"
            :class="['py-3 rounded-lg text-sm font-semibold border',
              transformMode === 'cash' ? 'bg-navy text-white border-navy' : 'border-slate-300 text-slate-600']">
            Espèces
          </button>
          <button @click="transformMode = 'credit'"
            :class="['py-3 rounded-lg text-sm font-semibold border',
              transformMode === 'credit' ? 'bg-amber-500 text-white border-amber-500' : 'border-slate-300 text-slate-600']">
            Crédit
          </button>
        </div>
        <p v-if="transformError" class="text-red-500 text-xs">{{ transformError }}</p>
      </div>
      <div class="flex gap-3 p-5 border-t border-slate-200">
        <button @click="showTransformModal = false"
          class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium">Annuler</button>
        <button @click="confirmerTransformation" :disabled="transformSaving"
          class="flex-1 py-2.5 rounded-lg bg-gold text-white font-medium disabled:opacity-50">
          {{ transformSaving ? '…' : 'Confirmer' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { commandesClientsApi, clientsApi, productsApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

const commandes     = ref<any[]>([])
const clients       = ref<any[]>([])
const products      = ref<any[]>([])
const loading       = ref(false)
const filtreStatut   = ref('')
const filtreDateFrom = ref('')
const filtreDateTo   = ref('')

async function loadCommandes() {
  loading.value = true
  try {
    const params: any = {}
    if (filtreStatut.value)   params.statut    = filtreStatut.value
    if (filtreDateFrom.value) params.date_from = filtreDateFrom.value
    if (filtreDateTo.value)   params.date_to   = filtreDateTo.value
    commandes.value = (await commandesClientsApi.list(params)).data.data ?? []
  } finally { loading.value = false }
}

async function loadClients() {
  clients.value = (await clientsApi.list()).data
}

async function loadProducts() {
  const { data } = await productsApi.list({ actif: 1, per_page: 500 })
  products.value = data.data ?? data
}

// ── Nouvelle commande ────────────────────────────────────────────────────────
const showModal  = ref(false)
const saving     = ref(false)
const formError  = ref('')
const form       = ref<any>({
  client_id: '', nom_client: '', telephone_client: '', adresse_livraison: '', note: '',
  items: [{ product_id: '', quantite: 1, type_prix: 'detail', stock: null, prix_detail: 0, prix_gros: 0 }],
})

function openNewCommande() {
  formError.value = ''
  form.value = {
    client_id: '', nom_client: '', telephone_client: '', adresse_livraison: '', note: '',
    items: [{ product_id: '', quantite: 1, type_prix: 'detail', stock: null, prix_detail: 0, prix_gros: 0 }],
  }
  showModal.value = true
}

function addItem() {
  form.value.items.push({ product_id: '', quantite: 1, type_prix: 'detail', stock: null, prix_detail: 0, prix_gros: 0 })
}

function onProductChange(item: any) {
  const prod = products.value.find(p => p.id === item.product_id)
  if (prod) {
    item.stock       = prod.quantite
    item.prix_detail = Number(prod.prix_vente_ht ?? 0)
    item.prix_gros   = Number(prod.prix_vente_gros ?? 0)
    item.type_prix   = 'detail'
  }
}

const totalEstime = computed(() =>
  form.value.items.reduce((sum: number, i: any) => {
    const prix = i.type_prix === 'gros' && i.prix_gros ? i.prix_gros : i.prix_detail
    return sum + (i.quantite || 0) * (prix || 0)
  }, 0)
)

async function saveCommande() {
  if (!form.value.client_id && !form.value.nom_client.trim()) {
    formError.value = 'Choisissez un client ou saisissez un nom.'
    return
  }
  const filledItems = form.value.items.filter((i: any) => i.product_id && i.quantite > 0)
  if (filledItems.length === 0) {
    formError.value = 'Ajoutez au moins un produit.'
    return
  }
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      client_id:          form.value.client_id || null,
      nom_client:         form.value.client_id ? null : form.value.nom_client,
      telephone_client:   form.value.telephone_client || null,
      adresse_livraison:  form.value.adresse_livraison || null,
      note:               form.value.note || null,
      items: filledItems.map((i: any) => ({
        product_id: i.product_id, quantite: i.quantite, type_prix: i.type_prix,
      })),
    }
    await commandesClientsApi.create(payload)
    showModal.value = false
    await loadCommandes()
  } catch (e: any) {
    formError.value = e.response?.data?.message ?? 'Erreur lors de la création.'
  } finally { saving.value = false }
}

// ── Actions statut ───────────────────────────────────────────────────────────
async function changerStatut(c: any, statut: string) {
  const labels: Record<string, string> = { prete: 'prête', livree: 'livrée', annulee: 'annulée' }
  if (!confirm(`Marquer cette commande comme ${labels[statut] ?? statut} ?`)) return
  try {
    await commandesClientsApi.updateStatut(c.id, statut)
    await loadCommandes()
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

async function telechargerBon(c: any) {
  try {
    const { data } = await commandesClientsApi.bonLivraison(c.id)
    const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 10000)
  } catch {
    alert('Impossible de générer le bon de livraison.')
  }
}

// ── Transformer en vente ─────────────────────────────────────────────────────
const showTransformModal = ref(false)
const transformCommande  = ref<any>(null)
const transformMode      = ref<'cash' | 'credit'>('cash')
const transformSaving    = ref(false)
const transformError     = ref('')

function openTransformer(c: any) {
  transformCommande.value = c
  transformMode.value     = 'cash'
  transformError.value    = ''
  showTransformModal.value = true
}

async function confirmerTransformation() {
  if (!transformCommande.value) return
  transformSaving.value = true
  transformError.value  = ''
  try {
    await commandesClientsApi.transformerVente(transformCommande.value.id, transformMode.value)
    showTransformModal.value = false
    await loadCommandes()
  } catch (e: any) {
    transformError.value = e.response?.data?.message ?? 'Erreur lors de la transformation.'
  } finally { transformSaving.value = false }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function statutClass(s: string) {
  return ({
    en_preparation: 'bg-amber-100 text-amber-700',
    prete:          'bg-blue-100 text-blue-700',
    livree:         'bg-emerald-100 text-emerald-700',
    payee:          'bg-green-100 text-green-800',
    annulee:        'bg-red-100 text-red-700',
  } as Record<string, string>)[s] ?? 'bg-slate-100 text-slate-500'
}
function statutIcon(s: string) {
  return ({ en_preparation: '🟡', prete: '🔵', livree: '🟢', payee: '✅', annulee: '🔴' } as Record<string, string>)[s] ?? ''
}
function statutLabel(s: string) {
  return ({
    en_preparation: 'En préparation', prete: 'Prête', livree: 'Livrée', payee: 'Payée', annulee: 'Annulée',
  } as Record<string, string>)[s] ?? s
}
function fmtDate(d: string) {
  return d ? new Date(d).toLocaleDateString('fr-FR') : '—'
}

onMounted(async () => {
  await Promise.all([loadCommandes(), loadClients(), loadProducts()])
})
</script>

<style scoped>
.input-field {
  @apply border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold;
}
</style>
