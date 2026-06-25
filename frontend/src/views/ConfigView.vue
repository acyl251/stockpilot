<template>
  <div class="space-y-6">
    <!-- Informations de facturation (apparaissent sur les factures PDF) -->
    <div class="card">
      <div class="flex items-center justify-between mb-1">
        <h2 class="text-lg font-semibold text-navy">Informations de facturation</h2>
        <span class="text-xs text-slate-400">Affichées sur vos factures PDF</span>
      </div>
      <p class="text-sm text-slate-500 mb-4">Renseignez votre matricule fiscal et votre adresse pour des factures conformes.</p>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Raison sociale</label>
          <input v-model="org.nom" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Matricule fiscal</label>
          <input v-model="org.matricule_fiscal" placeholder="Ex : 1234567A/P/M/000"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Téléphone</label>
          <input v-model="org.telephone" class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Email</label>
          <input :value="org.email_contact" disabled class="border border-slate-200 bg-slate-50 rounded-lg px-3 py-2 text-sm w-full text-slate-400" />
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs text-slate-500 mb-1">Adresse</label>
          <input v-model="org.adresse" placeholder="Rue, ville, code postal"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
      </div>
      <div class="flex items-center gap-3 mt-4">
        <button @click="saveOrg" :disabled="orgSaving" class="btn-primary disabled:opacity-60">
          {{ orgSaving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
        <span v-if="orgSaved" class="text-emerald-600 text-sm">✓ Enregistré</span>
        <span v-if="orgError" class="text-red-500 text-sm">{{ orgError }}</span>
      </div>
    </div>

    <div class="flex items-center justify-between border-t border-slate-200 pt-6">
      <h2 class="text-lg font-semibold text-navy">Types de produits</h2>
      <button @click="showTypeForm = true" class="btn-primary">+ Nouveau type</button>
    </div>

    <div v-if="loading" class="text-center py-10 text-slate-400">Chargement…</div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="t in types" :key="t.id" class="card hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
          <div>
            <p class="font-semibold text-navy">{{ t.icone }} {{ t.nom }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ t.attributes?.length ?? 0 }} attribut(s)</p>
          </div>
          <span v-if="t.suggere_par_ia"
            class="text-xs bg-gold/10 text-gold px-2 py-0.5 rounded-full font-medium">IA</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="attr in t.attributes" :key="attr.id"
            :class="['text-xs px-2 py-0.5 rounded-full font-medium',
              attr.type_donnee === 'number'  ? 'bg-blue-100 text-blue-700' :
              attr.type_donnee === 'date'    ? 'bg-purple-100 text-purple-700' :
              attr.type_donnee === 'boolean' ? 'bg-emerald-100 text-emerald-700' :
              attr.type_donnee === 'select'  ? 'bg-amber-100 text-amber-700' :
                                               'bg-slate-100 text-slate-600']">
            {{ attr.label }}
          </span>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="types.length === 0" class="col-span-full text-center py-10 text-slate-400">
        Aucun type de produit. Cliquez sur « Nouveau type » pour commencer.
      </div>
    </div>

    <!-- Categories section -->
    <div class="border-t border-slate-200 pt-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-navy">Catégories</h2>
        <button @click="openCatCreate" class="btn-secondary text-sm">+ Nouvelle catégorie</button>
      </div>
      <div class="flex flex-wrap gap-2">
        <div v-for="c in categories" :key="c.id"
          class="group flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-2">
          <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: c.couleur }" />
          <span class="text-sm font-medium text-slate-700">{{ c.nom }}</span>
          <div class="flex items-center gap-1.5 ml-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button @click="editCategory(c)" title="Modifier"
              class="text-slate-400 hover:text-navy text-xs">✎</button>
            <button @click="deleteCategory(c)" title="Supprimer"
              class="text-slate-400 hover:text-red-600 text-xs">🗑</button>
          </div>
        </div>
        <div v-if="categories.length === 0" class="text-slate-400 text-sm py-2">
          Aucune catégorie. Cliquez sur « Nouvelle catégorie ».
        </div>
      </div>
    </div>

    <!-- Type form modal -->
    <TypeFormModal v-if="showTypeForm" @close="showTypeForm = false" @saved="onTypeSaved" />

    <!-- Category form modal (create or edit) -->
    <div v-if="showCatForm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/30">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <h3 class="font-semibold text-navy">{{ catForm.id ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</h3>
        <input v-model="catForm.nom" placeholder="Nom *" class="border rounded-lg px-3 py-2 text-sm w-full" />
        <div class="flex items-center gap-2">
          <input v-model="catForm.couleur" type="color" class="w-12 h-8 rounded cursor-pointer" />
          <span class="text-xs text-slate-400">Couleur</span>
        </div>
        <p v-if="catError" class="text-red-500 text-xs">{{ catError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showCatForm = false" class="text-sm text-slate-500">Annuler</button>
          <button @click="saveCategory" :disabled="catSaving" class="btn-primary text-sm disabled:opacity-60">
            {{ catSaving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useProductsStore } from '@/stores/products'
import { categoriesApi, organisationApi } from '@/services/api'
import TypeFormModal from '@/components/TypeFormModal.vue'

const store        = useProductsStore()
const types        = ref<any[]>([])
const categories   = ref<any[]>([])
const loading      = ref(true)

// Informations de facturation
const org      = ref<any>({ nom: '', matricule_fiscal: '', telephone: '', adresse: '', email_contact: '' })
const orgSaving = ref(false)
const orgSaved  = ref(false)
const orgError  = ref('')

async function loadOrg() {
  try {
    const { data } = await organisationApi.get()
    org.value = data
  } catch { /* ignore */ }
}

async function saveOrg() {
  orgSaving.value = true
  orgSaved.value = false
  orgError.value = ''
  try {
    await organisationApi.update({
      nom: org.value.nom,
      matricule_fiscal: org.value.matricule_fiscal || null,
      telephone: org.value.telephone || null,
      adresse: org.value.adresse || null,
    })
    orgSaved.value = true
    setTimeout(() => (orgSaved.value = false), 3000)
  } catch (e: any) {
    orgError.value = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.'
  } finally {
    orgSaving.value = false
  }
}
const showTypeForm = ref(false)
const showCatForm  = ref(false)
const catSaving    = ref(false)
const catError     = ref('')
const catForm      = ref<{ id: number | null; nom: string; couleur: string }>({ id: null, nom: '', couleur: '#C9A84C' })

async function load() {
  loading.value = true
  await Promise.all([store.fetchTypes(), store.fetchCategories()])
  types.value      = store.types
  categories.value = store.categories
  loading.value    = false
}

async function onTypeSaved() {
  showTypeForm.value = false
  await load()
}

function openCatCreate() {
  catForm.value = { id: null, nom: '', couleur: '#C9A84C' }
  catError.value = ''
  showCatForm.value = true
}

function editCategory(c: any) {
  catForm.value = { id: c.id, nom: c.nom, couleur: c.couleur ?? '#C9A84C' }
  catError.value = ''
  showCatForm.value = true
}

async function saveCategory() {
  if (!catForm.value.nom.trim()) {
    catError.value = 'Le nom est requis.'
    return
  }
  catSaving.value = true
  catError.value = ''
  try {
    const payload = { nom: catForm.value.nom, couleur: catForm.value.couleur }
    if (catForm.value.id) {
      await categoriesApi.update(catForm.value.id, payload)
    } else {
      await categoriesApi.create(payload)
    }
    showCatForm.value = false
    await refreshCategories()
  } catch (e: any) {
    catError.value = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.'
  } finally {
    catSaving.value = false
  }
}

async function deleteCategory(c: any) {
  if (!confirm(`Supprimer la catégorie « ${c.nom} » ?`)) return
  await categoriesApi.destroy(c.id)
  await refreshCategories()
}

async function refreshCategories() {
  await store.fetchCategories()
  categories.value = store.categories
}

onMounted(() => { load(); loadOrg() })
</script>
