<template>
  <div class="space-y-5">
    <!-- Header + tabs -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold text-navy">Fournisseurs</h1>
      <button v-if="activeTab === 'fournisseurs'" @click="openFournisseurForm()"
        class="btn-primary text-sm">+ Nouveau fournisseur</button>
      <button v-else @click="openCommandeForm()"
        class="btn-primary text-sm">+ Nouvelle commande</button>
    </div>

    <div class="flex gap-1 border-b border-slate-200">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
          activeTab === t.key
            ? 'border-gold text-gold'
            : 'border-transparent text-slate-500 hover:text-slate-700']">
        {{ t.label }}
      </button>
    </div>

    <!-- ═══ TAB FOURNISSEURS ═══ -->
    <div v-if="activeTab === 'fournisseurs'">
      <div v-if="loadingF" class="text-center py-10 text-slate-400">Chargement…</div>
      <div v-else-if="fournisseurs.length === 0" class="text-center py-16 text-slate-400">
        <p class="text-4xl mb-3">🏭</p>
        <p class="font-medium">Aucun fournisseur</p>
        <p class="text-sm mt-1">Cliquez sur « Nouveau fournisseur » pour commencer.</p>
      </div>
      <div v-else class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nom</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden sm:table-cell">Téléphone</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden md:table-cell">Email</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Statut</th>
              <th class="px-4 py-3 w-36"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in fournisseurs" :key="f.id"
              class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
              <td class="px-4 py-3 font-medium text-navy">{{ f.nom }}</td>
              <td class="px-4 py-3 text-slate-500 hidden sm:table-cell">{{ f.telephone ?? '—' }}</td>
              <td class="px-4 py-3 text-slate-500 hidden md:table-cell">{{ f.email ?? '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full',
                  f.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500']">
                  {{ f.active ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2 justify-end">
                  <button @click="openCommandeForm(f.id)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium whitespace-nowrap">Commander</button>
                  <button @click="openFournisseurForm(f)"
                    class="text-xs text-slate-500 hover:text-navy font-medium">Modifier</button>
                  <button @click="deleteFournisseur(f)"
                    class="text-xs text-red-500 hover:text-red-700 font-medium">Supprimer</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ TAB COMMANDES ═══ -->
    <div v-if="activeTab === 'commandes'" class="space-y-4">
      <!-- Filtres -->
      <div class="flex flex-wrap gap-3">
        <select v-model="filtreStatut" @change="loadCommandes"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les statuts</option>
          <option value="brouillon">Brouillon</option>
          <option value="envoyee">Envoyée</option>
          <option value="recue">Reçue</option>
          <option value="annulee">Annulée</option>
        </select>
        <select v-model="filtreFournisseur" @change="loadCommandes"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les fournisseurs</option>
          <option v-for="f in fournisseurs" :key="f.id" :value="f.id">{{ f.nom }}</option>
        </select>
      </div>

      <div v-if="loadingC" class="text-center py-10 text-slate-400">Chargement…</div>
      <div v-else-if="commandes.length === 0" class="text-center py-16 text-slate-400">
        <p class="text-4xl mb-3">📋</p>
        <p>Aucune commande pour ces critères.</p>
      </div>
      <div v-else class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Date</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Fournisseur</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600 hidden sm:table-cell">Articles</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Statut</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600 hidden md:table-cell">Livraison prévue</th>
              <th class="px-4 py-3 w-28"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in commandes" :key="c.id"
              class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer"
              @click="openDetail(c.id)">
              <td class="px-4 py-3 text-slate-600">{{ fmtDate(c.date_commande) }}</td>
              <td class="px-4 py-3 font-medium text-navy">{{ c.fournisseur?.nom }}</td>
              <td class="px-4 py-3 text-center text-slate-500 hidden sm:table-cell">{{ c.items?.length ?? 0 }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statutClass(c.statut)]">
                  {{ statutLabel(c.statut) }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-500 hidden md:table-cell">
                {{ c.date_livraison_prevue ? fmtDate(c.date_livraison_prevue) : '—' }}
              </td>
              <td class="px-4 py-3" @click.stop>
                <div class="flex items-center gap-2 justify-end">
                  <button v-if="c.statut === 'envoyee'" @click.stop="openReception(c.id)"
                    class="text-xs text-emerald-600 hover:text-emerald-800 font-medium whitespace-nowrap">
                    Réceptionner
                  </button>
                  <button v-if="c.statut === 'brouillon'" @click.stop="envoyerCommande(c.id)"
                    class="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">
                    Envoyer
                  </button>
                  <button v-if="['brouillon','envoyee'].includes(c.statut)" @click.stop="deleteCommande(c)"
                    class="text-xs text-red-500 hover:text-red-700 font-medium">
                    Supprimer
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       MODAL FOURNISSEUR
  ═════════════════════════════════════════════════ -->
  <div v-if="showFournisseurModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
      <div class="p-5 border-b border-slate-200">
        <h2 class="font-bold text-navy">{{ fForm.id ? 'Modifier le fournisseur' : 'Nouveau fournisseur' }}</h2>
      </div>
      <div class="p-5 space-y-3">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Nom *</label>
          <input v-model="fForm.nom" class="input-field w-full" placeholder="Ex: Moulins du Nord" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Téléphone</label>
            <input v-model="fForm.telephone" class="input-field w-full" placeholder="Ex: 70 000 000" />
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Email</label>
            <input v-model="fForm.email" type="email" class="input-field w-full" placeholder="contact@..." />
          </div>
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Adresse</label>
          <input v-model="fForm.adresse" class="input-field w-full" />
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Note</label>
          <textarea v-model="fForm.note" rows="2" class="input-field w-full resize-none"></textarea>
        </div>
        <p v-if="fError" class="text-red-500 text-xs">{{ fError }}</p>
      </div>
      <div class="flex gap-3 p-5 border-t border-slate-200">
        <button @click="showFournisseurModal = false"
          class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium">Annuler</button>
        <button @click="saveFournisseur" :disabled="fSaving"
          class="flex-1 py-2.5 rounded-lg bg-navy text-white font-medium disabled:opacity-50">
          {{ fSaving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       MODAL COMMANDE
  ═════════════════════════════════════════════════ -->
  <div v-if="showCommandeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
      <div class="p-5 border-b border-slate-200 flex-shrink-0">
        <h2 class="font-bold text-navy">{{ cForm.id ? 'Modifier la commande' : 'Nouvelle commande fournisseur' }}</h2>
      </div>
      <div class="p-5 space-y-4 overflow-y-auto flex-1">
        <!-- Entête commande -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-slate-500 mb-1">Fournisseur *</label>
            <select v-model="cForm.fournisseur_id" class="input-field w-full">
              <option value="">— Choisir —</option>
              <option v-for="f in fournisseurs.filter(f => f.active)" :key="f.id" :value="f.id">{{ f.nom }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Date commande *</label>
            <input v-model="cForm.date_commande" type="date" class="input-field w-full" />
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Date livraison prévue</label>
            <input v-model="cForm.date_livraison_prevue" type="date" class="input-field w-full" />
          </div>
          <div>
            <label class="block text-xs text-slate-500 mb-1">Note</label>
            <input v-model="cForm.note" class="input-field w-full" />
          </div>
        </div>

        <!-- Lignes -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-slate-700">Lignes de commande</h3>
            <button @click="addItem" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Ajouter un produit</button>
          </div>
          <div v-for="(item, idx) in cForm.items" :key="idx"
            class="grid grid-cols-12 gap-2 items-center mb-2">
            <div class="col-span-5">
              <select v-model="item.product_id" @change="onProductChange(item)" class="input-field w-full text-xs">
                <option value="">— Produit —</option>
                <option v-for="p in simpleProducts" :key="p.id" :value="p.id">{{ p.nom }}</option>
              </select>
            </div>
            <div class="col-span-2">
              <input v-model.number="item.quantite" type="number" min="0.001" step="0.001"
                placeholder="Qté" class="input-field w-full text-xs" />
            </div>
            <div class="col-span-2">
              <input v-model="item.unite" placeholder="Unité" class="input-field w-full text-xs" />
            </div>
            <div class="col-span-2">
              <input v-model.number="item.prix_unitaire" type="number" min="0" step="0.001"
                placeholder="Prix" class="input-field w-full text-xs" />
            </div>
            <div class="col-span-1 flex justify-center">
              <button @click="cForm.items.splice(idx, 1)" class="text-red-400 hover:text-red-600 text-lg leading-none">×</button>
            </div>
          </div>
          <div v-if="totalEstime > 0" class="text-right text-sm font-semibold text-navy mt-2">
            Total estimé : {{ formatPrice(totalEstime) }}
          </div>
        </div>
        <p v-if="cError" class="text-red-500 text-xs">{{ cError }}</p>
      </div>
      <div class="flex gap-3 p-5 border-t border-slate-200 flex-shrink-0">
        <button @click="showCommandeModal = false"
          class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium">Annuler</button>
        <button @click="saveCommande('brouillon')" :disabled="cSaving"
          class="flex-1 py-2.5 rounded-lg border border-navy text-navy font-medium disabled:opacity-50">
          {{ cSaving ? '…' : 'Brouillon' }}
        </button>
        <button @click="saveCommande('envoyee')" :disabled="cSaving"
          class="flex-1 py-2.5 rounded-lg bg-navy text-white font-medium disabled:opacity-50">
          {{ cSaving ? '…' : 'Envoyer' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       MODAL DÉTAIL COMMANDE
  ═════════════════════════════════════════════════ -->
  <div v-if="showDetailModal && detailCommande" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">
      <div class="p-5 border-b border-slate-200 flex items-center justify-between flex-shrink-0">
        <div>
          <h2 class="font-bold text-navy">Commande #{{ detailCommande.id }}</h2>
          <p class="text-xs text-slate-500 mt-0.5">{{ detailCommande.fournisseur?.nom }} — {{ fmtDate(detailCommande.date_commande) }}</p>
        </div>
        <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statutClass(detailCommande.statut)]">
          {{ statutLabel(detailCommande.statut) }}
        </span>
      </div>
      <div class="p-5 overflow-y-auto flex-1 space-y-3">
        <div v-if="detailCommande.note" class="bg-slate-50 rounded-lg px-3 py-2 text-sm text-slate-600">
          {{ detailCommande.note }}
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200">
              <th class="text-left py-2 text-slate-500 font-medium">Produit</th>
              <th class="text-right py-2 text-slate-500 font-medium">Qté</th>
              <th class="text-left py-2 text-slate-500 font-medium pl-2">Unité</th>
              <th class="text-right py-2 text-slate-500 font-medium">Prix unit.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in detailCommande.items" :key="item.id" class="border-b border-slate-100">
              <td class="py-2 font-medium text-navy">{{ item.product?.nom }}</td>
              <td class="py-2 text-right font-mono">{{ item.quantite }}</td>
              <td class="py-2 text-slate-500 pl-2">{{ item.unite }}</td>
              <td class="py-2 text-right text-slate-600">
                {{ item.prix_unitaire ? item.prix_unitaire + ' DT' : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="detailCommande.date_livraison_prevue" class="text-xs text-slate-500">
          Livraison prévue le {{ fmtDate(detailCommande.date_livraison_prevue) }}
        </div>
      </div>
      <div class="flex gap-3 p-5 border-t border-slate-200 flex-shrink-0">
        <button @click="showDetailModal = false"
          class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium">Fermer</button>
        <button v-if="detailCommande.statut === 'envoyee'" @click="openReceptionFromDetail"
          class="flex-1 py-2.5 rounded-lg bg-emerald-600 text-white font-medium">
          Réceptionner
        </button>
        <button v-if="detailCommande.statut === 'brouillon'" @click="envoyerCommande(detailCommande.id, true)"
          class="flex-1 py-2.5 rounded-lg bg-navy text-white font-medium">
          Marquer envoyée
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       MODAL RÉCEPTION
  ═════════════════════════════════════════════════ -->
  <div v-if="showReceptionModal && detailCommande" class="fixed inset-0 bg-black/60 flex items-center justify-center z-[60] p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">
      <div class="p-5 border-b border-slate-200 flex-shrink-0">
        <h2 class="font-bold text-navy">Réception commande #{{ detailCommande.id }}</h2>
        <p class="text-xs text-slate-500 mt-0.5">Vérifiez les quantités reçues et les prix réels</p>
      </div>
      <div class="p-5 overflow-y-auto flex-1 space-y-3">
        <div v-for="(item, idx) in receptionItems" :key="item.item_id"
          class="border border-slate-200 rounded-xl p-3 space-y-2">
          <p class="font-medium text-navy text-sm">{{ detailCommande.items[idx]?.product?.nom }}</p>
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <label class="block text-slate-500 mb-1">Qté commandée</label>
              <p class="font-mono text-slate-700">{{ detailCommande.items[idx]?.quantite }} {{ detailCommande.items[idx]?.unite }}</p>
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Qté reçue *</label>
              <input v-model.number="item.quantite_recue" type="number" min="0" step="0.001"
                class="input-field w-full text-sm" />
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Prix unitaire réel (optionnel)</label>
              <input v-model.number="item.prix_unitaire_reel" type="number" min="0" step="0.001"
                placeholder="DT" class="input-field w-full text-sm" />
            </div>
          </div>
        </div>
      </div>
      <div class="flex gap-3 p-5 border-t border-slate-200 flex-shrink-0">
        <button @click="showReceptionModal = false"
          class="flex-1 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium">Annuler</button>
        <button @click="validerReception" :disabled="rSaving"
          class="flex-1 py-2.5 rounded-lg bg-emerald-600 text-white font-medium disabled:opacity-50">
          {{ rSaving ? 'Validation…' : 'Valider la réception' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { fournisseursApi, commandesFournisseurApi, productsApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

interface Fournisseur { id: number; nom: string; telephone?: string; email?: string; adresse?: string; note?: string; active: boolean }
interface CommandeItem { id: number; product_id: number; quantite: number; prix_unitaire?: number; unite: string; product?: any }
interface Commande {
  id: number; fournisseur_id: number; fournisseur?: Fournisseur; statut: string
  date_commande: string; date_livraison_prevue?: string; note?: string; items: CommandeItem[]
}

const activeTab = ref<'fournisseurs' | 'commandes'>('fournisseurs')
const tabs: { key: 'fournisseurs' | 'commandes'; label: string }[] = [
  { key: 'fournisseurs', label: 'Fournisseurs' },
  { key: 'commandes',    label: 'Commandes' },
]

// ── Data ────────────────────────────────────────────────────────────────────
const fournisseurs    = ref<Fournisseur[]>([])
const commandes       = ref<Commande[]>([])
const simpleProducts  = ref<any[]>([])
const loadingF        = ref(false)
const loadingC        = ref(false)
const filtreStatut    = ref('')
const filtreFournisseur = ref('')

async function loadFournisseurs() {
  loadingF.value = true
  try { fournisseurs.value = (await fournisseursApi.list()).data } finally { loadingF.value = false }
}
async function loadCommandes() {
  loadingC.value = true
  try {
    const params: any = {}
    if (filtreStatut.value)    params.statut         = filtreStatut.value
    if (filtreFournisseur.value) params.fournisseur_id = filtreFournisseur.value
    commandes.value = (await commandesFournisseurApi.list(params)).data.data ?? []
  } finally { loadingC.value = false }
}
async function loadProducts() {
  const { data } = await productsApi.list({ type: 'simple', actif: 1, per_page: 500 })
  simpleProducts.value = data.data ?? data
}

// ── Fournisseur CRUD ────────────────────────────────────────────────────────
const showFournisseurModal = ref(false)
const fError  = ref('')
const fSaving = ref(false)
const fForm   = ref<any>({ id: null, nom: '', telephone: '', email: '', adresse: '', note: '' })

function openFournisseurForm(f?: Fournisseur) {
  fError.value = ''
  fForm.value  = f
    ? { id: f.id, nom: f.nom, telephone: f.telephone ?? '', email: f.email ?? '', adresse: f.adresse ?? '', note: f.note ?? '' }
    : { id: null, nom: '', telephone: '', email: '', adresse: '', note: '' }
  showFournisseurModal.value = true
}
async function saveFournisseur() {
  if (! fForm.value.nom.trim()) { fError.value = 'Le nom est requis.'; return }
  fSaving.value = true; fError.value = ''
  try {
    const payload = {
      nom: fForm.value.nom, telephone: fForm.value.telephone || null,
      email: fForm.value.email || null, adresse: fForm.value.adresse || null,
      note: fForm.value.note || null,
    }
    if (fForm.value.id) await fournisseursApi.update(fForm.value.id, payload)
    else await fournisseursApi.create(payload)
    showFournisseurModal.value = false
    await loadFournisseurs()
  } catch (e: any) {
    fError.value = e.response?.data?.message ?? 'Erreur.'
  } finally { fSaving.value = false }
}
async function deleteFournisseur(f: Fournisseur) {
  if (! confirm(`Supprimer le fournisseur « ${f.nom} » ?`)) return
  await fournisseursApi.destroy(f.id)
  await loadFournisseurs()
}

// ── Commande CRUD ────────────────────────────────────────────────────────────
const showCommandeModal = ref(false)
const cError  = ref('')
const cSaving = ref(false)
const cForm   = ref<any>({
  id: null, fournisseur_id: '', date_commande: today(), date_livraison_prevue: '', note: '',
  items: [{ product_id: '', quantite: 1, unite: '', prix_unitaire: null }],
})

const totalEstime = computed(() =>
  cForm.value.items.reduce((sum: number, i: any) => sum + (i.quantite || 0) * (i.prix_unitaire || 0), 0)
)

function today() { return new Date().toISOString().slice(0, 10) }

function openCommandeForm(fournisseurId?: number) {
  cError.value = ''
  cForm.value  = {
    id: null,
    fournisseur_id: fournisseurId ?? '',
    date_commande: today(),
    date_livraison_prevue: '',
    note: '',
    items: [{ product_id: '', quantite: 1, unite: '', prix_unitaire: null }],
  }
  showCommandeModal.value = true
}
function addItem() {
  cForm.value.items.push({ product_id: '', quantite: 1, unite: '', prix_unitaire: null })
}
function onProductChange(item: any) {
  const prod = simpleProducts.value.find(p => p.id === item.product_id)
  if (prod) item.unite = prod.unite_mesure ?? ''
}
async function saveCommande(statut: string) {
  if (! cForm.value.fournisseur_id) { cError.value = 'Choisissez un fournisseur.'; return }
  const filledItems = cForm.value.items.filter((i: any) => i.product_id && i.quantite > 0)
  if (filledItems.length === 0) { cError.value = 'Ajoutez au moins une ligne.'; return }
  cSaving.value = true; cError.value = ''
  try {
    const payload = {
      fournisseur_id:        cForm.value.fournisseur_id,
      date_commande:         cForm.value.date_commande,
      date_livraison_prevue: cForm.value.date_livraison_prevue || null,
      note:                  cForm.value.note || null,
      statut,
      items: filledItems.map((i: any) => ({
        product_id: i.product_id, quantite: i.quantite,
        unite: i.unite, prix_unitaire: i.prix_unitaire || null,
      })),
    }
    if (cForm.value.id) await commandesFournisseurApi.update(cForm.value.id, payload)
    else await commandesFournisseurApi.create(payload)
    showCommandeModal.value = false
    await loadCommandes()
    activeTab.value = 'commandes'
  } catch (e: any) {
    cError.value = e.response?.data?.message ?? 'Erreur.'
  } finally { cSaving.value = false }
}

async function envoyerCommande(id: number, closeDetail = false) {
  if (! confirm('Marquer cette commande comme envoyée ?')) return
  await commandesFournisseurApi.envoyer(id)
  if (closeDetail) showDetailModal.value = false
  await loadCommandes()
}
async function deleteCommande(c: Commande) {
  if (! confirm('Supprimer cette commande ?')) return
  await commandesFournisseurApi.destroy(c.id)
  await loadCommandes()
}

// ── Détail commande ──────────────────────────────────────────────────────────
const showDetailModal = ref(false)
const detailCommande  = ref<Commande | null>(null)

async function openDetail(id: number) {
  const { data } = await commandesFournisseurApi.get(id)
  detailCommande.value = data
  showDetailModal.value = true
}

// ── Réception ────────────────────────────────────────────────────────────────
const showReceptionModal = ref(false)
const rSaving = ref(false)
const receptionItems = ref<{ item_id: number; quantite_recue: number; prix_unitaire_reel?: number | null }[]>([])

function openReception(id: number) {
  openDetail(id).then(() => {
    if (!detailCommande.value) return
    receptionItems.value = detailCommande.value.items.map(i => ({
      item_id: i.id,
      quantite_recue: Number(i.quantite),
      prix_unitaire_reel: i.prix_unitaire ? Number(i.prix_unitaire) : null,
    }))
    showReceptionModal.value = true
  })
}
function openReceptionFromDetail() {
  if (!detailCommande.value) return
  receptionItems.value = detailCommande.value.items.map(i => ({
    item_id: i.id,
    quantite_recue: Number(i.quantite),
    prix_unitaire_reel: i.prix_unitaire ? Number(i.prix_unitaire) : null,
  }))
  showDetailModal.value  = false
  showReceptionModal.value = true
}

async function validerReception() {
  if (! detailCommande.value) return
  rSaving.value = true
  try {
    await commandesFournisseurApi.receptionner(detailCommande.value.id, {
      items: receptionItems.value.map(i => ({
        item_id: i.item_id,
        quantite_recue: i.quantite_recue,
        prix_unitaire_reel: i.prix_unitaire_reel || null,
      })),
    })
    showReceptionModal.value = false
    showDetailModal.value    = false
    await loadCommandes()
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur lors de la réception.')
  } finally { rSaving.value = false }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function statutClass(s: string) {
  return {
    brouillon: 'bg-slate-100 text-slate-600',
    envoyee:   'bg-blue-100 text-blue-700',
    recue:     'bg-emerald-100 text-emerald-700',
    annulee:   'bg-red-100 text-red-700',
  }[s] ?? 'bg-slate-100 text-slate-500'
}
function statutLabel(s: string) {
  return { brouillon: 'Brouillon', envoyee: 'Envoyée', recue: 'Reçue', annulee: 'Annulée' }[s] ?? s
}
function fmtDate(d: string) {
  return d ? new Date(d).toLocaleDateString('fr-FR') : '—'
}

onMounted(async () => {
  await Promise.all([loadFournisseurs(), loadCommandes(), loadProducts()])
})
</script>

<style scoped>
.input-field {
  @apply border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold;
}
</style>
