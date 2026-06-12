<template>
  <div class="min-h-screen bg-gradient-to-br from-navy to-navy-dark flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
      <!-- Header -->
      <div class="px-8 pt-8 pb-6 border-b border-slate-200">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-gold rounded-xl flex items-center justify-center">
            <span class="text-white font-bold">S</span>
          </div>
          <h1 class="text-xl font-bold text-navy">Configuration initiale</h1>
        </div>
        <!-- Step indicator -->
        <div class="flex items-center gap-2 mt-4">
          <div v-for="s in totalSteps" :key="s"
            :class="['h-1.5 flex-1 rounded-full transition-all',
              s <= step ? 'bg-gold' : 'bg-slate-200']" />
        </div>
        <p class="text-xs text-slate-400 mt-2">Étape {{ step }} / {{ totalSteps }}</p>
      </div>

      <!-- Step 1: Sector -->
      <div v-if="step === 1" class="px-8 py-6 space-y-4">
        <h2 class="text-lg font-semibold text-navy">Quel est votre secteur d'activité ?</h2>
        <p class="text-sm text-slate-500">L'IA va proposer des types de produits adaptés à votre activité.</p>
        <div class="grid grid-cols-2 gap-3">
          <button v-for="s in sectors" :key="s"
            @click="form.secteur = s"
            :class="['py-3 px-4 rounded-xl border-2 text-sm font-medium transition-all text-left',
              form.secteur === s ? 'border-gold bg-gold/5 text-gold' : 'border-slate-200 text-slate-600 hover:border-slate-300']">
            {{ s }}
          </button>
        </div>
        <div>
          <input v-model="form.secteur" placeholder="Ou saisissez votre secteur…"
            class="border border-slate-300 rounded-lg px-4 py-2.5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
      </div>

      <!-- Step 2: AI type suggestions -->
      <div v-if="step === 2" class="px-8 py-6 space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="text-lg font-semibold text-navy">Types de produits suggérés par l'IA</h2>
          <span class="text-xs bg-gold/10 text-gold px-2 py-0.5 rounded-full">GPT-4o mini</span>
        </div>
        <p class="text-sm text-slate-500">Sélectionnez les types à activer pour votre organisation.</p>

        <div v-if="loadingSuggestions" class="text-center py-8 text-slate-400">
          <div class="animate-pulse">✨ L'IA génère vos suggestions…</div>
        </div>

        <div v-else class="space-y-3">
          <div v-for="(t, i) in suggestions" :key="i"
            @click="toggleType(i)"
            :class="['rounded-xl border-2 p-4 cursor-pointer transition-all',
              selectedTypes.includes(i) ? 'border-gold bg-gold/5' : 'border-slate-200 hover:border-slate-300']">
            <div class="flex items-start justify-between">
              <div>
                <p class="font-semibold text-navy">{{ t.nom }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ t.description }}</p>
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <span v-for="attr in (t.attributs ?? []).slice(0, 4)" :key="attr.nom"
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
              <div :class="['w-5 h-5 rounded-full border-2 flex-shrink-0 mt-0.5 transition-all',
                selectedTypes.includes(i) ? 'border-gold bg-gold' : 'border-slate-300']">
                <svg v-if="selectedTypes.includes(i)" class="w-full h-full text-white" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3: AI product suggestions (AI plan only) -->
      <div v-if="step === 3 && auth.hasAI" class="px-8 py-6 space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-navy">Produits suggérés pour votre catalogue</h2>
            <span class="text-xs bg-gold/10 text-gold px-2 py-0.5 rounded-full">GPT-4o mini</span>
          </div>
          <div v-if="!loadingProducts && suggestedProducts.length > 0" class="flex gap-2">
            <button @click="selectAllProducts" class="text-xs text-gold hover:underline">Tout sélectionner</button>
            <span class="text-slate-300">|</span>
            <button @click="selectedProducts = []" class="text-xs text-slate-500 hover:underline">Tout désélectionner</button>
          </div>
        </div>
        <p class="text-sm text-slate-500">
          Sélectionnez les produits à importer directement dans votre catalogue.
          <span class="text-slate-400">({{ selectedProducts.length }} sélectionné{{ selectedProducts.length > 1 ? 's' : '' }})</span>
        </p>

        <div v-if="loadingProducts" class="text-center py-10 text-slate-400">
          <div class="space-y-3">
            <div class="h-3 bg-slate-100 rounded animate-pulse w-3/4 mx-auto"></div>
            <div class="h-3 bg-slate-100 rounded animate-pulse w-2/3 mx-auto"></div>
            <div class="h-3 bg-slate-100 rounded animate-pulse w-4/5 mx-auto"></div>
            <p class="text-sm mt-4">✨ L'IA génère votre catalogue initial…</p>
          </div>
        </div>

        <div v-else-if="suggestedProducts.length === 0" class="text-center py-8 text-slate-400">
          <p class="text-sm">Aucun produit suggéré. Vous pourrez en ajouter manuellement depuis le catalogue.</p>
        </div>

        <div v-else class="space-y-4 max-h-96 overflow-y-auto pr-1">
          <div v-for="cat in productCategories" :key="cat">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 sticky top-0 bg-white py-1">{{ cat }}</p>
            <div class="space-y-2">
              <div v-for="(p, i) in productsByCategory(cat)" :key="i"
                @click="toggleProduct(p)"
                :class="['rounded-xl border-2 p-3 cursor-pointer transition-all flex items-center gap-3',
                  isProductSelected(p) ? 'border-gold bg-gold/5' : 'border-slate-200 hover:border-slate-300']">
                <div :class="['w-5 h-5 rounded border-2 flex-shrink-0 flex items-center justify-center transition-all',
                  isProductSelected(p) ? 'border-gold bg-gold' : 'border-slate-300']">
                  <svg v-if="isProductSelected(p)" class="w-3 h-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <p class="font-medium text-navy text-sm truncate">{{ p.nom }}</p>
                    <span class="text-xs text-slate-400 flex-shrink-0">{{ p.reference }}</span>
                  </div>
                  <p v-if="p.description" class="text-xs text-slate-400 truncate">{{ p.description }}</p>
                </div>
                <div class="text-right flex-shrink-0 space-y-0.5">
                  <p class="text-sm font-semibold text-navy">{{ formatPrice(p.prix_vente_ht) }} TND</p>
                  <p class="text-xs text-slate-400">Qté : {{ p.quantite }} {{ p.unite_mesure }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Final step: Confirmation -->
      <div v-if="step === finalStep" class="px-8 py-6 text-center space-y-4">
        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-navy">Configuration terminée !</h2>
        <p class="text-slate-500 text-sm">
          {{ selectedTypes.length }} type(s) de produits ont été créés pour votre organisation.
          <span v-if="createdProductsCount > 0">
            <br />{{ createdProductsCount }} produit(s) ont été importés dans votre catalogue.
          </span>
        </p>
        <button @click="goToDashboard" class="btn-secondary w-full">
          Accéder au tableau de bord →
        </button>
      </div>

      <!-- Footer -->
      <div v-if="step < finalStep" class="px-8 py-5 border-t border-slate-200 flex justify-between items-center">
        <button v-if="step > 1" @click="step--"
          class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">← Retour</button>
        <div v-else />

        <div class="flex gap-3 items-center">
          <button v-if="step === 3 && auth.hasAI && !loadingProducts"
            @click="confirmWithProducts([])"
            class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700">
            Passer cette étape
          </button>

          <button v-if="step === 1" @click="goToStep2" :disabled="!form.secteur"
            class="btn-primary disabled:opacity-60">
            Continuer →
          </button>
          <button v-if="step === 2" @click="goToStep3"
            :disabled="selectedTypes.length === 0 || loadingSuggestions"
            class="btn-primary disabled:opacity-60">
            Continuer →
          </button>
          <button v-if="step === 3 && auth.hasAI"
            @click="confirmWithProducts(selectedProducts)"
            :disabled="saving || loadingProducts"
            class="btn-primary disabled:opacity-60">
            {{ saving ? 'Enregistrement…' : (selectedProducts.length > 0 ? `Importer ${selectedProducts.length} produit(s)` : 'Continuer sans produits') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { onboardingApi } from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()

const totalSteps = computed(() => auth.hasAI ? 4 : 3)
const finalStep  = computed(() => totalSteps.value)

const step               = ref(1)
const form               = ref({ secteur: '' })
const suggestions        = ref<any[]>([])
const selectedTypes      = ref<number[]>([])
const loadingSuggestions = ref(false)
const suggestedProducts  = ref<any[]>([])
const selectedProducts   = ref<any[]>([])
const loadingProducts    = ref(false)
const saving             = ref(false)
const createdProductsCount = ref(0)

const sectors = [
  'Épicerie & Alimentation', 'Pharmacie', 'Textile & Vêtements',
  'Électronique', 'Quincaillerie & BTP', 'Cosmétique & Beauté',
]

// ── Computed ─────────────────────────────────────────────────────────────────

const productCategories = computed(() => {
  const cats = [...new Set(suggestedProducts.value.map((p: any) => p.categorie || 'Général'))]
  return cats
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function productsByCategory(cat: string) {
  return suggestedProducts.value.filter((p: any) => (p.categorie || 'Général') === cat)
}

function isProductSelected(p: any) {
  return selectedProducts.value.some((sp: any) => sp.reference === p.reference && sp.nom === p.nom)
}

function toggleProduct(p: any) {
  const idx = selectedProducts.value.findIndex((sp: any) => sp.reference === p.reference && sp.nom === p.nom)
  if (idx === -1) selectedProducts.value.push(p)
  else selectedProducts.value.splice(idx, 1)
}

function selectAllProducts() {
  selectedProducts.value = [...suggestedProducts.value]
}

function formatPrice(val: number | string) {
  return Number(val).toFixed(3)
}

function toggleType(i: number) {
  const idx = selectedTypes.value.indexOf(i)
  if (idx === -1) selectedTypes.value.push(i)
  else selectedTypes.value.splice(idx, 1)
}

// ── Default fallbacks ─────────────────────────────────────────────────────────

const defaultSuggestions: Record<string, any[]> = {
  default: [
    { nom: 'Produit standard', icone: '📦', description: 'Article générique avec gestion de stock de base', attributs: [
      { nom: 'marque', label: 'Marque', type_donnee: 'text', obligatoire: false },
      { nom: 'poids_kg', label: 'Poids (kg)', type_donnee: 'number', obligatoire: false },
    ]},
    { nom: 'Matière première', icone: '🏭', description: 'Matière utilisée en production ou transformation', attributs: [
      { nom: 'fournisseur', label: 'Fournisseur', type_donnee: 'text', obligatoire: true },
      { nom: 'date_peremption', label: 'Date de péremption', type_donnee: 'date', obligatoire: false },
    ]},
  ],
  'Épicerie & Alimentation': [
    { nom: 'Produit alimentaire', icone: '🍎', description: 'Denrée alimentaire avec traçabilité DLC', attributs: [
      { nom: 'date_peremption', label: 'Date de péremption', type_donnee: 'date', obligatoire: true },
      { nom: 'temperature', label: 'Température de conservation', type_donnee: 'select', obligatoire: false, options_select: 'Ambiant,Réfrigéré,Congelé' },
    ]},
    { nom: 'Boisson', icone: '🥤', description: 'Boissons et liquides', attributs: [
      { nom: 'volume_ml', label: 'Volume (ml)', type_donnee: 'number', obligatoire: true },
      { nom: 'alcoolise', label: 'Alcoolisé', type_donnee: 'boolean', obligatoire: false },
    ]},
  ],
  'Pharmacie': [
    { nom: 'Médicament', icone: '💊', description: 'Produit pharmaceutique avec numéro de lot', attributs: [
      { nom: 'numero_lot', label: 'N° de lot', type_donnee: 'text', obligatoire: true },
      { nom: 'date_peremption', label: 'Date de péremption', type_donnee: 'date', obligatoire: true },
      { nom: 'ordonnance', label: 'Requiert ordonnance', type_donnee: 'boolean', obligatoire: false },
    ]},
    { nom: 'Matériel médical', icone: '🩺', description: 'Dispositifs et consommables médicaux', attributs: [
      { nom: 'sterile', label: 'Stérile', type_donnee: 'boolean', obligatoire: true },
      { nom: 'usage_unique', label: 'Usage unique', type_donnee: 'boolean', obligatoire: false },
    ]},
  ],
  'Électronique': [
    { nom: 'Appareil électronique', icone: '💻', description: 'Appareils avec numéro de série', attributs: [
      { nom: 'numero_serie', label: 'N° de série', type_donnee: 'text', obligatoire: true },
      { nom: 'garantie_mois', label: 'Garantie (mois)', type_donnee: 'number', obligatoire: false },
      { nom: 'tension_v', label: 'Tension (V)', type_donnee: 'number', obligatoire: false },
    ]},
    { nom: 'Accessoire', icone: '🔌', description: 'Câbles, chargeurs et périphériques', attributs: [
      { nom: 'compatibilite', label: 'Compatibilité', type_donnee: 'text', obligatoire: false },
      { nom: 'couleur', label: 'Couleur', type_donnee: 'text', obligatoire: false },
    ]},
  ],
}

function getDefaultSuggestions(secteur: string) {
  for (const key of Object.keys(defaultSuggestions)) {
    if (key !== 'default' && secteur.toLowerCase().includes(key.toLowerCase().split(' ')[0])) {
      return defaultSuggestions[key]
    }
  }
  return defaultSuggestions.default
}

// ── Step navigation ───────────────────────────────────────────────────────────

async function goToStep2() {
  step.value = 2
  loadingSuggestions.value = true
  try {
    if (auth.hasAI) {
      const { data } = await onboardingApi.suggest(form.value.secteur)
      suggestions.value = (data.suggestions ?? []).length > 0
        ? data.suggestions
        : getDefaultSuggestions(form.value.secteur)
    } else {
      suggestions.value = getDefaultSuggestions(form.value.secteur)
    }
    selectedTypes.value = suggestions.value.map((_, i) => i)
  } catch {
    suggestions.value = getDefaultSuggestions(form.value.secteur)
    selectedTypes.value = suggestions.value.map((_, i) => i)
  } finally {
    loadingSuggestions.value = false
  }
}

async function goToStep3() {
  if (!auth.hasAI) {
    await confirmWithProducts([])
    return
  }

  step.value = 3
  loadingProducts.value = true
  suggestedProducts.value = []
  selectedProducts.value = []

  try {
    const { data } = await onboardingApi.suggestProducts(form.value.secteur)
    suggestedProducts.value = data.products ?? []
    selectedProducts.value = [...suggestedProducts.value]
  } catch {
    suggestedProducts.value = []
  } finally {
    loadingProducts.value = false
  }
}

async function confirmWithProducts(products: any[]) {
  saving.value = true
  try {
    const types = selectedTypes.value.map(i => suggestions.value[i])
    const { data } = await onboardingApi.confirm(types, products)
    createdProductsCount.value = data.nb_produits ?? products.length
    await auth.fetchMe()
    step.value = finalStep.value
  } finally {
    saving.value = false
  }
}

function goToDashboard() {
  router.push('/app')
}
</script>
