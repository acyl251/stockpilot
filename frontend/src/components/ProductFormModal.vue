<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-navy">{{ isEdit ? 'Modifier le produit' : 'Nouveau produit' }}</h2>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
      </div>

      <!-- Body -->
      <div class="overflow-y-auto flex-1 p-6 space-y-6">
        <!-- Section A: Fixed fields -->
        <div>
          <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Section A — Informations générales</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="form-label">Nom du produit *</label>
              <input v-model="form.nom" @blur="touched.nom = true" type="text"
                :class="['form-input', touched.nom && errors.nom ? 'input-error' : '']" />
              <p v-if="touched.nom && errors.nom" class="text-red-500 text-xs mt-1">{{ errors.nom }}</p>
            </div>
            <div>
              <label class="form-label">Référence</label>
              <input v-model="form.reference" @input="onReferenceInput" type="text"
                :class="['form-input',
                  referenceAvailable === false ? 'input-error' :
                  (referenceAvailable === true && form.reference) ? 'border-emerald-400' : '']" />
              <p v-if="referenceChecking" class="text-slate-400 text-xs mt-1">Vérification…</p>
              <p v-else-if="referenceAvailable === false" class="text-red-500 text-xs mt-1">Cette référence est déjà utilisée.</p>
              <p v-else-if="referenceAvailable === true && form.reference" class="text-emerald-600 text-xs mt-1">✓ Référence disponible</p>
              <p v-else-if="!form.reference" class="text-slate-400 text-xs mt-1">Laissez vide pour générer automatiquement.</p>
            </div>
            <div>
              <label class="form-label">Unité de mesure</label>
              <select v-if="!customUnit" v-model="form.unite_mesure" class="form-input" @change="onUnitChange">
                <optgroup v-for="g in UNIT_GROUPS" :key="g.label" :label="g.label">
                  <option v-for="u in g.units" :key="u[0]" :value="u[0]">{{ u[1] }}</option>
                </optgroup>
                <option value="__custom__">Autre (préciser)…</option>
              </select>
              <div v-else class="flex gap-2">
                <input v-model="form.unite_mesure" type="text" class="form-input" placeholder="Préciser l'unité…" />
                <button type="button" @click="customUnit = false; form.unite_mesure = 'pcs'"
                  title="Revenir à la liste"
                  class="text-slate-400 hover:text-navy text-sm px-2 border border-slate-300 rounded-lg">↩</button>
              </div>
            </div>
            <div>
              <label class="form-label">Catégorie</label>
              <select v-model="form.category_id" class="form-input">
                <option value="">— Aucune —</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Type de produit</label>
              <select v-model="form.product_type_id" class="form-input" @change="onTypeChange">
                <option value="">— Aucun —</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.nom }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Seuil d'alerte *</label>
              <input v-model.number="form.seuil_alerte" @blur="touched.seuil_alerte = true" type="number" min="0" step="0.001"
                :class="['form-input', touched.seuil_alerte && errors.seuil_alerte ? 'input-error' : '']" />
              <p v-if="touched.seuil_alerte && errors.seuil_alerte" class="text-red-500 text-xs mt-1">{{ errors.seuil_alerte }}</p>
            </div>
            <div>
              <label class="form-label">Prix achat HT (TND) *</label>
              <input v-model.number="form.prix_achat_ht" @blur="touched.prix_achat_ht = true" type="number" min="0" step="0.001"
                :class="['form-input', touched.prix_achat_ht && errors.prix_achat_ht ? 'input-error' : '']" />
              <p v-if="touched.prix_achat_ht && errors.prix_achat_ht" class="text-red-500 text-xs mt-1">{{ errors.prix_achat_ht }}</p>
            </div>
            <div>
              <label class="form-label">Taux TVA</label>
              <select v-model.number="form.taux_tva" class="form-input">
                <option :value="0">0 % (exonéré)</option>
                <option :value="7">7 % (taux réduit)</option>
                <option :value="19">19 % (taux normal)</option>
              </select>
            </div>
            <div>
              <label class="form-label">Prix vente HT (TND) *</label>
              <input v-model.number="form.prix_vente_ht" @blur="touched.prix_vente_ht = true" type="number" min="0" step="0.001"
                :class="['form-input',
                  touched.prix_vente_ht && (errors.prix_vente_ht || marginNegative) ? 'input-error' :
                  marginPositive ? 'border-emerald-400' : '']" />
              <p v-if="touched.prix_vente_ht && errors.prix_vente_ht" class="text-red-500 text-xs mt-1">{{ errors.prix_vente_ht }}</p>
            </div>

            <!-- Live margin indicator — the app "thinks" for the user -->
            <div v-if="showMargin" class="col-span-2">
              <div :class="['rounded-lg px-3 py-2 text-sm flex items-center justify-between border',
                marginNegative ? 'bg-red-50 text-red-700 border-red-200' :
                marginZero     ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                 'bg-emerald-50 text-emerald-700 border-emerald-200']">
                <span class="font-medium">
                  <template v-if="marginNegative">⚠ Marge négative — vente à perte</template>
                  <template v-else-if="marginZero">⚠ Marge nulle</template>
                  <template v-else>Marge bénéficiaire</template>
                </span>
                <span class="font-semibold">{{ marginValue.toFixed(3) }} TND ({{ marginPct.toFixed(1) }} %)</span>
              </div>
            </div>

            <div class="col-span-2">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" rows="2" class="form-input" placeholder="Description du produit…"></textarea>
            </div>
          </div>
        </div>

        <!-- Section B: Dynamic type attributes -->
        <div v-if="selectedTypeAttrs.length > 0"
          class="border-2 border-gold/30 rounded-xl p-4 bg-gold/5">
          <h3 class="text-sm font-semibold text-gold uppercase tracking-wider mb-3">
            Section B — Attributs {{ selectedTypeName }}
          </h3>
          <div class="grid grid-cols-2 gap-4">
            <div v-for="attr in selectedTypeAttrs" :key="attr.nom"
              :class="attr.type_donnee === 'boolean' ? 'flex items-center gap-2' : ''">
              <label class="form-label">
                {{ attr.label }}
                <span v-if="attr.obligatoire" class="text-red-500">*</span>
              </label>
              <!-- text / number / date -->
              <input v-if="['text','number','date'].includes(attr.type_donnee)"
                v-model="form.attributs[attr.nom]"
                :type="attr.type_donnee"
                :required="attr.obligatoire"
                :placeholder="attr.valeur_defaut"
                class="form-input" />
              <!-- select -->
              <select v-else-if="attr.type_donnee === 'select'"
                v-model="form.attributs[attr.nom]"
                :required="attr.obligatoire"
                class="form-input">
                <option v-for="opt in attr.options_select" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <!-- boolean -->
              <input v-else-if="attr.type_donnee === 'boolean'"
                v-model="form.attributs[attr.nom]" type="checkbox" class="w-4 h-4" />
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-slate-200">
        <p v-if="marginNegative" class="text-xs text-red-600">⚠ Marge négative : vérifiez vos prix.</p>
        <span v-else />
        <div class="flex gap-3">
          <button @click="$emit('close')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Annuler</button>
          <button @click="save" :disabled="saving || referenceChecking || saveDisabled" class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
            {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useProductsStore } from '@/stores/products'
import { productsApi } from '@/services/api'

const props = defineProps<{ product?: any }>()
const emit  = defineEmits(['close', 'saved'])
const store = useProductsStore()

const categories = computed(() => store.categories)
const types      = computed(() => store.types)
const saving     = ref(false)
const isEdit     = computed(() => !!props.product)

// Normalized unit list (value, label) grouped by nature — keeps stock data consistent.
const UNIT_GROUPS: { label: string; units: [string, string][] }[] = [
  { label: 'Conditionnement', units: [
    ['pcs', 'Pièce (pcs)'], ['unité', 'Unité'], ['boite', 'Boîte'], ['paquet', 'Paquet'],
    ['pack', 'Pack'], ['lot', 'Lot'], ['carton', 'Carton'], ['palette', 'Palette'],
    ['jeu', 'Jeu'], ['rouleau', 'Rouleau'], ['sac', 'Sac'], ['pot', 'Pot'], ['tube', 'Tube'],
  ]},
  { label: 'Poids',    units: [['kg', 'Kilogramme (kg)'], ['g', 'Gramme (g)'], ['t', 'Tonne (t)']] },
  { label: 'Volume',   units: [['L', 'Litre (L)'], ['ml', 'Millilitre (ml)'], ['cl', 'Centilitre (cl)']] },
  { label: 'Longueur', units: [['m', 'Mètre (m)'], ['cm', 'Centimètre (cm)']] },
]
const KNOWN_UNITS = UNIT_GROUPS.flatMap(g => g.units.map(u => u[0]))

const form = ref<any>(props.product
  ? {
      nom:             props.product.nom ?? '',
      reference:       props.product.reference ?? '',
      description:     props.product.description ?? '',
      unite_mesure:    props.product.unite_mesure ?? 'unité',
      category_id:     props.product.category?.id ?? '',
      product_type_id: props.product.product_type?.id ?? '',
      seuil_alerte:    Number(props.product.seuil_alerte ?? 0),
      prix_achat_ht:   Number(props.product.prix_achat_ht ?? 0),
      taux_tva:        Number(props.product.taux_tva ?? 19),
      prix_vente_ht:   Number(props.product.prix_vente_ht ?? 0),
      attributs:       { ...(props.product.attributs ?? {}) },
    }
  : {
      nom: '', reference: '', description: '', unite_mesure: 'unité',
      category_id: '', product_type_id: '',
      seuil_alerte: 0, prix_achat_ht: 0, taux_tva: 19, prix_vente_ht: 0,
      attributs: {},
    })

const selectedTypeAttrs = computed(() => {
  if (! form.value.product_type_id) return []
  const t = types.value.find((x: any) => x.id === Number(form.value.product_type_id))
  return t?.attributes ?? []
})

const selectedTypeName = computed(() => {
  const t = types.value.find((x: any) => x.id === Number(form.value.product_type_id))
  return t?.nom ?? ''
})

// Unit kept in free-text mode when the existing value isn't a standard unit.
const customUnit = ref(!!form.value.unite_mesure && !KNOWN_UNITS.includes(form.value.unite_mesure))

function onUnitChange() {
  if (form.value.unite_mesure === '__custom__') {
    customUnit.value = true
    form.value.unite_mesure = ''
  }
}

function onTypeChange() {
  form.value.attributs = {}
}

// ── Real-time validation ──────────────────────────────────────────────────────
const touched = reactive<Record<string, boolean>>({})

const errors = computed<Record<string, string>>(() => {
  const e: Record<string, string> = {}
  if (!String(form.value.nom).trim())                              e.nom          = 'Le nom est requis.'
  if (form.value.prix_achat_ht == null || form.value.prix_achat_ht < 0) e.prix_achat_ht = 'Prix d\'achat invalide.'
  if (form.value.prix_vente_ht == null || form.value.prix_vente_ht < 0) e.prix_vente_ht = 'Prix de vente invalide.'
  if (form.value.seuil_alerte == null || form.value.seuil_alerte < 0)   e.seuil_alerte  = 'Seuil invalide.'
  return e
})

// Reference duplication blocks saving; a negative margin only warns.
const saveDisabled = computed(() =>
  Object.keys(errors.value).length > 0 || referenceAvailable.value === false
)

// ── Live margin ───────────────────────────────────────────────────────────────
const marginValue = computed(() => Number(form.value.prix_vente_ht || 0) - Number(form.value.prix_achat_ht || 0))
const marginPct   = computed(() => {
  const a = Number(form.value.prix_achat_ht || 0)
  return a > 0 ? (marginValue.value / a) * 100 : 0
})
const showMargin     = computed(() => Number(form.value.prix_achat_ht) > 0 || Number(form.value.prix_vente_ht) > 0)
const marginNegative = computed(() => marginValue.value < 0)
const marginZero     = computed(() => marginValue.value === 0 && Number(form.value.prix_vente_ht) > 0)
const marginPositive = computed(() => marginValue.value > 0)

// ── Reference uniqueness (debounced server check) ────────────────────────────
const referenceChecking  = ref(false)
const referenceAvailable = ref<boolean | null>(null)
let refTimer: ReturnType<typeof setTimeout>

function onReferenceInput() {
  clearTimeout(refTimer)
  const r = String(form.value.reference || '').trim()
  if (!r) { referenceAvailable.value = null; referenceChecking.value = false; return }
  referenceChecking.value = true
  refTimer = setTimeout(checkReference, 400)
}

async function checkReference() {
  const reference = String(form.value.reference || '').trim()
  if (!reference) { referenceAvailable.value = null; referenceChecking.value = false; return }
  try {
    const { data } = await productsApi.checkReference(reference, props.product?.id)
    referenceAvailable.value = data.available
  } catch {
    referenceAvailable.value = null
  } finally {
    referenceChecking.value = false
  }
}

async function save() {
  // Surface every error on submit attempt.
  touched.nom = touched.prix_achat_ht = touched.prix_vente_ht = touched.seuil_alerte = true
  if (saveDisabled.value || referenceChecking.value) return

  saving.value = true
  try {
    const payload = {
      ...form.value,
      category_id:     form.value.category_id     || null,
      product_type_id: form.value.product_type_id || null,
    }
    if (isEdit.value) {
      await store.updateProduct(props.product.id, payload)
    } else {
      await store.createProduct(payload)
    }
    emit('saved')
  } finally {
    saving.value = false
  }
}
</script>

<style>
.form-label { @apply block text-sm font-medium text-slate-700 mb-1; }
.form-input { @apply w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold; }
.input-error { @apply border-red-400 ring-1 ring-red-300 focus:ring-red-300; }
</style>
