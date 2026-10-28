<script setup>
import { computed, ref } from 'vue'
import { useRouter, useLink } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
// Phones and small tablets start with the narrow rail so pages keep their width.
const rail = ref(useDisplay().smAndDown.value)

const userInitials = computed(() =>
  (authStore.user?.name || '').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()
)

const roleLabel = computed(() => authStore.user?.role_name || authStore.user?.role)

// Each item lists the permission(s) it needs; sections left empty are hidden.
const sections = computed(() => {
  const all = [
    {
      label: 'Asosiy',
      items: [
        { title: 'Dashboard', to: '/', icon: 'mdi-view-dashboard', exact: true },
      ],
    },
    {
      label: 'Transport',
      items: [
        { title: 'Avtomobillar', to: '/vehicles', icon: 'mdi-car', permission: 'vehicles.view' },
        { title: 'Haydovchilar', to: '/drivers', icon: 'mdi-account-tie', permission: 'drivers.view' },
      ],
    },
    {
      label: "Yo'l varaqalari",
      items: [
        { title: "Varaqalar ro'yxati", to: '/waybills', icon: 'mdi-file-document-multiple', permission: 'waybills.view' },
        { title: 'Yangi varaq', to: '/waybills/create', icon: 'mdi-plus-circle', permission: 'waybills.create' },
        { title: 'Mexanik navbati', to: '/waybills/mechanic', icon: 'mdi-wrench', permission: 'waybills.mechanic_check' },
        { title: 'Shifokor navbati', to: '/waybills/doctor', icon: 'mdi-stethoscope', permission: 'waybills.doctor_check' },
      ],
    },
    {
      label: "Yoqilg'i",
      items: [
        { title: 'Monitoring', to: '/fuel/monitoring', icon: 'mdi-gauge-full', permission: 'fuel.view' },
        { title: "Yoqilg'i quyish", to: '/fuel/station', icon: 'mdi-fuel', permission: 'fuel.transactions.create' },
        { title: 'Shahobchalar', to: '/fuel/stations', icon: 'mdi-gas-station', permission: 'fuel.view' },
        { title: 'Smenalar', to: '/fuel/shifts', icon: 'mdi-account-clock', permission: 'fuel.view' },
        { title: 'Harakatlar', to: '/fuel/transactions', icon: 'mdi-swap-horizontal-bold', permission: 'fuel.view' },
        { title: 'Yetkazib beruvchilar', to: '/fuel/suppliers', icon: 'mdi-truck-delivery', permission: 'fuel.stations.manage' },
        { title: 'Hisobotlar', to: '/fuel/reports', icon: 'mdi-chart-line', permission: 'fuel.view' },
      ],
    },
    {
      label: 'Kadrlar',
      items: [
        { title: 'Xodimlar', to: '/employees', icon: 'mdi-account-multiple', permission: 'employees.view' },
        { title: 'Tuzilma', to: '/org-structure', icon: 'mdi-sitemap', permission: 'org_structure.manage' },
        { title: 'Kadrlar hisobotlari', to: '/hr/reports', icon: 'mdi-chart-box', permission: ['employees.view', 'ohs.view'] },
      ],
    },
    {
      label: 'Mehnat muhofazasi',
      items: [
        { title: 'Ogohlantirishlar', to: '/ohs/alerts', icon: 'mdi-alert', permission: ['ohs.view', 'employees.view'] },
        { title: "Tibbiy ko'riklar", to: '/ohs/medical', icon: 'mdi-heart-pulse', permission: ['ohs.view', 'ohs.medical.view'] },
        { title: "Yo'riqnomalar", to: '/ohs/briefings', icon: 'mdi-clipboard-check', permission: 'ohs.view' },
        { title: 'Maxsus kiyim va SHHV', to: '/ohs/ppe', icon: 'mdi-hard-hat', permission: 'ohs.view' },
      ],
    },
    {
      label: 'Boshqaruv',
      items: [
        { title: "Yoqilg'i me'yorlari", to: '/fuel-norms', icon: 'mdi-clipboard-list', permission: 'fuel_norms.view' },
        { title: 'Tashkilotlar', to: '/organizations', icon: 'mdi-domain', permission: 'organizations.manage' },
        { title: 'Foydalanuvchilar', to: '/users', icon: 'mdi-account-group', permission: ['users.view', 'users.manage'] },
        { title: 'Rollar va huquqlar', to: '/roles', icon: 'mdi-shield-account', superAdmin: true },
        { title: 'Amallar jurnali', to: '/audit', icon: 'mdi-history', permission: 'audit.view' },
      ],
    },
    {
      label: 'Analitika',
      items: [
        { title: 'Hisobotlar', to: '/reports', icon: 'mdi-chart-bar', permission: 'reports.view' },
      ],
    },
  ]

  const visible = (item) => {
    if (item.superAdmin) return authStore.isSuperAdmin
    if (!item.permission) return true
    return authStore.canAny([item.permission].flat())
  }

  return all
    .map(section => ({ ...section, items: section.items.filter(visible) }))
    .filter(section => section.items.length)
})

async function logout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <v-navigation-drawer
    permanent
    :rail="rail"
    :width="264"
    class="utg-sidebar"
  >
    <!-- Header -->
    <div class="sidebar-header" :class="{ 'rail-header': rail }">
      <div class="brand" :class="{ 'rail-brand': rail }">
        <img src="/logo.svg" alt="UTG" :class="rail ? 'brand-logo-rail' : 'brand-logo'" />
        <div v-if="!rail" class="brand-text">
          <span class="brand-name">UTG Transport</span>
          <span class="brand-sub">Avtotransport tizimi</span>
        </div>
      </div>
      <button class="toggle-btn" @click="rail = !rail">
        <v-icon size="18" color="#94A3B8">{{ rail ? 'mdi-chevron-right' : 'mdi-chevron-left' }}</v-icon>
      </button>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
      <div v-for="section in sections" :key="section.label" class="nav-section">
        <div v-if="!rail" class="nav-section-label">{{ section.label }}</div>
        <div v-else class="nav-section-divider" />

        <RouterLink
          v-for="item in section.items"
          :key="item.to"
          :to="item.to"
          custom
          v-slot="{ isActive, isExactActive, navigate }"
        >
          <button
            class="nav-item"
            :class="{ active: item.exact ? isExactActive : isActive }"
            @click="navigate"
          >
            <div class="nav-icon">
              <v-icon size="18">{{ item.icon }}</v-icon>
            </div>
            <span v-if="!rail" class="nav-label">{{ item.title }}</span>
            <v-tooltip v-if="rail" activator="parent" location="end" :text="item.title" />
          </button>
        </RouterLink>
      </div>
    </div>

    <!-- Footer -->
    <template #append>
      <div class="sidebar-footer">
        <div v-if="!rail" class="user-info">
          <div class="user-avatar">{{ userInitials }}</div>
          <div class="user-text">
            <span class="user-name">{{ authStore.user?.name }}</span>
            <span class="user-role">{{ roleLabel }}</span>
          </div>
          <button class="logout-btn" @click="logout">
            <v-icon size="16" color="#64748B">mdi-logout</v-icon>
          </button>
        </div>
        <div v-else class="user-rail">
          <button class="user-avatar" @click="logout">
            {{ userInitials }}
            <v-tooltip activator="parent" location="end" text="Chiqish" />
          </button>
        </div>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<style scoped>
.utg-sidebar {
  background: #0F172A !important;
  border-right: 1px solid rgba(255,255,255,0.06) !important;
}

/* Header */
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  min-height: 64px;
}
.rail-header { justify-content: center; flex-direction: column; gap: 8px; }

.brand { display: flex; align-items: center; gap: 10px; overflow: hidden; }
.rail-brand { flex-direction: column; align-items: center; }

.brand-logo {
  height: 36px; width: auto;
  flex-shrink: 0;
  filter: brightness(0) invert(1);
  opacity: 0.92;
}
.brand-logo-rail {
  height: 28px; width: auto;
  flex-shrink: 0;
  filter: brightness(0) invert(1);
  opacity: 0.92;
}

.brand-text { display: flex; flex-direction: column; overflow: hidden; }
.brand-name { color: #F1F5F9; font-size: 14px; font-weight: 700; white-space: nowrap; }
.brand-sub  { color: #64748B; font-size: 11px; white-space: nowrap; }

.toggle-btn {
  background: none; border: none; cursor: pointer; padding: 4px;
  border-radius: 8px; display: flex; align-items: center; justify-content: center;
  transition: background 0.15s; flex-shrink: 0;
}
.toggle-btn:hover { background: rgba(255,255,255,0.06); }

/* Nav */
.sidebar-nav {
  flex: 1;
  padding: 12px 10px;
  overflow-y: auto;
}

.nav-section { margin-bottom: 4px; }

.nav-section-label {
  padding: 10px 10px 4px;
  font-size: 10px; font-weight: 700; letter-spacing: 1.2px;
  text-transform: uppercase; color: #334155;
}

.nav-section-divider {
  height: 1px; background: rgba(255,255,255,0.06);
  margin: 8px 4px;
}

.nav-item {
  width: 100%; border: none; background: none; cursor: pointer;
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 10px;
  text-align: left; transition: background 0.15s, color 0.15s;
  color: #64748B;
  margin-bottom: 2px;
}
.nav-item:hover { background: rgba(255,255,255,0.06); color: #CBD5E1; }
.nav-item.active {
  background: rgba(37,99,235,0.18);
  color: #93C5FD;
}
.nav-item.active .nav-icon { color: #60A5FA; }

.nav-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: background 0.15s;
}
.nav-item:hover .nav-icon { background: rgba(255,255,255,0.06); }
.nav-item.active .nav-icon { background: rgba(37,99,235,0.25); }

.nav-label { font-size: 13px; font-weight: 500; }

/* Footer */
.sidebar-footer {
  border-top: 1px solid rgba(255,255,255,0.06);
  padding: 12px 10px;
}

.user-info {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 6px; border-radius: 10px;
}

.user-avatar {
  width: 34px; height: 34px; border-radius: 10px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: white; font-size: 12px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; border: none; cursor: pointer;
}

.user-text {
  flex: 1; overflow: hidden; display: flex; flex-direction: column;
}
.user-name { color: #CBD5E1; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role { color: #475569; font-size: 11px; }

.logout-btn {
  background: none; border: none; cursor: pointer; padding: 6px;
  border-radius: 8px; display: flex; align-items: center; transition: background 0.15s;
}
.logout-btn:hover { background: rgba(255,255,255,0.06); }

.user-rail { display: flex; justify-content: center; }
</style>
