<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div class="flex gap-3 flex-wrap">
        <input v-model="search" @input="debouncedFetch"
          placeholder="Rechercher un produit…"
          class="border border-slate-300 rounded-lg px-4 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-gold" />
        <select v-model="filterStatut" @change="fetchProducts"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les statuts</option>
          <option value="alerte">En alerte</option>
          <option value="rupture">En rupture</option>
        </select>
        <select v-model="filterCategory" @change="fetchProducts"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Toutes catégories</option>
          <option v-for="c in store.categories" :key="c.id" :value="c.id">{{ c.nom }}</option>
        </select>
      </div>
      <button v-if="!auth.isRestrictedOperateur" @click="openCreate"
        class="btn-primary flex items-center gap-2">
        + Nouveau produit
      </button>
    </div>

    <!-- Bandeau info opérateur multi-PDV -->
    <div v-if="auth.isRestrictedOperateur"
      class="flex items-center gap-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl px-4 py-3 text-sm">
      <span class="text-lg">ℹ️</span>
      <span>La gestion du catalogue est réservée à l'administrateur.</span>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Produit</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Référence</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Catégorie</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Quantité</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Prix achat HT</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Statut</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.loading">
            <td colspan="7" class="text-center py-10 text-slate-400">Chargement…</td>
          </tr>
          <tr v-else-if="store.products.length === 0">
            <td colspan="7" class="text-center py-10 text-slate-400">Aucun produit trouvé.</td>
          </tr>
          <tr v-for="p in store.products" :key="p.id"
            class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 font-medium text-navy">{{ p.nom }}</td>
            <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ p.reference || '—' }}</td>
            <td class="px-4 py-3">
              <span v-if="p.category" class="px-2 py-0.5 rounded-full text-xs font-medium text-white"
                :style="{ backgroundColor: p.category.couleur }">
                {{ p.category.nom }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold"
              :class="p.en_rupture ? 'text-red-600' : p.en_alerte ? 'text-amber-600' : 'text-slate-800'">
              {{ p.quantite }} {{ p.unite_mesure }}
            </td>
            <td class="px-4 py-3 text-right text-slate-600">{{ p.prix_achat_ht.toFixed(3) }} TND</td>
            <td class="px-4 py-3 text-center">
              <div class="flex flex-col items-center gap-1">
                <span :class="['badge-stock px-2 py-0.5 rounded-full text-xs font-semibold',
                  p.en_rupture ? 'bg-red-100 text-red-700' :
                  p.en_alerte  ? 'bg-amber-100 text-amber-700' :
                  p.type === 'compose' ? 'bg-purple-100 text-purple-700' :
                                         'bg-emerald-100 text-emerald-700']">
                  {{ p.statut }}
                </span>
                <span v-if="(p.attributs as any)?.conservation" class="text-xs text-slate-400">
                  {{ ({ ambiant: '🌡 Ambiant', refrigere: '❄ Réfrigéré', congele: '🧊 Congelé' } as Record<string,string>)[(p.attributs as any).conservation] }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-3">
                <RouterLink :to="`/products/${p.id}`" class="text-gold hover:underline text-xs font-medium">
                  Voir
                </RouterLink>
                <template v-if="!auth.isRestrictedOperateur">
                  <button @click="openEdit(p)" class="text-slate-500 hover:text-navy text-xs font-medium">
                    Modifier
                  </button>
                  <button @click="removeProduct(p)" class="text-red-500 hover:text-red-700 text-xs font-medium">
                    Supprimer
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
        <span>{{ store.pagination.total }} produits</span>
        <div class="flex gap-2">
          <button :disabled="page === 1" @click="page--; fetchProducts()"
            class="px-3 py-1 rounded border disabled:opacity-40">←</button>
          <span>{{ page }} / {{ store.pagination.last_page }}</span>
          <button :disabled="page >= store.pagination.last_page" @click="page++; fetchProducts()"
            class="px-3 py-1 rounded border disabled:opacity-40">→</button>
        </div>
      </div>
    </div>

    <!-- Product form modal (create or edit) -->
    <ProductFormModal v-if="showForm" :key="editing?.id ?? 'new'" :product="editing"
      @close="closeForm" @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useProductsStore } from '@/stores/products'
import { useAuthStore } from '@/stores/auth'
import ProductFormModal from '@/components/ProductFormModal.vue'

const store         = useProductsStore()
const auth          = useAuthStore()
const search        = ref('')
const filterStatut  = ref('')
const filterCategory = ref('')
const page          = ref(1)
const showForm      = ref(false)
const editing       = ref<any | undefined>(undefined)

let debounceTimer: ReturnType<typeof setTimeout>

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchProducts, 400)
}

function fetchProducts() {
  store.fetchProducts({
    search:      search.value || undefined,
    statut:      filterStatut.value || undefined,
    category_id: filterCategory.value || undefined,
    page:        page.value,
  })
}

function openCreate() {
  editing.value = undefined
  showForm.value = true
}

function openEdit(p: any) {
  editing.value = p
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editing.value = undefined
}

async function removeProduct(p: any) {
  if (!confirm(`Supprimer le produit « ${p.nom} » ?`)) return
  await store.deleteProduct(p.id)
  fetchProducts()
}

function onSaved() {
  closeForm()
  fetchProducts()
}

onMounted(async () => {
  await Promise.all([
    store.fetchCategories(),
    store.fetchTypes(),
    fetchProducts(),
  ])
})
</script>
