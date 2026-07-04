<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div class="flex gap-3 flex-wrap">
        <select v-model="filterType" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les types</option>
          <option value="entree">Entrées</option>
          <option value="sortie">Sorties</option>
          <option value="ajustement">Ajustements</option>
        </select>
        <select v-if="auth.isAdmin && pointsDeVente.length > 0"
          v-model="filterPdv" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold">
          <option value="">Tous les points de vente</option>
          <option v-for="pdv in pointsDeVente" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
        </select>
        <input v-model="filterDateFrom" type="date" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />
        <input v-model="filterDateTo" type="date" @change="fetchMovements"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />
      </div>
      <button @click="showForm = true" class="btn-primary">+ Nouveau mouvement</button>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Date</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Produit</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Type</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Quantité</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Avant → Après</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Opérateur</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Note</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.loading">
            <td colspan="7" class="text-center py-10 text-slate-400">Chargement…</td>
          </tr>
          <tr v-else-if="store.movements.length === 0">
            <td colspan="7" class="text-center py-10 text-slate-400">Aucun mouvement trouvé.</td>
          </tr>
          <tr v-for="m in store.movements" :key="m.id"
            class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 text-slate-500 text-xs">{{ formatDate(m.date_mouvement) }}</td>
            <td class="px-4 py-3 font-medium text-navy">{{ m.product?.nom }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold',
                m.type_mouvement === 'entree'     ? 'bg-emerald-100 text-emerald-700' :
                m.type_mouvement === 'sortie'     ? 'bg-red-100 text-red-700' :
                                                    'bg-blue-100 text-blue-700']">
                {{ { entree: 'Entrée', sortie: 'Sortie', ajustement: 'Ajust.' }[m.type_mouvement] }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold"
              :class="m.type_mouvement === 'entree' ? 'text-emerald-600' : 'text-red-600'">
              {{ m.type_mouvement === 'sortie' ? '-' : '+' }}{{ m.quantite }} {{ m.product?.unite_mesure }}
            </td>
            <td class="px-4 py-3 text-right text-slate-500 text-xs">
              {{ m.quantite_avant }} → {{ m.quantite_apres }}
            </td>
            <td class="px-4 py-3 text-slate-600 text-xs">
              {{ m.user?.prenom }} {{ m.user?.nom }}
            </td>
            <td class="px-4 py-3 text-slate-400 text-xs truncate max-w-xs">{{ m.note }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Movement drawer -->
    <MovementDrawer v-if="showForm" @close="showForm = false" @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useMovementsStore } from '@/stores/movements'
import { useAuthStore } from '@/stores/auth'
import { pointsDeVenteApi } from '@/services/api'
import MovementDrawer from '@/components/MovementDrawer.vue'
import { format } from 'date-fns'
import { fr } from 'date-fns/locale'

const store          = useMovementsStore()
const auth           = useAuthStore()
const filterType     = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')
const filterPdv      = ref<number | ''>('')
const showForm       = ref(false)
const pointsDeVente  = ref<any[]>([])

function fetchMovements() {
  store.fetchMovements({
    type_mouvement:    filterType.value     || undefined,
    date_from:         filterDateFrom.value || undefined,
    date_to:           filterDateTo.value   || undefined,
    point_de_vente_id: filterPdv.value      || undefined,
  })
}

function formatDate(d: string) {
  return format(new Date(d), 'dd/MM/yyyy HH:mm', { locale: fr })
}

function onSaved() {
  showForm.value = false
  fetchMovements()
}

onMounted(async () => {
  if (auth.isAdmin) {
    try { const { data } = await pointsDeVenteApi.list(); pointsDeVente.value = data } catch {}
  }
  fetchMovements()
})
</script>
