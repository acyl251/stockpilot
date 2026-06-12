<template>
  <!-- Slide-over drawer -->
  <div class="fixed inset-0 z-50 flex justify-end bg-black/30" @click.self="$emit('close')">
    <div class="bg-white w-full max-w-md h-full shadow-2xl flex flex-col">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-navy">Nouveau mouvement</h2>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
      </div>

      <div class="flex-1 overflow-y-auto p-6 space-y-5">
        <div>
          <label class="form-label">Produit *</label>
          <select v-model="form.product_id" class="form-input" @change="onProductChange">
            <option value="">Sélectionner un produit…</option>
            <option v-for="p in products" :key="p.id" :value="p.id">
              {{ p.nom }} ({{ p.quantite }} {{ p.unite_mesure }})
            </option>
          </select>
        </div>

        <div>
          <label class="form-label">Type de mouvement *</label>
          <div class="grid grid-cols-3 gap-2">
            <button v-for="t in types" :key="t.value"
              @click="form.type_mouvement = t.value"
              :class="['py-2 rounded-lg text-sm font-medium border-2 transition-colors',
                form.type_mouvement === t.value ? t.activeClass : 'border-slate-200 text-slate-500 hover:border-slate-300']">
              {{ t.label }}
            </button>
          </div>
        </div>

        <div>
          <label class="form-label">Quantité *</label>
          <input v-model.number="form.quantite" type="number" min="0.001" step="0.001" class="form-input" />
        </div>

        <!-- Real-time impact preview -->
        <div v-if="selectedProduct && form.quantite > 0"
          class="bg-slate-50 rounded-xl p-4 text-sm space-y-1">
          <p class="font-semibold text-slate-600">Impact sur le stock</p>
          <p class="text-slate-500">
            Avant : <span class="font-semibold text-navy">{{ selectedProduct.quantite }}</span>
          </p>
          <p class="text-slate-500">
            Après : <span class="font-semibold"
              :class="previewAfter < selectedProduct.seuil_alerte ? 'text-amber-600' : 'text-emerald-600'">
              {{ previewAfter.toFixed(3) }}
            </span>
            {{ selectedProduct.unite_mesure }}
          </p>
          <p v-if="previewAfter <= 0" class="text-red-600 text-xs font-medium">⚠ Rupture de stock après ce mouvement</p>
          <p v-else-if="previewAfter < selectedProduct.seuil_alerte" class="text-amber-600 text-xs font-medium">⚠ Sous le seuil d'alerte ({{ selectedProduct.seuil_alerte }})</p>
        </div>

        <div>
          <label class="form-label">Note</label>
          <textarea v-model="form.note" class="form-input" rows="3"
            placeholder="Bon de commande, fournisseur…" />
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 flex gap-3 justify-end">
        <button @click="$emit('close')" class="px-4 py-2 text-sm text-slate-600">Annuler</button>
        <button @click="save" :disabled="saving || !form.product_id || form.quantite <= 0"
          class="btn-primary disabled:opacity-60">
          {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useMovementsStore } from '@/stores/movements'
import { useProductsStore } from '@/stores/products'

const emit = defineEmits(['close', 'saved'])
const movStore = useMovementsStore()
const prodStore = useProductsStore()

const products = computed(() => prodStore.products)
const saving   = ref(false)

const form = ref({
  product_id:     '',
  type_mouvement: 'entree',
  quantite:       0,
  note:           '',
})

const types = [
  { value: 'entree',     label: 'Entrée',   activeClass: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
  { value: 'sortie',     label: 'Sortie',   activeClass: 'border-red-500 bg-red-50 text-red-700' },
  { value: 'ajustement', label: 'Ajust.',   activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
]

const selectedProduct = computed(() =>
  products.value.find((p: any) => p.id === Number(form.value.product_id))
)

const previewAfter = computed(() => {
  const p = selectedProduct.value
  if (!p) return 0
  if (form.value.type_mouvement === 'entree')     return p.quantite + form.value.quantite
  if (form.value.type_mouvement === 'sortie')     return Math.max(0, p.quantite - form.value.quantite)
  if (form.value.type_mouvement === 'ajustement') return form.value.quantite
  return p.quantite
})

function onProductChange() {
  form.value.quantite = 0
}

async function save() {
  saving.value = true
  try {
    await movStore.createMovement({
      product_id:     Number(form.value.product_id),
      type_mouvement: form.value.type_mouvement,
      quantite:       form.value.quantite,
      note:           form.value.note || undefined,
    })
    emit('saved')
  } finally {
    saving.value = false
  }
}
</script>
