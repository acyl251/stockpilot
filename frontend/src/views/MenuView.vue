<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-navy">Menu</h1>
      <button @click="openCreate" class="btn-primary flex items-center gap-2">
        + Nouveau plat
      </button>
    </div>

    <!-- Search bar -->
    <input
      v-model="search"
      type="text"
      placeholder="Rechercher un plat…"
      class="input w-full max-w-sm"
    />

    <!-- Category filter pills -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="pill in pills" :key="pill.id ?? 'all'"
        @click="activeCategory = pill.id"
        :class="['px-3 py-1.5 rounded-full text-sm font-medium transition',
          activeCategory === pill.id
            ? 'bg-navy text-white shadow-sm'
            : 'bg-white border border-slate-200 text-slate-600 hover:border-gold hover:text-gold']">
        {{ pill.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-16 text-slate-400">Chargement du menu…</div>

    <!-- Empty -->
    <div v-else-if="filteredProducts.length === 0"
      class="text-center py-16 text-slate-400 border-2 border-dashed border-slate-200 rounded-xl">
      <p class="text-lg font-medium">Aucun plat composé dans cette catégorie.</p>
      <button @click="openCreate" class="mt-3 text-gold hover:underline text-sm">
        + Créer le premier plat
      </button>
    </div>

    <!-- Cards grid -->
    <div v-else class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr))">
      <div v-for="p in filteredProducts" :key="p.id"
        class="card flex flex-col gap-3 hover:shadow-md transition-shadow">

        <!-- Top: name + price -->
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-bold text-navy text-base leading-snug truncate">{{ p.nom }}</p>
            <span v-if="p.category"
              class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium text-white"
              :style="{ backgroundColor: p.category.couleur ?? '#94a3b8' }">
              {{ p.category.nom }}
            </span>
          </div>
          <p class="text-gold font-bold text-sm shrink-0 mt-0.5">{{ money(p.prix_vente_ttc) }}</p>
        </div>

        <!-- Ingredient count badge -->
        <div>
          <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold',
            nbIngredients(p.id) > 0
              ? 'bg-emerald-100 text-emerald-700'
              : 'bg-amber-100 text-amber-700']">
            {{ nbIngredients(p.id) > 0
                ? `${nbIngredients(p.id)} ingrédient${nbIngredients(p.id) > 1 ? 's' : ''}`
                : 'Aucune recette' }}
          </span>
        </div>

        <!-- First 3 ingredients -->
        <ul v-if="nbIngredients(p.id) > 0" class="text-xs text-slate-500 space-y-0.5">
          <li v-for="ing in (compositionMap[p.id] ?? []).slice(0, 3)" :key="ing.id"
            class="flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0" />
            <span class="truncate">{{ ing.composant?.nom }}</span>
            <span class="text-slate-400 shrink-0">× {{ ing.quantite }} {{ ing.unite }}</span>
          </li>
          <li v-if="nbIngredients(p.id) > 3" class="text-slate-400 pl-3">
            + {{ nbIngredients(p.id) - 3 }} autre(s)…
          </li>
        </ul>

        <!-- ── Food cost section ──────────────────────────────────────────── -->
        <template v-if="nbIngredients(p.id) > 0">
          <div class="border-t border-slate-100 pt-3 space-y-1.5">

            <!-- Coût incomplet -->
            <div v-if="!foodCostMap[p.id]?.cout_complet"
              class="flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 rounded-lg px-2.5 py-1.5">
              <span>⚠</span>
              <span>Coût incomplet — renseignez le prix d'achat de tous les ingrédients.</span>
            </div>

            <template v-else>
              <!-- Coût matière -->
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Coût matière</span>
                <span class="font-semibold text-slate-700">{{ money(foodCostMap[p.id]?.cout_matiere) }}</span>
              </div>

              <!-- Food cost % avec badge coloré -->
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Food cost</span>
                <span :class="['px-2 py-0.5 rounded-full font-bold text-xs', foodCostBadge(p.id)]">
                  {{ foodCostMap[p.id]?.food_cost_percent != null
                      ? (foodCostMap[p.id].food_cost_percent as number).toFixed(1) + ' %'
                      : '—' }}
                </span>
              </div>

              <!-- Marge matière -->
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">Marge matière</span>
                <span :class="['font-semibold',
                  (foodCostMap[p.id]?.marge_matiere ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600']">
                  {{ money(foodCostMap[p.id]?.marge_matiere) }}
                </span>
              </div>
            </template>
          </div>
        </template>

        <!-- Action button -->
        <div class="mt-auto pt-1">
          <button @click="openEdit(p)"
            :class="['w-full py-2 rounded-lg text-sm font-medium transition',
              nbIngredients(p.id) > 0
                ? 'bg-navy text-white hover:bg-navy/90'
                : 'bg-amber-500 text-white hover:bg-amber-600']">
            {{ nbIngredients(p.id) > 0 ? 'Modifier la recette' : 'Configurer la recette' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ProductFormModal -->
    <ProductFormModal
      v-if="showModal"
      :key="editing?.id ?? 'new'"
      :product="editing"
      :default-type="editing ? undefined : 'compose'"
      @close="showModal = false"
      @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { productsApi, compositionApi } from '@/services/api'
import { useProductsStore } from '@/stores/products'
import ProductFormModal from '@/components/ProductFormModal.vue'

interface Category { id: number; nom: string; couleur: string }
interface Product {
  id: number; nom: string; prix_vente_ttc: number; type: string
  category?: Category
}
interface CompositionLine {
  id: number; quantite: number; unite: string
  composant?: { id: number; nom: string; unite_mesure: string; prix_achat_ht: number; prix_achat_ttc: number }
}
interface FoodCost {
  cout_matiere: number
  food_cost_percent: number | null
  marge_matiere: number
  cout_complet: boolean
}

const store   = useProductsStore()
const loading = ref(false)
const products      = ref<Product[]>([])
const compositionMap = ref<Record<number, CompositionLine[]>>({})
const foodCostMap    = ref<Record<number, FoodCost>>({})

const showModal = ref(false)
const editing   = ref<any>(undefined)

const activeCategory = ref<number | null>(null)
const search         = ref('')

const pills = computed(() => {
  const seen = new Map<number, Category>()
  for (const p of products.value) {
    if (p.category) seen.set(p.category.id, p.category)
  }
  return [
    { id: null, label: 'Tous' },
    ...[...seen.values()].map(c => ({ id: c.id, label: c.nom })),
  ]
})

const filteredProducts = computed(() =>
  products.value.filter(p => {
    const matchSearch   = !search.value || p.nom.toLowerCase().includes(search.value.toLowerCase())
    const matchCategory = activeCategory.value === null || p.category?.id === activeCategory.value
    return matchSearch && matchCategory
  })
)

function nbIngredients(productId: number): number {
  return compositionMap.value[productId]?.length ?? 0
}

function foodCostBadge(productId: number): string {
  const pct = foodCostMap.value[productId]?.food_cost_percent
  if (pct == null) return 'bg-slate-100 text-slate-500'
  if (pct < 35)   return 'bg-emerald-100 text-emerald-700'
  if (pct <= 45)  return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}

async function loadMenu() {
  loading.value = true
  try {
    const { data } = await productsApi.list({ per_page: 500, actif: 1 })
    const composed = (data.data as Product[]).filter(p => p.type === 'compose')
    products.value = composed

    // Batch-load compositions + food cost in parallel
    const results = await Promise.all(
      composed.map(p =>
        compositionApi.list(p.id)
          .then(r => ({
            id:        p.id,
            lines:     (r.data.lignes ?? []) as CompositionLine[],
            foodCost:  {
              cout_matiere:      r.data.cout_matiere      ?? 0,
              food_cost_percent: r.data.food_cost_percent ?? null,
              marge_matiere:     r.data.marge_matiere     ?? 0,
              cout_complet:      r.data.cout_complet      ?? false,
            } as FoodCost,
          }))
          .catch(() => ({
            id:       p.id,
            lines:    [] as CompositionLine[],
            foodCost: { cout_matiere: 0, food_cost_percent: null, marge_matiere: 0, cout_complet: false },
          }))
      )
    )

    const cMap: Record<number, CompositionLine[]> = {}
    const fMap: Record<number, FoodCost>          = {}
    for (const r of results) { cMap[r.id] = r.lines; fMap[r.id] = r.foodCost }
    compositionMap.value = cMap
    foodCostMap.value    = fMap
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = undefined
  showModal.value = true
}

function openEdit(p: Product) {
  editing.value = p
  showModal.value = true
}

async function onSaved() {
  showModal.value = false
  await Promise.all([store.fetchCategories(), loadMenu()])
}

function money(v: number | string | null | undefined): string {
  return Number(v ?? 0).toFixed(3) + ' TND'
}

onMounted(async () => {
  await Promise.all([store.fetchCategories(), store.fetchTypes(), loadMenu()])
})
</script>
