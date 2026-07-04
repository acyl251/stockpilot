import axios from 'axios'

export const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

// Attach JWT token on every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 globally — redirect to login
api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('access_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  },
)

// ─── Auth ─────────────────────────────────────────────────────────────────────
export const authApi = {
  login:   (email: string, password: string) => api.post('/auth/login', { email, password }),
  logout:  ()                                => api.post('/auth/logout'),
  me:      ()                                => api.get('/auth/me'),
  refresh: ()                                => api.post('/auth/refresh'),
}

// ─── Dashboard ─────────────────────────────────────────────────────────────────
export const dashboardApi = {
  index:      ()                   => api.get('/dashboard'),
  forecast:   (productId: number)  => api.get(`/dashboard/forecast/${productId}`),
  restaurant: ()                   => api.get('/dashboard/restaurant'),
}

// ─── Products ─────────────────────────────────────────────────────────────────
export const productsApi = {
  list:    (params?: object)       => api.get('/products', { params }),
  get:     (id: number)            => api.get(`/products/${id}`),
  create:  (data: object)          => api.post('/products', data),
  update:  (id: number, data: object) => api.patch(`/products/${id}`, data),
  destroy: (id: number)            => api.delete(`/products/${id}`),
  checkReference: (reference: string, excludeId?: number) =>
    api.get('/products/check-reference', { params: { reference, exclude_id: excludeId } }),
}

// ─── Stock Movements ──────────────────────────────────────────────────────────
export const movementsApi = {
  list:   (params?: object) => api.get('/movements', { params }),
  get:    (id: number)      => api.get(`/movements/${id}`),
  create: (data: object)    => api.post('/movements', data),
}

// ─── Clients (comptes / crédit) ──────────────────────────────────────────────
export const clientsApi = {
  list:   (params?: object)            => api.get('/clients', { params }),
  get:    (id: number)                 => api.get(`/clients/${id}`),
  create: (data: object)               => api.post('/clients', data),
  update: (id: number, data: object)   => api.patch(`/clients/${id}`, data),
  pay:    (id: number, data: object)   => api.post(`/clients/${id}/pay`, data),
  remind: (id: number)                 => api.post(`/clients/${id}/remind`),
}

// ─── Caisse (POS) ──────────────────────────────────────────────────────────────
export const salesApi = {
  list:             (params?: object) => api.get('/sales', { params }),
  get:              (id: number)      => api.get(`/sales/${id}`),
  create:           (data: object)    => api.post('/sales', data),
  cancel:           (id: number)      => api.post(`/sales/${id}/cancel`),
  export:           (params?: object) => api.get('/sales/export', { params, responseType: 'blob' }),
  invoice:          (id: number)      => api.get(`/sales/${id}/invoice`, { responseType: 'blob' }),
  checkIngredients: (data: object)    => api.post('/sales/check-ingredients', data),
}

// ─── Organisation (infos légales / facturation) ──────────────────────────────
export const organisationApi = {
  get:    ()            => api.get('/organisation'),
  update: (data: object) => api.patch('/organisation', data),
}

// ─── Recettes / Fiches techniques (restauration) ──────────────────────────────
export const compositionApi = {
  list:    (productId: number)                          => api.get(`/products/${productId}/composition`),
  add:     (productId: number, data: object)            => api.post(`/products/${productId}/composition`, data),
  update:  (productId: number, lineId: number, data: object) => api.patch(`/products/${productId}/composition/${lineId}`, data),
  remove:  (productId: number, lineId: number)          => api.delete(`/products/${productId}/composition/${lineId}`),
}

// ─── Categories ───────────────────────────────────────────────────────────────
export const categoriesApi = {
  list:    ()                          => api.get('/categories'),
  create:  (data: object)              => api.post('/categories', data),
  update:  (id: number, data: object)  => api.patch(`/categories/${id}`, data),
  destroy: (id: number)                => api.delete(`/categories/${id}`),
}

// ─── Product Types ─────────────────────────────────────────────────────────────
export const productTypesApi = {
  list:    ()                          => api.get('/product-types'),
  get:     (id: number)                => api.get(`/product-types/${id}`),
  create:  (data: object)              => api.post('/product-types', data),
  update:  (id: number, data: object)  => api.patch(`/product-types/${id}`, data),
  destroy: (id: number)                => api.delete(`/product-types/${id}`),
}

// ─── Alerts ────────────────────────────────────────────────────────────────────
export const alertsApi = {
  stock:               ()               => api.get('/alerts/stock'),
  suggestions:         ()               => api.get('/alerts/suggestions'),
  anomalies:           (productId?: number) => api.get('/alerts/anomalies', { params: { product_id: productId } }),
  notify:              ()               => api.post('/alerts/notify'),
  commandesSuggerees:  ()               => api.get('/alerts/commandes-suggerees'),
}

// ─── Onboarding ───────────────────────────────────────────────────────────────
export const onboardingApi = {
  suggest:         (secteur: string)                          => api.post('/onboarding/suggest', { secteur }),
  suggestProducts: (secteur: string)                          => api.post('/onboarding/suggest-products', { secteur }),
  confirm:         (types: object[], products: object[] = []) => api.post('/onboarding/confirm', { types, products }),
}

// ─── Tables & Commandes (restauration) ───────────────────────────────────────
export const tablesApi = {
  list:    ()                          => api.get('/tables'),
  create:  (data: object)              => api.post('/tables', data),
  update:  (id: number, data: object)  => api.patch(`/tables/${id}`, data),
  destroy: (id: number)                => api.delete(`/tables/${id}`),
}

export const ordersApi = {
  list:             (params?: object)           => api.get('/orders', { params }),
  get:              (id: number)                => api.get(`/orders/${id}`),
  create:           (data: object)              => api.post('/orders', data),
  updateItems:      (id: number, data: object)  => api.patch(`/orders/${id}/items`, data),
  checkIngredients: (id: number)                => api.get(`/orders/${id}/check-ingredients`),
  sendKitchen:      (id: number, data?: object) => api.post(`/orders/${id}/send-kitchen`, data ?? {}),
  pay:              (id: number, data?: object) => api.post(`/orders/${id}/pay`, data ?? {}),
}

// ─── Consommation ingrédients (restauration) ─────────────────────────────────
export const consommationApi = {
  index:  (params?: object) => api.get('/consommation', { params }),
  export: (params?: object) => api.get('/consommation/export', { params, responseType: 'blob' }),
}

// ─── Suppléments (restauration) ───────────────────────────────────────────────
export const supplementsApi = {
  list:    ()                          => api.get('/supplements'),
  create:  (data: object)              => api.post('/supplements', data),
  update:  (id: number, data: object)  => api.patch(`/supplements/${id}`, data),
  destroy: (id: number)                => api.delete(`/supplements/${id}`),
}

// ─── Users ────────────────────────────────────────────────────────────────────
export const usersApi = {
  list:   ()                          => api.get('/users'),
  create: (data: object)              => api.post('/users', data),
  update: (id: number, data: object)  => api.patch(`/users/${id}`, data),
  delete: (id: number)                => api.delete(`/users/${id}`),
}

// ─── Super Admin ───────────────────────────────────────────────────────────────
export const superAdminApi = {
  dashboard:          ()                       => api.get('/super-admin/dashboard'),
  organisations:      ()                       => api.get('/super-admin/organisations'),
  users:              ()                       => api.get('/super-admin/users'),
  plans:              ()                       => api.get('/super-admin/plans'),
  createOrg:          (data: object)           => api.post('/super-admin/organisations', data),
  updateOrg:          (id: number, data: object)   => api.patch(`/super-admin/organisations/${id}`, data),
  updateUser:         (id: number, data: object)   => api.patch(`/super-admin/users/${id}`, data),
  destroyUser:        (id: number)                 => api.delete(`/super-admin/users/${id}`),
  destroyOrg:         (id: number)                 => api.delete(`/super-admin/organisations/${id}`),
  demoRequests:       ()                           => api.get('/super-admin/demo-requests'),
  updateDemoStatus:   (id: number, statut: string) => api.patch(`/super-admin/demo-requests/${id}`, { statut }),
}

// ─── Fournisseurs ─────────────────────────────────────────────────────────────
// ─── Plan usage ───────────────────────────────────────────────────────────────
export const planApi = {
  usage: () => api.get('/plan/usage'),
}

export const fournisseursApi = {
  list:    ()                          => api.get('/fournisseurs'),
  create:  (data: object)              => api.post('/fournisseurs', data),
  update:  (id: number, data: object)  => api.patch(`/fournisseurs/${id}`, data),
  destroy: (id: number)                => api.delete(`/fournisseurs/${id}`),
}

export const commandesFournisseurApi = {
  list:          (params?: object)           => api.get('/commandes-fournisseur', { params }),
  get:           (id: number)                => api.get(`/commandes-fournisseur/${id}`),
  create:        (data: object)              => api.post('/commandes-fournisseur', data),
  update:        (id: number, data: object)  => api.patch(`/commandes-fournisseur/${id}`, data),
  envoyer:       (id: number)                => api.post(`/commandes-fournisseur/${id}/envoyer`),
  receptionner:  (id: number, data: object)  => api.post(`/commandes-fournisseur/${id}/receptionner`, data),
  destroy:       (id: number)                => api.delete(`/commandes-fournisseur/${id}`),
}

// ─── Activity Logs ─────────────────────────────────────────────────────────────
export const activityLogsApi = {
  list:   (params?: object) => api.get('/activity-logs', { params }),
  export: (params?: object) => api.get('/activity-logs/export', { params, responseType: 'blob' }),
}

// ─── Dashboard Chaîne ─────────────────────────────────────────────────────────
export const chaineApi = {
  caParPoint:        ()                  => api.get('/chaine/ca-par-point'),
  stockParPoint:     (params?: object)   => api.get('/chaine/stock-par-point', { params }),
  topPlats:          ()                  => api.get('/chaine/top-plats'),
  transfertsRecents: ()                  => api.get('/chaine/transferts-recents'),
}

// ─── Transferts inter-PDV ─────────────────────────────────────────────────────
export const transfertsApi = {
  list:   (params?: object) => api.get('/transferts', { params }),
  get:    (id: number)      => api.get(`/transferts/${id}`),
  create: (data: object)    => api.post('/transferts', data),
}

// ─── Points de vente ──────────────────────────────────────────────────────────
export const pointsDeVenteApi = {
  list:     (params?: object)           => api.get('/points-de-vente', { params }),
  create:   (data: object)              => api.post('/points-de-vente', data),
  update:   (id: number, data: object)  => api.patch(`/points-de-vente/${id}`, data),
  destroy:  (id: number)                => api.delete(`/points-de-vente/${id}`),
  stock:    (id: number)                => api.get(`/points-de-vente/${id}/stock`),
  transfer: (data: object)              => api.post('/points-de-vente/transfer', data),
}

// ─── Public ────────────────────────────────────────────────────────────────────
export const publicApi = {
  sendDemoRequest: (data: object) => api.post('/demo-request', data),
  getPlans:        ()             => api.get('/plans'),
  menu:            (slug: string) => api.get(`/public/menu/${slug}`),
}
