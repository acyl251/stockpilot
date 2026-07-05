<template>
  <Teleport to="body">
    <Transition name="search-modal">
      <div v-if="open"
        class="fixed inset-0 z-[200] flex items-start justify-center pt-[10vh] px-4"
        @mousedown.self="close">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @mousedown.self="close" />

        <!-- Panel -->
        <div class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[75vh]">

          <!-- Input -->
          <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-200">
            <svg class="w-5 h-5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
            </svg>
            <input
              id="global-search-input"
              ref="inputRef"
              v-model="query"
              type="text"
              autocomplete="off"
              placeholder="Rechercher produits, clients, ventes, fournisseurs…"
              class="flex-1 text-sm text-slate-800 placeholder-slate-400 focus:outline-none bg-transparent"
              @keydown="handleInputKeydown"
            />
            <kbd class="hidden sm:flex items-center text-xs text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 leading-none">Esc</kbd>
            <button @click="close" class="text-slate-400 hover:text-slate-600 text-xl leading-none ml-1">×</button>
          </div>

          <!-- Results -->
          <div class="overflow-y-auto flex-1">

            <!-- Loading skeleton -->
            <div v-if="loading" class="p-4 space-y-3">
              <div v-for="i in 3" :key="i" class="space-y-2">
                <div class="h-2.5 w-20 bg-slate-100 rounded animate-pulse"/>
                <div class="h-9 bg-slate-50 rounded-lg animate-pulse"/>
                <div class="h-9 bg-slate-50 rounded-lg animate-pulse"/>
              </div>
            </div>

            <!-- Hint: too short -->
            <div v-else-if="query.length > 0 && query.length < 2"
              class="px-4 py-8 text-center text-sm text-slate-400">
              Tapez au moins 2 caractères…
            </div>

            <!-- No results -->
            <div v-else-if="query.length >= 2 && !loading && hasNoResults"
              class="px-4 py-8 text-center text-sm text-slate-400">
              Aucun résultat pour <span class="font-medium text-slate-600">« {{ query }} »</span>
            </div>

            <!-- Empty state -->
            <div v-else-if="query.length === 0"
              class="px-4 py-8 text-center text-sm text-slate-400 space-y-1">
              <p>Recherchez dans tout votre espace de travail</p>
              <p class="text-xs">Produits · Clients · Ventes · Fournisseurs</p>
            </div>

            <!-- Results -->
            <template v-else>

              <!-- Produits -->
              <div v-if="results.produits.length" class="py-1">
                <p class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider">📦 Produits</p>
                <button
                  v-for="(item, i) in results.produits" :key="`p-${item.id}`"
                  class="w-full text-left px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors"
                  :class="groupOffsets.produits + i === focusedIndex ? 'bg-gold/10' : ''"
                  @mousedown.prevent="navigate(item)"
                >
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-navy font-medium truncate" v-html="highlight(item.nom)"/>
                    <p v-if="item.reference" class="text-xs text-slate-400 truncate" v-html="highlight(item.reference)"/>
                  </div>
                  <span class="text-xs font-mono text-slate-500 flex-shrink-0">{{ formatPrice(item.prix_vente) }}</span>
                </button>
              </div>

              <!-- Clients -->
              <div v-if="results.clients.length" class="py-1 border-t border-slate-50">
                <p class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider">👤 Clients</p>
                <button
                  v-for="(item, i) in results.clients" :key="`c-${item.id}`"
                  class="w-full text-left px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors"
                  :class="groupOffsets.clients + i === focusedIndex ? 'bg-gold/10' : ''"
                  @mousedown.prevent="navigate(item)"
                >
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-navy font-medium truncate" v-html="highlight(item.nom)"/>
                    <p v-if="item.telephone" class="text-xs text-slate-400 truncate">{{ item.telephone }}</p>
                  </div>
                </button>
              </div>

              <!-- Ventes -->
              <div v-if="results.ventes.length" class="py-1 border-t border-slate-50">
                <p class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider">🧾 Ventes</p>
                <button
                  v-for="(item, i) in results.ventes" :key="`v-${item.id}`"
                  class="w-full text-left px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors"
                  :class="groupOffsets.ventes + i === focusedIndex ? 'bg-gold/10' : ''"
                  @mousedown.prevent="navigate(item)"
                >
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-navy font-medium truncate" v-html="highlight(item.numero)"/>
                  </div>
                  <span class="text-xs font-mono text-slate-500 flex-shrink-0">{{ formatPrice(item.total) }}</span>
                </button>
              </div>

              <!-- Fournisseurs -->
              <div v-if="results.fournisseurs.length" class="py-1 border-t border-slate-50">
                <p class="px-4 pt-3 pb-1 text-xs font-semibold text-slate-400 uppercase tracking-wider">🚚 Fournisseurs</p>
                <button
                  v-for="(item, i) in results.fournisseurs" :key="`f-${item.id}`"
                  class="w-full text-left px-4 py-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors"
                  :class="groupOffsets.fournisseurs + i === focusedIndex ? 'bg-gold/10' : ''"
                  @mousedown.prevent="navigate(item)"
                >
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-navy font-medium truncate" v-html="highlight(item.nom)"/>
                    <p v-if="item.telephone" class="text-xs text-slate-400 truncate">{{ item.telephone }}</p>
                  </div>
                </button>
              </div>

            </template>
          </div>

          <!-- Footer hint -->
          <div class="border-t border-slate-100 px-4 py-2 flex items-center gap-4 text-xs text-slate-400">
            <span><kbd class="border border-slate-200 rounded px-1">↑↓</kbd> naviguer</span>
            <span><kbd class="border border-slate-200 rounded px-1">↵</kbd> ouvrir</span>
            <span><kbd class="border border-slate-200 rounded px-1">Esc</kbd> fermer</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { searchApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

const router       = useRouter()
const open         = ref(false)
const query        = ref('')
const loading      = ref(false)
const inputRef     = ref<HTMLInputElement | null>(null)
const focusedIndex = ref(-1)

interface Results {
  produits: any[]
  clients: any[]
  ventes: any[]
  fournisseurs: any[]
}
const results = ref<Results>({ produits: [], clients: [], ventes: [], fournisseurs: [] })

const hasNoResults = computed(() =>
  !results.value.produits.length &&
  !results.value.clients.length &&
  !results.value.ventes.length &&
  !results.value.fournisseurs.length
)

const flatItems = computed(() => [
  ...results.value.produits,
  ...results.value.clients,
  ...results.value.ventes,
  ...results.value.fournisseurs,
])

const groupOffsets = computed(() => ({
  produits:     0,
  clients:      results.value.produits.length,
  ventes:       results.value.produits.length + results.value.clients.length,
  fournisseurs: results.value.produits.length + results.value.clients.length + results.value.ventes.length,
}))

function highlight(text: string): string {
  if (!query.value || !text) return text ?? ''
  const escaped = query.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return text.replace(new RegExp(`(${escaped})`, 'gi'),
    '<mark class="bg-gold/30 text-navy rounded-sm px-0.5">$1</mark>')
}

function handleInputKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    e.preventDefault()
    close()
    return
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    const len = flatItems.value.length
    if (!len) return
    focusedIndex.value = (focusedIndex.value - 1 + len) % len
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    const len = flatItems.value.length
    if (!len) return
    focusedIndex.value = (focusedIndex.value + 1 + len) % len
    return
  }
  if (e.key === 'Enter') {
    e.preventDefault()
    console.log('[GlobalSearch] Enter pressed — focusedIndex:', focusedIndex.value, '| item:', flatItems.value[focusedIndex.value])
    if (focusedIndex.value >= 0 && focusedIndex.value < flatItems.value.length) {
      navigate(flatItems.value[focusedIndex.value])
    }
  }
}

async function navigate(item: any) {
  const current = router.currentRoute.value
  close()  // fermer d'abord pour ne pas bloquer le rendu de la page cible
  if (current.path !== item.link) {
    await router.push(item.link)
  }
}

// Déclaré ici pour être accessible dans watch(open) et watch(query)
let debounceTimer: ReturnType<typeof setTimeout>

// Gestion ouverture/fermeture du modal
watch(open, (val) => {
  if (!val) {
    // Nettoyage complet à la fermeture
    clearTimeout(debounceTimer)
    query.value        = ''
    results.value      = { produits: [], clients: [], ventes: [], fournisseurs: [] }
    focusedIndex.value = -1
    loading.value      = false
    return
  }
  // MutationObserver : attend que Teleport insère l'input dans le DOM
  const observer = new MutationObserver(() => {
    const el = document.getElementById('global-search-input') as HTMLInputElement | null
    if (el) {
      el.focus()
      observer.disconnect()
    }
  })
  observer.observe(document.body, { childList: true, subtree: true })
  // Fallback au cas où l'input est déjà présent
  const existing = document.getElementById('global-search-input') as HTMLInputElement | null
  if (existing) { existing.focus(); observer.disconnect() }
})

// Debounce search
watch(query, (val) => {
  clearTimeout(debounceTimer)
  if (val.length < 2) {
    results.value = { produits: [], clients: [], ventes: [], fournisseurs: [] }
    focusedIndex.value = -1
    loading.value = false
    return
  }
  loading.value = true
  debounceTimer = setTimeout(async () => {
    try {
      const { data } = await searchApi.search(val)
      results.value = data
      focusedIndex.value = -1  // reset seulement quand de nouveaux résultats arrivent
    } catch {
      results.value = { produits: [], clients: [], ventes: [], fournisseurs: [] }
      focusedIndex.value = -1
    } finally {
      loading.value = false
    }
  }, 300)
})

function openSearch() {
  query.value   = ''
  focusedIndex.value = -1
  results.value = { produits: [], clients: [], ventes: [], fournisseurs: [] }
  open.value    = true  // déclenche le watch(open) → MutationObserver → focus
}

function close() {
  open.value = false
  // Le watch(open → false) se charge du nettoyage complet
}

defineExpose({ openSearch })
</script>

<style scoped>
.search-modal-enter-active,
.search-modal-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.search-modal-enter-from,
.search-modal-leave-to {
  opacity: 0;
  transform: scale(0.97) translateY(-8px);
}
</style>
