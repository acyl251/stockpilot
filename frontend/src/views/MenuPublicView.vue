<template>
  <!-- Full-page public menu — no sidebar, no auth required -->
  <div class="min-h-screen bg-slate-50">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="text-center text-slate-400">
        <div class="w-10 h-10 border-4 border-slate-200 border-t-amber-500 rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-sm">Chargement du menu…</p>
      </div>
    </div>

    <!-- Not found -->
    <div v-else-if="notFound" class="flex items-center justify-center min-h-screen p-6">
      <div class="text-center">
        <p class="text-5xl mb-4">🍽️</p>
        <h1 class="text-xl font-bold text-slate-700 mb-2">Restaurant introuvable</h1>
        <p class="text-slate-500 text-sm">Ce lien ne correspond à aucun restaurant actif.</p>
      </div>
    </div>

    <!-- Menu -->
    <template v-else>
      <!-- Header -->
      <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="max-w-2xl mx-auto px-4 py-4">
          <h1 class="text-xl font-bold text-slate-800 text-center">{{ restaurant.nom }}</h1>
          <p v-if="restaurant.adresse" class="text-xs text-slate-400 text-center mt-0.5">{{ restaurant.adresse }}</p>
          <p v-if="restaurant.telephone" class="text-xs text-slate-400 text-center">📞 {{ restaurant.telephone }}</p>
        </div>
      </header>

      <!-- Category filter pills -->
      <div v-if="categories.length > 1" class="bg-white border-b border-slate-100 sticky top-[72px] z-10">
        <div class="max-w-2xl mx-auto px-4 py-3">
          <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
            <button @click="activeCategory = null"
              :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                activeCategory === null
                  ? 'bg-slate-800 text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200']">
              Tout
            </button>
            <button v-for="cat in categories" :key="cat.nom" @click="activeCategory = cat.nom"
              :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                activeCategory === cat.nom
                  ? 'text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
              :style="activeCategory === cat.nom ? { backgroundColor: cat.couleur } : {}">
              {{ cat.nom }}
            </button>
          </div>
        </div>
      </div>

      <!-- Content -->
      <main class="max-w-2xl mx-auto px-4 py-6 space-y-8 pb-16">
        <template v-for="cat in filteredCategories" :key="cat.nom">
          <!-- Category header -->
          <div>
            <div class="flex items-center gap-3 mb-4">
              <div class="w-1 h-6 rounded-full" :style="{ backgroundColor: cat.couleur }"></div>
              <h2 class="text-lg font-bold text-slate-800">{{ cat.nom }}</h2>
              <span class="text-xs text-slate-400">{{ cat.plats.length }} plat{{ cat.plats.length > 1 ? 's' : '' }}</span>
            </div>

            <!-- Plats grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-for="plat in cat.plats" :key="plat.id"
                class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
                <!-- Placeholder image area -->
                <div class="h-32 flex items-center justify-center text-5xl"
                  :style="{ backgroundColor: cat.couleur + '20' }">
                  🍽️
                </div>
                <div class="p-4">
                  <!-- Category badge -->
                  <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full mb-2"
                    :style="{ backgroundColor: cat.couleur + '20', color: cat.couleur }">
                    {{ cat.nom }}
                  </span>
                  <h3 class="font-semibold text-slate-800 leading-tight">{{ plat.nom }}</h3>
                  <p v-if="plat.description" class="text-xs text-slate-500 mt-1 leading-relaxed line-clamp-2">
                    {{ plat.description }}
                  </p>
                  <div class="flex items-center justify-between mt-3">
                    <span class="text-lg font-bold text-slate-900">{{ money(plat.prix_vente_ttc) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Empty state -->
        <div v-if="filteredCategories.length === 0 && !loading"
          class="text-center py-16 text-slate-400">
          <p class="text-4xl mb-3">🍽️</p>
          <p>Aucun plat disponible pour le moment.</p>
        </div>
      </main>

      <!-- Footer -->
      <footer class="fixed bottom-0 inset-x-0 bg-white border-t border-slate-100 py-2">
        <p class="text-center text-xs text-slate-400">
          Propulsé par <span class="font-semibold text-slate-600">StockPilot</span>
        </p>
      </footer>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { publicApi } from '@/services/api'

interface Plat {
  id: number
  nom: string
  description: string | null
  prix_vente_ttc: number
  unite: string
  categorie: { id: number; nom: string; couleur: string } | null
}
interface Category { nom: string; couleur: string; plats: Plat[] }

const route = useRoute()
const slug  = route.params.slug as string

const loading      = ref(true)
const notFound     = ref(false)
const restaurant   = ref<any>(null)
const categories   = ref<Category[]>([])
const activeCategory = ref<string | null>(null)

const filteredCategories = computed(() =>
  activeCategory.value
    ? categories.value.filter(c => c.nom === activeCategory.value)
    : categories.value
)

function money(v: number) {
  return Number(v ?? 0).toFixed(3) + ' DT'
}

onMounted(async () => {
  try {
    const { data } = await publicApi.menu(slug)
    restaurant.value = data.restaurant
    categories.value = data.categories ?? []
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
