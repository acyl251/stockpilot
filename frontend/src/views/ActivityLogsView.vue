<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-navy">Journal d'activité</h1>
        <p class="text-slate-500 text-sm mt-0.5">Toutes les actions effectuées dans votre organisation</p>
      </div>
      <button @click="exportCsv" class="btn-secondary flex items-center gap-2 text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Exporter CSV
      </button>
    </div>

    <!-- Résumé -->
    <div class="grid grid-cols-3 gap-4">
      <div class="card text-center py-4">
        <p class="text-3xl font-bold text-navy">{{ summary.today_count ?? 0 }}</p>
        <p class="text-sm text-slate-500 mt-1">Actions aujourd'hui</p>
      </div>
      <div class="card py-4">
        <p class="text-xs text-slate-500 mb-1">Utilisateur le plus actif</p>
        <p class="font-semibold text-navy truncate">{{ summary.most_active?.user ?? '—' }}</p>
        <p v-if="summary.most_active" class="text-xs text-slate-400">{{ summary.most_active.count }} action(s) aujourd'hui</p>
      </div>
      <div class="card py-4">
        <p class="text-xs text-slate-500 mb-1">Dernière action</p>
        <p class="font-semibold text-navy text-sm truncate">{{ summary.last_action?.description ?? '—' }}</p>
        <p v-if="summary.last_action?.created_at" class="text-xs text-slate-400">
          {{ fmtDate(summary.last_action.created_at) }} · {{ summary.last_action.user ?? '' }}
        </p>
      </div>
    </div>

    <!-- Filtres -->
    <div class="card">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <!-- Période rapide -->
        <div>
          <label class="label">Période</label>
          <select v-model="periode" @change="onPeriodeChange" class="input text-sm">
            <option value="today">Aujourd'hui</option>
            <option value="week">Cette semaine</option>
            <option value="month">Ce mois</option>
            <option value="custom">Personnalisé</option>
          </select>
        </div>
        <div v-if="periode === 'custom'" class="col-span-1">
          <label class="label">Du</label>
          <input type="date" v-model="filters.debut" @change="load(1)" class="input text-sm" />
        </div>
        <div v-if="periode === 'custom'" class="col-span-1">
          <label class="label">Au</label>
          <input type="date" v-model="filters.fin" @change="load(1)" class="input text-sm" />
        </div>
        <!-- Module -->
        <div>
          <label class="label">Module</label>
          <select v-model="filters.module" @change="load(1)" class="input text-sm">
            <option value="">Tous les modules</option>
            <option v-for="m in modules" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
        <!-- Utilisateur -->
        <div>
          <label class="label">Utilisateur</label>
          <select v-model="filters.user_id" @change="load(1)" class="input text-sm">
            <option value="">Tous</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.prenom }} {{ u.nom }}</option>
          </select>
        </div>
        <!-- Recherche texte -->
        <div class="md:col-span-2">
          <label class="label">Recherche</label>
          <input type="text" v-model="filters.search" @input="onSearch" placeholder="Rechercher dans les descriptions…" class="input text-sm" />
        </div>
      </div>
    </div>

    <!-- Tableau -->
    <div class="card p-0 overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400">
        <svg class="w-6 h-6 animate-spin mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
        Chargement…
      </div>
      <div v-else-if="!logs.length" class="py-16 text-center text-slate-400">
        Aucune activité trouvée pour ces critères.
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-medium w-36">Date / Heure</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium w-40">Utilisateur</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium w-28">Module</th>
            <th class="text-left px-4 py-3 text-slate-600 font-medium">Description</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="log in logs" :key="log.id"
            @click="openDetail(log)"
            class="hover:bg-slate-50 cursor-pointer transition-colors"
          >
            <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-xs">{{ fmtDate(log.created_at) }}</td>
            <td class="px-4 py-3">
              <div v-if="log.user" class="flex items-center gap-2">
                <span class="font-medium text-navy">{{ log.user.prenom }} {{ log.user.nom }}</span>
                <span :class="['text-xs px-1.5 py-0.5 rounded-full font-medium', roleBadge(log.user.role)]">
                  {{ log.user.role }}
                </span>
              </div>
              <span v-else class="text-slate-400 text-xs italic">Système</span>
            </td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', moduleBadge(log.module)]">
                {{ log.module }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-700">{{ log.description }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
        <p class="text-xs text-slate-500">Page {{ currentPage }} / {{ totalPages }} — {{ total }} entrée(s)</p>
        <div class="flex gap-1">
          <button
            v-for="p in pagesRange" :key="p"
            @click="load(p)"
            :class="['px-3 py-1 text-xs rounded border transition-colors',
              p === currentPage ? 'bg-navy text-white border-navy' : 'border-slate-200 hover:border-slate-400']"
          >{{ p }}</button>
        </div>
      </div>
    </div>

    <!-- Modal détail -->
    <div v-if="showDetail && detailLog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="showDetail = false">
      <div class="bg-white rounded-xl w-full max-w-lg p-6 shadow-xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-navy">Détail de l'action</h3>
          <button @click="showDetail = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <dl class="space-y-2 text-sm">
          <div class="flex gap-2"><dt class="text-slate-500 w-28 flex-shrink-0">Date</dt><dd class="text-navy font-medium">{{ fmtDate(detailLog.created_at) }}</dd></div>
          <div class="flex gap-2"><dt class="text-slate-500 w-28 flex-shrink-0">Utilisateur</dt><dd>{{ detailLog.user ? detailLog.user.prenom + ' ' + detailLog.user.nom : '—' }}</dd></div>
          <div class="flex gap-2"><dt class="text-slate-500 w-28 flex-shrink-0">Module</dt><dd><span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', moduleBadge(detailLog.module)]">{{ detailLog.module }}</span></dd></div>
          <div class="flex gap-2"><dt class="text-slate-500 w-28 flex-shrink-0">Action</dt><dd class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded">{{ detailLog.action }}</dd></div>
          <div class="flex gap-2"><dt class="text-slate-500 w-28 flex-shrink-0">Description</dt><dd class="text-navy">{{ detailLog.description }}</dd></div>
          <div class="flex gap-2"><dt class="text-slate-500 w-28 flex-shrink-0">IP</dt><dd class="font-mono text-xs text-slate-400">{{ detailLog.ip_address ?? '—' }}</dd></div>
        </dl>
        <div v-if="detailLog.meta && Object.keys(detailLog.meta).length" class="mt-4">
          <p class="text-xs text-slate-500 mb-1 font-medium">Métadonnées</p>
          <pre class="bg-slate-900 text-green-300 text-xs p-3 rounded-lg overflow-auto max-h-48">{{ JSON.stringify(detailLog.meta, null, 2) }}</pre>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { activityLogsApi } from '@/services/api'

const logs        = ref<any[]>([])
const users       = ref<any[]>([])
const summary     = ref<any>({})
const loading     = ref(false)
const total       = ref(0)
const currentPage = ref(1)
const lastPage    = ref(1)
const showDetail  = ref(false)
const detailLog   = ref<any>(null)

const periode  = ref('today')
const filters  = ref({ debut: '', fin: '', module: '', user_id: '', search: '' })
let searchTimer: ReturnType<typeof setTimeout> | null = null

const modules = [
  { value: 'caisse',        label: 'Caisse' },
  { value: 'vente',         label: 'Vente' },
  { value: 'produit',       label: 'Produit' },
  { value: 'stock',         label: 'Stock' },
  { value: 'client',        label: 'Client' },
  { value: 'fournisseur',   label: 'Fournisseur' },
  { value: 'utilisateur',   label: 'Utilisateur' },
  { value: 'configuration', label: 'Configuration' },
]

const totalPages = computed(() => lastPage.value)
const pagesRange = computed(() => {
  const pages: number[] = []
  for (let i = Math.max(1, currentPage.value - 2); i <= Math.min(lastPage.value, currentPage.value + 2); i++) {
    pages.push(i)
  }
  return pages
})

function fmtDate(iso: string) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function onPeriodeChange() {
  const now   = new Date()
  const today = now.toISOString().slice(0, 10)

  if (periode.value === 'today') {
    filters.value.debut = today
    filters.value.fin   = today
  } else if (periode.value === 'week') {
    const mon = new Date(now)
    mon.setDate(now.getDate() - now.getDay() + (now.getDay() === 0 ? -6 : 1))
    filters.value.debut = mon.toISOString().slice(0, 10)
    filters.value.fin   = today
  } else if (periode.value === 'month') {
    filters.value.debut = today.slice(0, 7) + '-01'
    filters.value.fin   = today
  } else {
    filters.value.debut = ''
    filters.value.fin   = ''
  }

  load(1)
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await activityLogsApi.list({
      page,
      debut:   filters.value.debut   || undefined,
      fin:     filters.value.fin     || undefined,
      module:  filters.value.module  || undefined,
      user_id: filters.value.user_id || undefined,
      search:  filters.value.search  || undefined,
    })

    logs.value        = data.data
    total.value       = data.total
    currentPage.value = data.current_page
    lastPage.value    = data.last_page
    summary.value     = data.summary ?? {}

    if (data.users?.length) {
      users.value = data.users
    }
  } finally {
    loading.value = false
  }
}

function openDetail(log: any) {
  detailLog.value = log
  showDetail.value = true
}

async function exportCsv() {
  const { data: blob } = await activityLogsApi.export({
    debut:   filters.value.debut   || undefined,
    fin:     filters.value.fin     || undefined,
    module:  filters.value.module  || undefined,
    user_id: filters.value.user_id || undefined,
    search:  filters.value.search  || undefined,
  })
  const url = URL.createObjectURL(new Blob([blob], { type: 'text/csv;charset=utf-8' }))
  const a   = document.createElement('a')
  a.href    = url
  a.download = `activite_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function moduleBadge(module: string): string {
  const map: Record<string, string> = {
    caisse:        'bg-emerald-100 text-emerald-700',
    vente:         'bg-emerald-100 text-emerald-700',
    produit:       'bg-blue-100 text-blue-700',
    stock:         'bg-orange-100 text-orange-700',
    client:        'bg-purple-100 text-purple-700',
    fournisseur:   'bg-amber-100 text-amber-700',
    utilisateur:   'bg-slate-100 text-slate-600',
    configuration: 'bg-red-100 text-red-700',
  }
  return map[module] ?? 'bg-slate-100 text-slate-600'
}

function roleBadge(role: string): string {
  const map: Record<string, string> = {
    admin:       'bg-navy/10 text-navy',
    manager:     'bg-indigo-100 text-indigo-700',
    caissier:    'bg-slate-100 text-slate-500',
    super_admin: 'bg-red-100 text-red-700',
  }
  return map[role] ?? 'bg-slate-100 text-slate-500'
}

onMounted(() => {
  onPeriodeChange()  // load with "today" preset
})
</script>
