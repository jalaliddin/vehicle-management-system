<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const rail = ref(false)

const userInitials = computed(() => {
  const name = authStore.user?.name || ''
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()
})

const roleLabel = computed(() => {
  const labels = {
    superadmin: 'Super Admin',
    hq_dispatcher: 'Markaziy Dispetcher',
    dispatcher: 'Dispetcher',
    mechanic: 'Mexanik',
    doctor: 'Shifokor',
    viewer: "Ko'ruvchi",
  }
  return labels[authStore.user?.role] || authStore.user?.role
})

const menuItems = computed(() => {
  const role = authStore.user?.role
  const items = [
    { type: 'header', title: 'Asosiy' },
    { title: 'Dashboard', to: '/', icon: 'mdi-view-dashboard-outline' },
    { type: 'header', title: 'Transport' },
    { title: 'Avtomobillar', to: '/vehicles', icon: 'mdi-car-multiple' },
    { title: 'Haydovchilar', to: '/drivers', icon: 'mdi-account-group-outline' },
    { type: 'header', title: "Yo'l varaqalari" },
    { title: "Yo'l varaqalari", to: '/waybills', icon: 'mdi-file-document-multiple-outline' },
  ]

  if (['dispatcher', 'hq_dispatcher', 'superadmin'].includes(role)) {
    items.push({ title: 'Yangi varaq', to: '/waybills/create', icon: 'mdi-plus-circle-outline' })
  }
  if (role === 'mechanic') {
    items.push({ title: 'Mexanik navbati', to: '/waybills/mechanic', icon: 'mdi-wrench-outline' })
  }
  if (role === 'doctor') {
    items.push({ title: 'Shifokor navbati', to: '/waybills/doctor', icon: 'mdi-stethoscope' })
  }

  items.push(
    { type: 'header', title: "Yoqilg'i" },
    { title: 'Monitoring', to: '/fuel/monitoring', icon: 'mdi-gauge' },
    { title: 'Shahobchalar', to: '/fuel/stations', icon: 'mdi-gas-station-outline' },
    { title: 'Harakatlar', to: '/fuel/transactions', icon: 'mdi-swap-horizontal' },
    { type: 'header', title: 'Boshqaruv' },
    { title: "Me'yorlar", to: '/fuel-norms', icon: 'mdi-clipboard-list-outline' },
    { title: 'Hisobotlar', to: '/reports', icon: 'mdi-chart-bar' },
  )

  if (['superadmin', 'hq_dispatcher'].includes(role)) {
    items.push({ title: 'Tashkilotlar', to: '/organizations', icon: 'mdi-office-building-outline' })
  }

  return items
})

async function logout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <v-navigation-drawer
    v-model:rail="rail"
    permanent
    :width="256"
    style="background: #0D1B2A; border-right: 1px solid #1E3A5F;"
  >
    <div class="d-flex align-center gap-3 pa-4">
      <v-avatar color="#1565C0" size="38" rounded="lg">
        <v-icon color="white" size="20">mdi-gas-station</v-icon>
      </v-avatar>
      <div v-if="!rail">
        <div class="text-white font-weight-bold" style="font-size:14px; line-height:1.2;">UTG Transport</div>
        <div style="color:#90CAF9; font-size:11px;">Avtotransport tizimi</div>
      </div>
      <v-spacer v-if="!rail" />
      <v-btn
        v-if="!rail"
        :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
        variant="text"
        size="x-small"
        style="color:#546E7A;"
        @click="rail = !rail"
      />
    </div>

    <v-divider style="border-color:#1E3A5F;" />

    <v-list density="compact" nav class="px-2 mt-1">
      <template v-for="item in menuItems" :key="item.title">
        <div
          v-if="item.type === 'header' && !rail"
          class="text-uppercase px-2 py-1 mt-2"
          style="color:#37474F; font-size:10px; font-weight:700; letter-spacing:1.5px;"
        >
          {{ item.title }}
        </div>
        <v-list-item
          v-else-if="item.to"
          :to="item.to"
          :prepend-icon="item.icon"
          :title="!rail ? item.title : ''"
          rounded="lg"
          class="mb-1 sidebar-item"
          active-class="sidebar-item-active"
          exact
        />
      </template>
    </v-list>

    <template #append>
      <v-divider style="border-color:#1E3A5F;" />
      <div
        class="d-flex align-center gap-2 pa-3"
        style="cursor:pointer;"
        @click="logout"
      >
        <v-avatar color="#1565C0" size="32">
          <span class="text-white" style="font-size:11px; font-weight:700;">{{ userInitials }}</span>
        </v-avatar>
        <div v-if="!rail" class="flex-grow-1 overflow-hidden">
          <div class="text-white" style="font-size:12px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ authStore.user?.name }}</div>
          <div style="color:#90CAF9; font-size:10px;">{{ roleLabel }}</div>
        </div>
        <v-icon v-if="!rail" size="16" style="color:#546E7A;">mdi-logout</v-icon>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<style scoped>
.sidebar-item { color: #78909C !important; }
.sidebar-item:hover { background: #1A2D42 !important; color: #FFFFFF !important; }
.sidebar-item-active { background: #1565C0 !important; color: #FFFFFF !important; }
:deep(.v-list-item__prepend .v-icon) { color: inherit !important; }
</style>
