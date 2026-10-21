import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/w/:id',
    name: 'waybill.public',
    component: () => import('@/views/waybills/PublicWaybillView.vue'),
    meta: { public: true },
  },
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

      { path: 'vehicles', name: 'vehicles', component: () => import('@/views/vehicles/VehicleListView.vue'), meta: { permission: 'vehicles.view' } },
      { path: 'vehicles/create', name: 'vehicles.create', component: () => import('@/views/vehicles/VehicleFormView.vue'), meta: { permission: 'vehicles.create' } },
      { path: 'vehicles/:id', name: 'vehicles.show', component: () => import('@/views/vehicles/VehicleDetailView.vue'), meta: { permission: 'vehicles.view' } },
      { path: 'vehicles/:id/edit', name: 'vehicles.edit', component: () => import('@/views/vehicles/VehicleFormView.vue'), meta: { permission: 'vehicles.update' } },

      { path: 'drivers', name: 'drivers', component: () => import('@/views/drivers/DriverListView.vue'), meta: { permission: 'drivers.view' } },
      { path: 'drivers/create', name: 'drivers.create', component: () => import('@/views/drivers/DriverFormView.vue'), meta: { permission: 'drivers.create' } },
      { path: 'drivers/:id', name: 'drivers.show', component: () => import('@/views/drivers/DriverDetailView.vue'), meta: { permission: 'drivers.view' } },
      { path: 'drivers/:id/edit', name: 'drivers.edit', component: () => import('@/views/drivers/DriverFormView.vue'), meta: { permission: 'drivers.update' } },

      { path: 'waybills', name: 'waybills', component: () => import('@/views/waybills/WaybillListView.vue'), meta: { permission: 'waybills.view' } },
      { path: 'waybills/create', name: 'waybills.create', component: () => import('@/views/waybills/WaybillCreateView.vue'), meta: { permission: 'waybills.create' } },
      { path: 'waybills/mechanic', name: 'waybills.mechanic', component: () => import('@/views/waybills/MechanicQueueView.vue'), meta: { permission: 'waybills.mechanic_check' } },
      { path: 'waybills/doctor', name: 'waybills.doctor', component: () => import('@/views/waybills/DoctorQueueView.vue'), meta: { permission: 'waybills.doctor_check' } },
      { path: 'waybills/:id', name: 'waybills.show', component: () => import('@/views/waybills/WaybillDetailView.vue'), meta: { permission: 'waybills.view' } },

      { path: 'fuel/monitoring', name: 'fuel.monitoring', component: () => import('@/views/fuel/FuelMonitoringView.vue'), meta: { permission: 'fuel.view' } },
      { path: 'fuel/stations', name: 'fuel.stations', component: () => import('@/views/fuel/FuelStationView.vue'), meta: { permission: 'fuel.view' } },
      { path: 'fuel/transactions', name: 'fuel.transactions', component: () => import('@/views/fuel/FuelTransactionView.vue'), meta: { permission: 'fuel.view' } },

      { path: 'organizations', name: 'organizations', component: () => import('@/views/organizations/OrganizationView.vue'), meta: { permission: 'organizations.manage' } },
      { path: 'fuel-norms', name: 'fuel-norms', component: () => import('@/views/FuelNormView.vue'), meta: { permission: 'fuel_norms.view' } },
      { path: 'reports', name: 'reports', component: () => import('@/views/reports/ReportsView.vue'), meta: { permission: 'reports.view' } },
      { path: 'users', name: 'users', component: () => import('@/views/users/UsersView.vue'), meta: { permission: ['users.view', 'users.manage'] } },
      { path: 'roles', name: 'roles', component: () => import('@/views/roles/RolesView.vue'), meta: { superAdmin: true } },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (to.meta.public) return
  if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login'
  if (to.meta.guest && auth.isAuthenticated) return '/'

  if (auth.isAuthenticated && !auth.refreshed) {
    try {
      await auth.fetchMe()
    } catch {
      // Offline or server error: keep the cached user; a 401 is handled by the API client.
    }
  }

  if (to.meta.superAdmin && !auth.isSuperAdmin) return { name: 'dashboard' }
  if (to.meta.permission && !auth.canAny([to.meta.permission].flat())) return { name: 'dashboard' }
})

export default router
