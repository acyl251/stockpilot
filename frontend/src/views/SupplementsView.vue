<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-navy">Suppléments</h1>
      <button v-if="!auth.isRestrictedOperateur" @click="openCreate" class="btn-primary flex items-center gap-2">+ Nouveau supplément</button>
    </div>

    <div v-if="loading" class="text-center py-16 text-slate-400">Chargement…</div>

    <div v-else-if="supplements.length === 0"
      class="text-center py-16 border-2 border-dashed border-slate-200 rounded-xl text-slate-400">
      <p class="text-lg font-medium">Aucun supplément créé.</p>
      <button @click="openCreate" class="mt-3 text-gold hover:underline text-sm">+ Créer le premier supplément</button>
    </div>

    <!-- Cards grid -->
    <div v-else class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))">
      <div v-for="s in supplements" :key="s.id"
        :class="['card flex flex-col gap-3 transition-shadow hover:shadow-md', !s.active && 'opacity-50']">

        <!-- Name + price -->
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-bold text-navy text-base leading-snug">{{ s.nom }}</p>
            <span v-if="!s.active" class="text-xs text-slate-400">Désactivé</span>
          </div>
          <p class="text-gold font-bold text-sm shrink-0">{{ money(s.prix_vente) }}</p>
        </div>

        <!-- Ingredient + dose -->
        <div class="text-xs text-slate-500 space-y-0.5">
          <p class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
            <span class="truncate font-medium text-slate-700">{{ s.ingredient?.nom ?? '—' }}</span>
          </p>
          <p class="pl-3 text-slate-400">{{ s.quantite }} {{ s.unite || s.ingredient?.unite_mesure }} / portion</p>
        </div>

        <!-- Food cost -->
        <template v-if="s.ingredient">
          <div class="border-t border-slate-100 pt-2.5 space-y-1">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500">Coût matière</span>
              <span class="font-semibold text-slate-700 font-mono">{{ money(coutMatiere(s)) }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500">Food cost</span>
              <span :class="['px-2 py-0.5 rounded-full font-bold text-xs', foodCostBadge(s)]">
                {{ foodCostPct(s) != null ? foodCostPct(s)!.toFixed(1) + ' %' : '—' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500">Formule</span>
              <span class="text-slate-400 font-mono text-xs">{{ formuleCout(s) }}</span>
            </div>
          </div>
        </template>

        <!-- Actions -->
        <div v-if="!auth.isRestrictedOperateur" class="mt-auto flex gap-2 pt-1">
          <button @click="openEdit(s)"
            class="flex-1 py-1.5 rounded-lg border border-slate-300 text-sm text-slate-700 hover:border-navy hover:text-navy transition">
            Modifier
          </button>
          <button @click="confirmDelete(s)"
            class="flex-1 py-1.5 rounded-lg border border-red-200 text-sm text-red-500 hover:bg-red-50 transition">
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      @click.self="showModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-bold text-navy text-lg">{{ editing ? 'Modifier le supplément' : 'Nouveau supplément' }}</h2>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-700 text-xl leading-none">×</button>
        </div>

        <div class="px-6 py-5 space-y-4 overflow-y-auto">
          <!-- Nom -->
          <div>
            <label class="form-label">Nom du supplément *</label>
            <input v-model="form.nom" type="text" placeholder="Extra fromage, sauce piquante…" class="form-input" />
          </div>

          <!-- Prix de vente -->
          <div>
            <label class="form-label">Prix de vente TTC (DT) *</label>
            <input v-model.number="form.prix_vente" type="number" min="0" step="0.001" class="form-input" />
          </div>

          <!-- Ingrédient -->
          <div>
            <label class="form-label">Ingrédient lié *</label>
            <select v-model.number="form.ingredient_id" class="form-input">
              <option :value="null">— Choisir un ingrédient —</option>
              <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                {{ ing.nom }} ({{ ing.unite_mesure }}) — {{ ing.prix_achat_ht }} DT/{{ ing.unite_mesure }}
              </option>
            </select>
          </div>

          <!-- Quantité + unité -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="form-label">Quantité / portion *</label>
              <input v-model.number="form.quantite" type="number" min="0.001" step="0.001" class="form-input" />
            </div>
            <div>
              <label class="form-label">Unité recette</label>
              <input v-model="form.unite" placeholder="g, ml, pcs…" class="form-input" />
            </div>
          </div>

          <!-- Preview coût en temps réel -->
          <p v-if="previewFormule" :class="['text-xs font-mono', previewOk ? 'text-emerald-600' : 'text-amber-600']">
            {{ previewFormule }}
          </p>

          <!-- Actif -->
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.active" class="w-4 h-4 rounded" />
            <span>Actif (visible en caisse)</span>
          </label>
        </div>

        <div v-if="modalError" class="px-6 text-red-500 text-sm">{{ modalError }}</div>

        <div class="px-6 py-4 border-t border-slate-200 flex gap-3 justify-end">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Annuler</button>
          <button @click="save" :disabled="saving || !canSave"
            class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
            {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { supplementsApi, productsApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { getConversionFactor } from '@/utils/unitConversion'
import { formatPrice } from '@/utils/currency'

interface Ingredient { id: number; nom: string; unite_mesure: string; prix_achat_ht: number }
interface Supplement {
  id: number; nom: string; prix_vente: number; active: boolean
  ingredient_id: number; quantite: number; unite: string | null
  ingredient?: { id: number; nom: string; unite_mesure: string; prix_achat: number }
}

const auth        = useAuthStore()
const supplements = ref<Supplement[]>([])
const ingredients = ref<Ingredient[]>([])
const loading     = ref(false)
const showModal   = ref(false)
const editing     = ref<Supplement | null>(null)
const saving      = ref(false)
const modalError  = ref('')

const emptyForm = () => ({ nom: '', prix_vente: 0, ingredient_id: null as number | null, quantite: 0, unite: '', active: true })
const form = ref(emptyForm())

async function load() {
  loading.value = true
  try {
    const [suppRes, prodRes] = await Promise.all([
      supplementsApi.list(),
      productsApi.list({ per_page: 500, type: 'simple', actif: 1 }),
    ])
    supplements.value = suppRes.data
    ingredients.value = (prodRes.data.data as Ingredient[])
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = emptyForm()
  modalError.value = ''
  showModal.value = true
}

function openEdit(s: Supplement) {
  editing.value = s
  form.value = {
    nom:           s.nom,
    prix_vente:    s.prix_vente,
    ingredient_id: s.ingredient_id,
    quantite:      s.quantite,
    unite:         s.unite ?? '',
    active:        s.active,
  }
  modalError.value = ''
  showModal.value = true
}

const canSave = computed(() =>
  form.value.nom.trim() && form.value.prix_vente >= 0 && form.value.ingredient_id && form.value.quantite > 0
)

// Real-time cost preview
const previewFormule = computed((): string => {
  const ing = ingredients.value.find(i => i.id === form.value.ingredient_id)
  if (!ing || !form.value.quantite) return ''
  const uIng  = ing.unite_mesure ?? ''
  const uRecette = form.value.unite || uIng
  const factor = getConversionFactor(uIng, uRecette)
  if (factor === null) return `⚠ Unités incompatibles (${uIng} → ${uRecette})`
  const cout = ing.prix_achat_ht * form.value.quantite * factor
  return `${ing.prix_achat_ht} DT/${uIng} × ${form.value.quantite} ${uRecette} = ${cout.toFixed(3)} DT`
})

const previewOk = computed(() => !previewFormule.value.startsWith('⚠'))

async function save() {
  modalError.value = ''
  saving.value = true
  try {
    const payload = { ...form.value, unite: form.value.unite || null }
    if (editing.value) {
      await supplementsApi.update(editing.value.id, payload)
    } else {
      await supplementsApi.create(payload)
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    modalError.value = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.'
  } finally {
    saving.value = false
  }
}

async function confirmDelete(s: Supplement) {
  if (!confirm(`Supprimer le supplément « ${s.nom} » ?`)) return
  await supplementsApi.destroy(s.id)
  await load()
}

// Food cost helpers
function coutMatiere(s: Supplement): number {
  const ing = s.ingredient
  if (!ing) return 0
  const uIng    = ing.unite_mesure ?? ''
  const uRecette = s.unite ?? uIng
  const factor  = getConversionFactor(uIng, uRecette) ?? 0
  return Math.round(ing.prix_achat * s.quantite * factor * 1000) / 1000
}

function foodCostPct(s: Supplement): number | null {
  if (!s.prix_vente) return null
  return Math.round(coutMatiere(s) / s.prix_vente * 1000) / 10
}

function foodCostBadge(s: Supplement): string {
  const pct = foodCostPct(s)
  if (pct == null) return 'bg-slate-100 text-slate-500'
  if (pct < 35)   return 'bg-emerald-100 text-emerald-700'
  if (pct <= 45)  return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}

function formuleCout(s: Supplement): string {
  const ing = s.ingredient
  if (!ing) return ''
  const uIng    = ing.unite_mesure ?? ''
  const uRecette = s.unite ?? uIng
  const factor  = getConversionFactor(uIng, uRecette)
  if (factor === null) return `⚠ incompatible`
  const cout = ing.prix_achat * s.quantite * factor
  return `${ing.prix_achat}×${s.quantite}${uRecette}=${cout.toFixed(3)}DT`
}

function money(v: number | null | undefined): string {
  return formatPrice(v)
}

onMounted(load)
</script>
