<template>
  <div class="space-y-5">
    <!-- Tabs -->
    <div class="flex gap-1 border-b border-slate-200">
      <button v-for="tab in tabs" :key="tab.key"
        @click="activeTab = tab.key"
        :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
          activeTab === tab.key
            ? 'border-gold text-gold'
            : 'border-transparent text-slate-500 hover:text-slate-700']">
        {{ tab.label }}
        <span v-if="tab.badge" class="ml-1.5 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">
          {{ tab.badge }}
        </span>
      </button>
    </div>

    <!-- Tab: Ruptures & Alertes -->
    <div v-if="activeTab === 'stock'">
      <div v-if="alerts.loading" class="text-center py-10 text-slate-400">Chargement…</div>
      <div v-else class="space-y-4">
        <!-- Ruptures -->
        <div v-if="alerts.ruptures.length > 0">
          <h3 class="text-sm font-semibold text-red-600 mb-2">🔴 Ruptures de stock ({{ alerts.ruptures.length }})</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="p in alerts.ruptures" :key="p.id"
              class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between">
              <div>
                <p class="font-semibold text-red-800">{{ p.nom }}</p>
                <p class="text-xs text-red-500">Réf: {{ p.reference }}</p>
              </div>
              <div class="text-right">
                <p class="text-red-700 font-bold text-lg">0</p>
                <p class="text-xs text-red-400">Seuil: {{ p.seuil_alerte }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Alertes -->
        <div v-if="alerts.alertes.length > 0">
          <h3 class="text-sm font-semibold text-amber-600 mb-2">⚠️ Alertes de stock ({{ alerts.alertes.length }})</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="p in alerts.alertes" :key="p.id"
              class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center justify-between">
              <div>
                <p class="font-semibold text-amber-800">{{ p.nom }}</p>
                <p class="text-xs text-amber-500">Réf: {{ p.reference }}</p>
              </div>
              <div class="text-right">
                <p class="text-amber-700 font-bold text-lg">{{ p.quantite }}</p>
                <p class="text-xs text-amber-400">Seuil: {{ p.seuil_alerte }}</p>
              </div>
            </div>
          </div>
        </div>

        <div v-if="alerts.ruptures.length === 0 && alerts.alertes.length === 0"
          class="text-center py-10 text-emerald-600 font-medium">
          ✅ Aucune alerte de stock — tout est en ordre !
        </div>
      </div>
    </div>

    <!-- Tab: AI Suggestions -->
    <div v-if="activeTab === 'suggestions'">
      <div v-if="!auth.hasAI" class="text-center py-10">
        <p class="text-slate-500">Fonctionnalité disponible sur les plans Pro et Enterprise.</p>
      </div>
      <div v-else>
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-slate-500">Suggestions de réapprovisionnement générées par IA</p>
          <button @click="loadSuggestions" :disabled="alerts.loading" class="btn-primary text-sm">
            {{ alerts.loading ? 'Génération…' : '✨ Actualiser les suggestions' }}
          </button>
        </div>
        <div v-if="alerts.suggestions.length === 0" class="text-center py-10 text-slate-400">
          Cliquez sur « Actualiser » pour générer des suggestions.
        </div>
        <div class="space-y-3">
          <div v-for="s in alerts.suggestions" :key="s.product_id"
            class="card flex items-center justify-between">
            <div>
              <p class="font-semibold text-navy">{{ s.nom }}</p>
              <p class="text-xs text-slate-500 mt-0.5">{{ s.justification }}</p>
            </div>
            <div class="text-right">
              <p class="text-gold font-bold text-lg">{{ s.quantite_suggeree }}</p>
              <div class="flex items-center gap-1 justify-end mt-1">
                <div class="bg-slate-200 rounded-full h-1.5 w-20">
                  <div class="bg-gold h-1.5 rounded-full" :style="{ width: s.confiance + '%' }" />
                </div>
                <span class="text-xs text-slate-400">{{ s.confiance }}%</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Anomaly Detection -->
    <div v-if="activeTab === 'anomalies'">
      <div v-if="!auth.hasAI" class="text-center py-10">
        <p class="text-slate-500">Fonctionnalité disponible sur les plans Pro et Enterprise.</p>
      </div>
      <div v-else>
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-slate-500">Détection d'anomalies — règle des 3σ (écarts-types)</p>
          <button @click="loadAnomalies" :disabled="alerts.loading" class="btn-primary text-sm">
            {{ alerts.loading ? 'Analyse…' : '🔍 Analyser les anomalies' }}
          </button>
        </div>
        <div v-if="alerts.anomalies.length === 0" class="text-center py-10 text-slate-400">
          Cliquez sur « Analyser » pour lancer la détection.
        </div>
        <div class="space-y-3">
          <div v-for="a in alerts.anomalies" :key="a.id"
            :class="['card border-l-4',
              a.severite === 'high'   ? 'border-red-500' :
              a.severite === 'medium' ? 'border-amber-500' :
                                        'border-blue-400']">
            <div class="flex items-start justify-between">
              <div>
                <p class="font-semibold text-navy">{{ a.nom ?? a.product_id }}</p>
                <p class="text-sm text-slate-500 mt-0.5">{{ a.description }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ a.date }}</p>
              </div>
              <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold',
                a.severite === 'high'   ? 'bg-red-100 text-red-700' :
                a.severite === 'medium' ? 'bg-amber-100 text-amber-700' :
                                          'bg-blue-100 text-blue-700']">
                {{ ({ high: 'Élevé', medium: 'Moyen', low: 'Faible' } as Record<string, string>)[a.severite] }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAlertsStore } from '@/stores/alerts'
import { useAuthStore } from '@/stores/auth'

const alerts     = useAlertsStore()
const auth       = useAuthStore()
const activeTab  = ref('stock')

const tabs = computed(() => [
  { key: 'stock',       label: 'Ruptures & Alertes', badge: alerts.totalAlerts() || undefined },
  { key: 'suggestions', label: '✨ Suggestions IA',  badge: undefined },
  { key: 'anomalies',   label: '🔍 Anomalies',       badge: undefined },
])

function loadSuggestions() { alerts.fetchSuggestions() }
function loadAnomalies()   { alerts.fetchAnomalies() }

onMounted(() => alerts.fetchStockAlerts())
</script>
