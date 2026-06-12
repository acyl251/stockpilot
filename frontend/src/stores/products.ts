import { defineStore } from 'pinia'
import { ref } from 'vue'
import { productsApi, categoriesApi, productTypesApi } from '@/services/api'

export interface Product {
  id: number
  nom: string
  reference: string
  quantite: number
  seuil_alerte: number
  unite_mesure: string
  prix_achat_ht: number
  prix_achat_ttc: number
  prix_vente_ht: number
  prix_vente_ttc: number
  taux_tva: number
  statut: 'En stock' | 'Alerte' | 'Rupture'
  en_alerte: boolean
  en_rupture: boolean
  actif: boolean
  attributs: Record<string, unknown>
  category?: { id: number; nom: string; couleur: string }
  product_type?: { id: number; nom: string }
}

export const useProductsStore = defineStore('products', () => {
  const products    = ref<Product[]>([])
  const categories  = ref<any[]>([])
  const types       = ref<any[]>([])
  const loading     = ref(false)
  const pagination  = ref({ current_page: 1, last_page: 1, total: 0 })

  async function fetchProducts(params?: object) {
    loading.value = true
    try {
      const { data } = await productsApi.list(params)
      products.value  = data.data
      pagination.value = {
        current_page: data.meta.current_page,
        last_page:    data.meta.last_page,
        total:        data.meta.total,
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchCategories() {
    const { data } = await categoriesApi.list()
    categories.value = data
  }

  async function fetchTypes() {
    const { data } = await productTypesApi.list()
    types.value = data
  }

  async function createProduct(payload: object) {
    const { data } = await productsApi.create(payload)
    products.value.unshift(data)
    return data
  }

  async function updateProduct(id: number, payload: object) {
    const { data } = await productsApi.update(id, payload)
    const idx = products.value.findIndex(p => p.id === id)
    if (idx !== -1) products.value[idx] = data
    return data
  }

  async function deleteProduct(id: number) {
    await productsApi.destroy(id)
    products.value = products.value.filter(p => p.id !== id)
  }

  return {
    products, categories, types, loading, pagination,
    fetchProducts, fetchCategories, fetchTypes,
    createProduct, updateProduct, deleteProduct,
  }
})
