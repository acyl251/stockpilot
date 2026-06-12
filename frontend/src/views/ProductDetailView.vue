<template>
  <div v-if="product" class="space-y-6">
    <!-- Header -->
    <div class="flex items-start justify-between">
      <div>
        <RouterLink to="/products" class="text-sm text-slate-400 hover:text-gold">← Catalogue</RouterLink>
        <h1 class="text-2xl font-bold text-navy mt-1">{{ product.nom }}</h1>
        <p class="text-slate-500 text-sm font-mono">{{ product.reference }}</p>
      </div>
      <div class="flex flex-col items-end gap-2">
        <span :class="['px-3 py-1 rounded-full text-sm font-semibold',
          product.en_rupture ? 'bg-red-100 text-red-700' :
          product.en_alerte  ? 'bg-amber-100 text-amber-700' :
                                'bg-emerald-100 text-emerald-700']">
          {{ product.statut }}
        </span>
        <div class="flex gap-2">
          <button @click="showEdit = true"
            class="text-xs font-medium border border-slate-300 text-slate-600 hover:bg-slate-50 rounded-lg px-3 py-1.5">
            Modifier
          </button>
          <button @click="removeProduct"
            class="text-xs font-medium border border-red-200 text-red-600 hover:bg-red-50 rounded-lg px-3 py-1.5">
            Supprimer
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: details -->
      <div class="lg:col-span-2 space-y-4">
        <div class="card grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-slate-500">Catégorie</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.category?.nom ?? '—' }}</p>
          </div>
          <div>
            <p class="text-slate-500">Type</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.product_type?.nom ?? '—' }}</p>
          </div>
          <div>
            <p class="text-slate-500">Quantité en stock</p>
            <p class="font-bold text-2xl mt-0.5"
              :class="product.en_rupture ? 'text-red-600' : product.en_alerte ? 'text-amber-600' : 'text-navy'">
              {{ product.quantite }} <span class="text-sm font-normal">{{ product.unite_mesure }}</span>
            </p>
          </div>
          <div>
            <p class="text-slate-500">Seuil d'alerte</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.seuil_alerte }} {{ product.unite_mesure }}</p>
          </div>
          <div>
            <p class="text-slate-500">Prix achat HT</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.prix_achat_ht.toFixed(3) }} TND</p>
          </div>
          <div>
            <p class="text-slate-500">Prix achat TTC (TVA {{ product.taux_tva }}%)</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.prix_achat_ttc.toFixed(3) }} TND</p>
          </div>
          <div>
            <p class="text-slate-500">Prix vente HT</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.prix_vente_ht.toFixed(3) }} TND</p>
          </div>
          <div>
            <p class="text-slate-500">Prix vente TTC</p>
            <p class="font-semibold text-navy mt-0.5">{{ product.prix_vente_ttc.toFixed(3) }} TND</p>
          </div>
        </div>

        <!-- Dynamic attributes -->
        <div v-if="Object.keys(product.attributs ?? {}).length > 0" class="card border-2 border-gold/20 bg-gold/5">
          <h3 class="text-sm font-semibold text-gold mb-3">Attributs spécifiques</h3>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div v-for="(val, key) in product.attributs" :key="key">
              <p class="text-slate-500">{{ key }}</p>
              <p class="font-semibold text-navy mt-0.5">{{ val }}</p>
            </div>
          </div>
        </div>

        <!-- Recent movements -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-3">Derniers mouvements</h3>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="pb-2">Date</th>
                <th class="pb-2">Type</th>
                <th class="pb-2 text-right">Qté</th>
                <th class="pb-2 text-right">Après</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in recentMovements" :key="m.id"
                class="border-b border-slate-50 last:border-0">
                <td class="py-1.5 text-slate-400 text-xs">{{ formatDate(m.date_mouvement) }}</td>
                <td class="py-1.5">
                  <span :class="['px-1.5 py-0.5 rounded text-xs font-medium',
                    m.type_mouvement === 'entree'     ? 'bg-emerald-100 text-emerald-700' :
                    m.type_mouvement === 'sortie'     ? 'bg-red-100 text-red-700' :
                                                        'bg-blue-100 text-blue-700']">
                    {{ m.type_mouvement }}
                  </span>
                </td>
                <td class="py-1.5 text-right font-medium">{{ m.quantite }}</td>
                <td class="py-1.5 text-right text-slate-500">{{ m.quantite_apres }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right: actions -->
      <div class="space-y-4">
        <div class="card">
          <h3 class="font-semibold text-navy mb-3">Actions rapides</h3>
          <div class="space-y-2">
            <button @click="movType = 'entree'; showMov = true"
              class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-medium py-2.5 rounded-lg text-sm transition-colors">
              + Entrée de stock
            </button>
            <button @click="movType = 'sortie'; showMov = true"
              class="w-full bg-red-50 hover:bg-red-100 text-red-700 font-medium py-2.5 rounded-lg text-sm transition-colors">
              − Sortie de stock
            </button>
            <button @click="movType = 'ajustement'; showMov = true"
              class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-2.5 rounded-lg text-sm transition-colors">
              ⟳ Ajustement
            </button>
          </div>
        </div>

        <!-- Forecast (AI) -->
        <div v-if="auth.hasAI" class="card">
          <div class="flex items-center gap-2 mb-3">
            <h3 class="font-semibold text-navy">Prévision 30j</h3>
            <span class="text-xs text-gold">✨ IA</span>
          </div>
          <button @click="loadForecast" :disabled="forecastLoading"
            class="w-full text-sm text-gold border border-gold/30 rounded-lg py-2 hover:bg-gold/5 transition-colors">
            {{ forecastLoading ? 'Génération…' : 'Générer la prévision' }}
          </button>
          <div v-if="forecast.length > 0" class="mt-3 space-y-1 max-h-40 overflow-y-auto">
            <div v-for="f in forecast.slice(0, 10)" :key="f.date"
              class="flex justify-between text-xs text-slate-600">
              <span>{{ f.date }}</span>
              <span class="font-medium">{{ f.quantite_prevue }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Movement drawer -->
    <MovementDrawer v-if="showMov" :preset-product="product" :preset-type="movType"
      @close="showMov = false" @saved="onSaved" />

    <!-- Edit product modal -->
    <ProductFormModal v-if="showEdit" :product="product"
      @close="showEdit = false" @saved="onProductEdited" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useProductsStore } from '@/stores/products'
import { productsApi, dashboardApi, movementsApi } from '@/services/api'
import MovementDrawer from '@/components/MovementDrawer.vue'
import ProductFormModal from '@/components/ProductFormModal.vue'
import { format } from 'date-fns'
import { fr } from 'date-fns/locale'

const route   = useRoute()
const router  = useRouter()
const auth    = useAuthStore()
const store   = useProductsStore()
const product = ref<any>(null)
const recentMovements = ref<any[]>([])
const showMov = ref(false)
const showEdit = ref(false)
const movType = ref('entree')
const forecast = ref<any[]>([])
const forecastLoading = ref(false)

async function onProductEdited() {
  showEdit.value = false
  const { data } = await productsApi.get(Number(route.params.id))
  product.value = data
}

async function removeProduct() {
  if (!product.value || !confirm(`Supprimer le produit « ${product.value.nom} » ?`)) return
  await store.deleteProduct(product.value.id)
  router.push('/products')
}

function formatDate(d: string) {
  return format(new Date(d), 'dd/MM HH:mm', { locale: fr })
}

async function loadForecast() {
  forecastLoading.value = true
  try {
    const { data } = await dashboardApi.forecast(Number(route.params.id))
    forecast.value = data.forecast ?? []
  } finally {
    forecastLoading.value = false
  }
}

async function onSaved() {
  showMov.value = false
  const { data } = await productsApi.get(Number(route.params.id))
  product.value = data
}

onMounted(async () => {
  const [prodRes, movRes] = await Promise.all([
    productsApi.get(Number(route.params.id)),
    movementsApi.list({ product_id: route.params.id, per_page: 10 }),
    store.fetchCategories(),
    store.fetchTypes(),
  ])
  product.value         = prodRes.data
  recentMovements.value = movRes.data.data ?? []
})
</script>
