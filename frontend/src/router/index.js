import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },

      { path: 'vehicles', name: 'vehicles', component: () => import('@/views/vehicles/VehicleListView.vue') },
      { path: 'vehicles/create', name: 'vehicles.create', component: () => import('@/views/vehicles/VehicleFormView.vue') },
      { path: 'vehicles/:id', name: 'vehicles.show', component: () => import('@/views/vehicles/VehicleDetailView.vue') },
      { path: 'vehicles/:id/edit', name: 'vehicles.edit', component: () => import('@/views/vehicles/VehicleFormView.vue') },

      { path: 'drivers', name: 'drivers', component: () => import('@/views/drivers/DriverListView.vue') },
      { path: 'drivers/create', name: 'drivers.create', component: () => import('@/views/drivers/DriverFormView.vue') },
      { path: 'drivers/:id', name: 'drivers.show', component: () => import('@/views/drivers/DriverDetailView.vue') },
      { path: 'drivers/:id/edit', name: 'drivers.edit', component: () => import('@/views/drivers/DriverFormView.vue') },

      { path: 'waybills', name: 'waybills', component: () => import('@/views/waybills/WaybillListView.vue') },
      { path: 'waybills/create', name: 'waybills.create', component: () => import('@/views/waybills/WaybillCreateView.vue') },
      { path: 'waybills/mechanic', name: 'waybills.mechanic', component: () => import('@/views/waybills/MechanicQueueView.vue') },
      { path: 'waybills/doctor', name: 'waybills.doctor', component: () => import('@/views/waybills/DoctorQueueView.vue') },
      { path: 'waybills/:id', name: 'waybills.show', component: () => import('@/views/waybills/WaybillDetailView.vue') },

      { path: 'fuel/monitoring', name: 'fuel.monitoring', component: () => import('@/views/fuel/FuelMonitoringView.vue') },
      { path: 'fuel/stations', name: 'fuel.stations', component: () => import('@/views/fuel/FuelStationView.vue') },
      { path: 'fuel/transactions', name: 'fuel.transactions', component: () => import('@/views/fuel/FuelTransactionView.vue') },

      { path: 'organizations', name: 'organizations', component: () => import('@/views/organizations/OrganizationView.vue') },
      { path: 'fuel-norms', name: 'fuel-norms', component: () => import('@/views/FuelNormView.vue') },
      { path: 'reports', name: 'reports', component: () => import('@/views/reports/ReportsView.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login'
  if (to.meta.guest && auth.isAuthenticated) return '/'
})

export default router
