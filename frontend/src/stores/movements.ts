import { defineStore } from 'pinia'
import { ref } from 'vue'
import { movementsApi } from '@/services/api'

export interface Movement {
  id: number
  type_mouvement: 'entree' | 'sortie' | 'ajustement'
  quantite: number
  quantite_avant: number
  quantite_apres: number
  note: string | null
  date_mouvement: string
  product: { id: number; nom: string; reference: string; unite_mesure: string }
  user: { id: number; nom: string; prenom: string }
}

export const useMovementsStore = defineStore('movements', () => {
  const movements  = ref<Movement[]>([])
  const loading    = ref(false)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })

  async function fetchMovements(params?: object) {
    loading.value = true
    try {
      const { data } = await movementsApi.list(params)
      movements.value  = data.data
      pagination.value = {
        current_page: data.meta.current_page,
        last_page:    data.meta.last_page,
        total:        data.meta.total,
      }
    } finally {
      loading.value = false
    }
  }

  async function createMovement(payload: {
    product_id: number
    type_mouvement: string
    quantite: number
    note?: string
  }) {
    const { data } = await movementsApi.create(payload)
    movements.value.unshift(data)
    return data
  }

  return { movements, loading, pagination, fetchMovements, createMovement }
})
