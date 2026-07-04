import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'landing',
      component: () => import('@/views/LandingView.vue'),
      meta: { guest: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/onboarding',
      name: 'onboarding',
      component: () => import('@/views/OnboardingView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/app',
      component: () => import('@/components/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '',         name: 'dashboard',  component: () => import('@/views/DashboardView.vue') },
        { path: 'products', name: 'products',   component: () => import('@/views/ProductsView.vue') },
        { path: 'products/:id', name: 'product-detail', component: () => import('@/views/ProductDetailView.vue') },
        { path: 'menu',        name: 'menu',        component: () => import('@/views/MenuView.vue') },
        { path: 'supplements', name: 'supplements', component: () => import('@/views/SupplementsView.vue') },
        { path: 'tables',       name: 'tables',       component: () => import('@/views/TablesView.vue') },
        { path: 'consommation', name: 'consommation', component: () => import('@/views/ConsommationView.vue') },
        { path: 'caisse',    name: 'caisse',    component: () => import('@/views/CaisseView.vue') },
        { path: 'ventes',    name: 'ventes',    component: () => import('@/views/VentesView.vue') },
        { path: 'clients',   name: 'clients',   component: () => import('@/views/ClientsView.vue') },
        { path: 'movements',    name: 'movements',    component: () => import('@/views/MovementsView.vue') },
        { path: 'fournisseurs', name: 'fournisseurs', component: () => import('@/views/FournisseursView.vue') },
        { path: 'alerts',    name: 'alerts',    component: () => import('@/views/AlertsView.vue') },
        { path: 'config',    name: 'config',    component: () => import('@/views/ConfigView.vue') },
        { path: 'users',       name: 'users',       component: () => import('@/views/UsersView.vue') },
        { path: 'logs',        name: 'logs',        component: () => import('@/views/ActivityLogsView.vue') },
        { path: 'super-admin',    name: 'super-admin',    component: () => import('@/views/SuperAdminView.vue') },
        { path: 'points-de-vente', name: 'points-de-vente', component: () => import('@/views/PointsVenteView.vue') },
        { path: 'transferts',      name: 'transferts',      component: () => import('@/views/TransfertsView.vue') },
      ],
    },
    // Public digital menu — no auth, no sidebar
    {
      path: '/menu/:slug',
      name: 'public-menu',
      component: () => import('@/views/MenuPublicView.vue'),
    },
    { path: '/:pathMatch(.*)*', redirect: '/app' },
  ],
})

// Routes accessibles uniquement aux super_admin
const superAdminRoutes = new Set(['super-admin', 'users'])

// Routes tenant (catalogue, mouvements…) interdites au super_admin
const tenantRoutes = new Set(['dashboard', 'products', 'menu', 'supplements', 'tables', 'consommation', 'caisse', 'ventes', 'clients', 'product-detail', 'movements', 'fournisseurs', 'alerts', 'config', 'logs', 'points-de-vente', 'transferts'])

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  await auth.initPromise

  if (to.meta.requiresAuth && ! auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guest && auth.isAuthenticated && to.name !== 'landing') {
    return auth.isSuperAdmin ? { name: 'super-admin' } : { name: 'dashboard' }
  }

  if (auth.isAuthenticated) {
    // Super admin ne peut pas accéder aux routes tenant
    if (auth.isSuperAdmin && tenantRoutes.has(String(to.name))) {
      return { name: 'super-admin' }
    }

    // Utilisateurs normaux ne peuvent pas accéder aux routes super admin
    if (!auth.isSuperAdmin && superAdminRoutes.has(String(to.name)) && to.name !== 'users') {
      return { name: 'dashboard' }
    }

    // Onboarding
    if (auth.user?.organisation && auth.user.organisation.onboarding_complete === false && to.name !== 'onboarding') {
      return { name: 'onboarding' }
    }
  }
})

export default router
