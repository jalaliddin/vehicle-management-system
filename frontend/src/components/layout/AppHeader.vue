<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'
import { computed } from 'vue'

const authStore = useAuthStore()
const route = useRoute()

const pageTitles = {
  dashboard: 'Dashboard',
  vehicles: 'Avtomobillar',
  'vehicles.create': 'Yangi avtomobil',
  'vehicles.show': 'Avtomobil',
  'vehicles.edit': 'Tahrirlash',
  drivers: 'Haydovchilar',
  'drivers.create': 'Yangi haydovchi',
  'drivers.show': 'Haydovchi',
  'drivers.edit': 'Tahrirlash',
  waybills: "Yo'l varaqalari",
  'waybills.create': 'Yangi varaq',
  'waybills.show': 'Varaq tafsiloti',
  'waybills.mechanic': 'Mexanik navbati',
  'waybills.doctor': 'Shifokor navbati',
  'fuel.monitoring': 'Yoqilg\'i monitoring',
  'fuel.stations': 'Shahobchalar',
  'fuel.transactions': 'Harakatlar',
  organizations: 'Tashkilotlar',
  'fuel-norms': "Yoqilg'i me'yorlari",
  reports: 'Hisobotlar',
}

const pageTitle = computed(() => pageTitles[route.name] || 'UTG Transport')

const userInitials = computed(() =>
  (authStore.user?.name || '').split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()
)

const today = new Date().toLocaleDateString('uz-UZ', {
  weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
})
</script>

<template>
  <v-app-bar
    elevation="0"
    color="white"
    height="64"
    style="border-bottom: 1px solid rgba(0,0,0,0.07);"
  >
    <div class="d-flex align-center ga-2 pl-6 flex-grow-1">
      <span class="page-title">{{ pageTitle }}</span>
    </div>

    <template #append>
      <div class="d-flex align-center ga-3 pr-5">
        <div class="date-chip">
          <v-icon size="14" style="opacity:0.6;">mdi-calendar-outline</v-icon>
          <span>{{ today }}</span>
        </div>

        <div class="user-badge">
          <div class="user-avatar-hdr">{{ userInitials }}</div>
          <div class="user-info-hdr">
            <span class="user-name-hdr">{{ authStore.user?.name }}</span>
          </div>
        </div>
      </div>
    </template>
  </v-app-bar>
</template>

<style scoped>
.page-title {
  font-size: 18px;
  font-weight: 700;
  color: #0F172A;
  letter-spacing: -0.3px;
}

.date-chip {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 12px;
  background: #F1F5F9;
  border-radius: 10px;
  font-size: 12px;
  color: #64748B;
  font-weight: 500;
}

.user-badge {
  display: flex; align-items: center; gap: 10px;
  padding: 6px 12px 6px 6px;
  background: #F8FAFC;
  border: 1px solid rgba(0,0,0,0.07);
  border-radius: 12px;
}

.user-avatar-hdr {
  width: 30px; height: 30px; border-radius: 8px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: white; font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}

.user-info-hdr { display: flex; flex-direction: column; }
.user-name-hdr { font-size: 13px; font-weight: 600; color: #0F172A; }
</style>
