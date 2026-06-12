import { defineStore } from 'pinia'
import { ref } from 'vue'
import { alertsApi } from '@/services/api'

export const useAlertsStore = defineStore('alerts', () => {
  const ruptures    = ref<any[]>([])
  const alertes     = ref<any[]>([])
  const suggestions = ref<any[]>([])
  const anomalies   = ref<any[]>([])
  const loading     = ref(false)

  async function fetchStockAlerts() {
    loading.value = true
    try {
      const { data } = await alertsApi.stock()
      ruptures.value = data.ruptures
      alertes.value  = data.alertes
    } finally {
      loading.value = false
    }
  }

  async function fetchSuggestions() {
    loading.value = true
    try {
      const { data } = await alertsApi.suggestions()
      suggestions.value = data.suggestions
    } finally {
      loading.value = false
    }
  }

  async function fetchAnomalies(productId?: number) {
    loading.value = true
    try {
      const { data } = await alertsApi.anomalies(productId)
      anomalies.value = data.anomalies
    } finally {
      loading.value = false
    }
  }

  const totalAlerts = () => ruptures.value.length + alertes.value.length

  return {
    ruptures, alertes, suggestions, anomalies, loading,
    fetchStockAlerts, fetchSuggestions, fetchAnomalies, totalAlerts,
  }
})
