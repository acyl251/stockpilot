<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-navy">Consommation ingrédients</h1>
        <p class="text-sm text-slate-500 mt-0.5">Suivi des sorties de stock par période</p>
      </div>
      <button @click="exportCsv" class="btn-primary text-sm py-2 px-4">Exporter CSV</button>
    </div>

    <!-- Filtres période -->
    <div class="card p-4">
      <div class="flex flex-wrap gap-2 items-center">
        <button v-for="p in presets" :key="p.key" @click="applyPreset(p.key)"
          :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
            activePreset === p.key
              ? 'bg-navy text-white'
              : 'border border-slate-300 text-slate-600 hover:bg-slate-50']">
          {{ p.label }}
        </button>
        <div class="flex items-center gap-2 ml-auto">
          <input type="date" v-model="dateDebut" @change="activePreset = 'custom'; fetch()"
            class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
          <span class="text-slate-400 text-sm">→</span>
          <input type="date" v-model="dateFin" @change="activePreset = 'custom'; fetch()"
            class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
      </div>
    </div>

    <!-- Résumé -->
    <div v-if="resume" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="card px-5 py-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Coût matière total</p>
        <p class="text-2xl font-bold text-navy">{{ money(resume.cout_total) }}</p>
      </div>
      <div class="card px-5 py-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Plus consommé</p>
        <p class="text-lg font-bold text-navy truncate">{{ resume.plus_consomme?.nom ?? '—' }}</p>
        <p v-if="resume.plus_consomme" class="text-xs text-slate-400">
          {{ resume.plus_consomme.consomme }} {{ resume.plus_consomme.unite }}
        </p>
      </div>
      <div class="card px-5 py-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Plus coûteux</p>
        <p class="text-lg font-bold text-navy truncate">{{ resume.plus_couteux?.nom ?? '—' }}</p>
        <p v-if="resume.plus_couteux" class="text-xs text-slate-400">
          {{ money(resume.plus_couteux.cout_total) }}
        </p>
      </div>
    </div>

    <!-- Tableau -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Ingrédient</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Unité</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Consommé</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Coût total matière</th>
            <th class="px-4 py-3 w-28">
              <!-- bar chart header -->
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center py-10 text-slate-400">Chargement…</td>
          </tr>
          <tr v-else-if="rows.length === 0">
            <td colspan="5" class="text-center py-10 text-slate-400">Aucune consommation sur cette période.</td>
          </tr>
          <tr v-for="row in rows" :key="row.nom"
            class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 font-medium text-navy">{{ row.nom }}</td>
            <td class="px-4 py-3 text-center text-slate-500">{{ row.unite }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700">{{ row.consomme }}</td>
            <td class="px-4 py-3 text-right font-semibold text-navy">{{ money(row.cout_total) }}</td>
            <td class="px-4 py-3">
              <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-gold rounded-full transition-all"
                  :style="{ width: barPct(row.cout_total) }" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Totaux -->
      <div v-if="rows.length > 0"
        class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50 text-sm font-semibold text-navy">
        <span>{{ rows.length }} ingrédient(s)</span>
        <span>Total coût matière : {{ money(resume?.cout_total ?? 0) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { consommationApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

interface Row { nom: string; unite: string; consomme: number; cout_total: number }

const rows    = ref<Row[]>([])
const resume  = ref<any>(null)
const loading = ref(false)

const _now  = new Date()
const today = `${_now.getFullYear()}-${String(_now.getMonth() + 1).padStart(2, '0')}-${String(_now.getDate()).padStart(2, '0')}`
const dateDebut = ref(today)
const dateFin   = ref(today)
const activePreset = ref<string>('today')

const presets = [
  { key: 'today', label: "Aujourd'hui" },
  { key: 'week',  label: 'Cette semaine' },
  { key: 'month', label: 'Ce mois' },
  { key: 'custom', label: 'Personnalisé' },
]

function applyPreset(key: string) {
  activePreset.value = key
  const now = new Date()
  if (key === 'today') {
    dateDebut.value = dateFin.value = today
  } else if (key === 'week') {
    const mon = new Date(now)
    mon.setDate(now.getDate() - now.getDay() + 1)
    dateDebut.value = mon.toISOString().slice(0, 10)
    dateFin.value   = today
  } else if (key === 'month') {
    dateDebut.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
    dateFin.value   = today
  }
  if (key !== 'custom') fetch()
}

async function fetch() {
  loading.value = true
  try {
    const { data } = await consommationApi.index({
      debut: dateDebut.value,
      fin:   dateFin.value,
    })
    rows.value   = data.data   ?? []
    resume.value = data.resume ?? null
  } finally {
    loading.value = false
  }
}

async function exportCsv() {
  const { data } = await consommationApi.export({
    debut: dateDebut.value,
    fin:   dateFin.value,
  })
  const url = URL.createObjectURL(new Blob([data], { type: 'text/csv' }))
  const a   = document.createElement('a')
  a.href    = url
  a.download = `consommation_${dateDebut.value}_${dateFin.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

const maxCout = computed(() => Math.max(...rows.value.map(r => r.cout_total), 1))
function barPct(val: number) { return `${Math.round((val / maxCout.value) * 100)}%` }
function money(v: number)    { return formatPrice(v) }

onMounted(fetch)
</script>
