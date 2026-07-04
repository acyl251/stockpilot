<template>
  <div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-navy">Points de vente</h1>
        <p class="text-slate-500 text-sm mt-1">Gérez vos emplacements de vente et entrepôts</p>
      </div>
      <button @click="openCreate" class="btn-primary">
        + Nouveau point de vente
      </button>
    </div>

    <!-- List -->
    <div v-if="loading" class="text-center py-12 text-slate-400">Chargement…</div>
    <div v-else-if="points.length === 0" class="card text-center py-12 text-slate-400">
      Aucun point de vente. Créez-en un pour commencer.
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="pdv in points" :key="pdv.id" class="card p-4 space-y-3">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-navy">{{ pdv.nom }}</h3>
            <span class="text-xs px-2 py-0.5 rounded-full mt-1 inline-block"
              :class="pdv.type === 'entrepot' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'">
              {{ pdv.type === 'entrepot' ? 'Entrepôt' : 'Point de vente' }}
            </span>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full"
            :class="pdv.actif ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
            {{ pdv.actif ? 'Actif' : 'Inactif' }}
          </span>
        </div>
        <p class="text-slate-500 text-sm">
          {{ pdv.users_count }} utilisateur{{ pdv.users_count !== 1 ? 's' : '' }} rattaché{{ pdv.users_count !== 1 ? 's' : '' }}
        </p>
        <div class="flex gap-2 pt-1">
          <button @click="viewStock(pdv)" class="btn-secondary text-xs px-3 py-1.5">
            Voir stock
          </button>
          <button @click="openEdit(pdv)" class="btn-secondary text-xs px-3 py-1.5">
            Modifier
          </button>
          <button @click="confirmDelete(pdv)" class="text-xs px-3 py-1.5 text-red-600 hover:bg-red-50 rounded-lg transition">
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Modal créer/modifier -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-bold text-navy">{{ editing ? 'Modifier' : 'Nouveau' }} point de vente</h2>
        <div class="space-y-3">
          <div>
            <label class="form-label">Nom</label>
            <input v-model="form.nom" class="input" placeholder="ex: Caisse principale" />
          </div>
          <div>
            <label class="form-label">Type</label>
            <select v-model="form.type" class="input">
              <option value="point_vente">Point de vente</option>
              <option value="entrepot">Entrepôt</option>
            </select>
          </div>
          <div v-if="editing">
            <label class="form-label">Statut</label>
            <select v-model="form.actif" class="input">
              <option :value="true">Actif</option>
              <option :value="false">Inactif</option>
            </select>
          </div>
        </div>
        <div class="flex gap-3 pt-2">
          <button @click="saveForm" :disabled="saving" class="btn-primary flex-1 disabled:opacity-60">
            {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
          <button @click="showForm = false" class="btn-secondary flex-1">Annuler</button>
        </div>
        <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
      </div>
    </div>

    <!-- Modal stock d'un PDV -->
    <div v-if="stockModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 space-y-4 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-bold text-navy">Stock — {{ stockModal.nom }}</h2>
          <button @click="stockModal = null" class="text-slate-400 hover:text-slate-600 text-xl">×</button>
        </div>
        <div class="overflow-y-auto flex-1">
          <div v-if="stockLoading" class="text-center py-8 text-slate-400">Chargement…</div>
          <div v-else-if="stockItems.length === 0" class="text-center py-8 text-slate-400">
            Aucun produit stocké ici.
          </div>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-left text-slate-500 border-b">
                <th class="pb-2">Produit</th>
                <th class="pb-2">Référence</th>
                <th class="pb-2 text-right">Quantité</th>
                <th class="pb-2 text-right">Alerte</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in stockItems" :key="s.product_id"
                :class="s.en_alerte ? 'bg-red-50' : ''"
                class="border-b last:border-0">
                <td class="py-2 font-medium text-navy">{{ s.nom }}</td>
                <td class="py-2 text-slate-500">{{ s.reference || '—' }}</td>
                <td class="py-2 text-right">{{ s.quantite }} {{ s.unite_mesure }}</td>
                <td class="py-2 text-right">
                  <span v-if="s.en_alerte" class="text-red-600 font-semibold">⚠</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { pointsDeVenteApi } from '@/services/api'

interface Pdv {
  id: number
  nom: string
  type: 'entrepot' | 'point_vente'
  actif: boolean
  users_count: number
}

const points      = ref<Pdv[]>([])
const loading     = ref(true)
const showForm    = ref(false)
const editing     = ref<Pdv | null>(null)
const saving      = ref(false)
const error       = ref('')
const stockModal  = ref<Pdv | null>(null)
const stockItems  = ref<any[]>([])
const stockLoading = ref(false)

const form = ref({ nom: '', type: 'point_vente' as 'entrepot' | 'point_vente', actif: true })

async function load() {
  loading.value = true
  try {
    const { data } = await pointsDeVenteApi.list()
    points.value = data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { nom: '', type: 'point_vente', actif: true }
  error.value = ''
  showForm.value = true
}

function openEdit(pdv: Pdv) {
  editing.value = pdv
  form.value = { nom: pdv.nom, type: pdv.type, actif: pdv.actif }
  error.value = ''
  showForm.value = true
}

async function saveForm() {
  if (!form.value.nom.trim()) { error.value = 'Le nom est requis.'; return }
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await pointsDeVenteApi.update(editing.value.id, form.value)
    } else {
      await pointsDeVenteApi.create(form.value)
    }
    showForm.value = false
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(pdv: Pdv) {
  if (!confirm(`Supprimer "${pdv.nom}" ?`)) return
  try {
    await pointsDeVenteApi.destroy(pdv.id)
    await load()
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur lors de la suppression.')
  }
}

async function viewStock(pdv: Pdv) {
  stockModal.value = pdv
  stockLoading.value = true
  stockItems.value = []
  try {
    const { data } = await pointsDeVenteApi.stock(pdv.id)
    stockItems.value = data.stock
  } finally {
    stockLoading.value = false
  }
}

onMounted(load)
</script>
