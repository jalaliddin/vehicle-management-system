<script setup>
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'
import { computed } from 'vue'

const authStore = useAuthStore()
const route = useRoute()

const pageTitle = computed(() => {
  const titles = {
    dashboard: 'Dashboard',
    vehicles: 'Avtomobillar',
    'vehicles.create': "Yangi Avtomobil qo'shish",
    'vehicles.show': 'Avtomobil tafsilotlari',
    'vehicles.edit': 'Avtomobilni tahrirlash',
    drivers: 'Haydovchilar',
    'drivers.create': "Yangi Haydovchi qo'shish",
    'drivers.show': 'Haydovchi tafsilotlari',
    waybills: "Yo'l Varaqalari",
    'waybills.create': "Yangi Yo'l Varaqasi",
    'waybills.show': "Yo'l Varaqasi Tafsilotlari",
    'waybills.mechanic': 'Mexanik Tekshiruvi Navbati',
    'waybills.doctor': 'Shifokor Tekshiruvi Navbati',
    'fuel.monitoring': "Yoqilg'i Monitoring",
    'fuel.stations': "Yoqilg'i Shahobchalari",
    'fuel.transactions': "Yoqilg'i Harakatlari",
    organizations: 'Tashkilotlar',
    'fuel-norms': "Yoqilg'i Me'yorlari",
    reports: 'Hisobotlar',
  }
  return titles[route.name] || 'UTG Transport'
})
</script>

<template>
  <v-app-bar elevation="0" style="border-bottom: 1px solid rgba(0,0,0,0.08);" color="white">
    <v-app-bar-title>
      <span class="text-subtitle-1 font-weight-semibold">{{ pageTitle }}</span>
    </v-app-bar-title>
    <template #append>
      <div class="d-flex align-center gap-2 mr-3">
        <v-chip color="primary" size="small" variant="tonal">
          <v-icon start size="14">mdi-calendar</v-icon>
          {{ new Date().toLocaleDateString('uz-UZ') }}
        </v-chip>
        <v-avatar color="primary" size="32">
          <span class="text-white text-caption font-weight-bold">
            {{ (authStore.user?.name || '').split(' ').map(n => n[0]).join('').slice(0, 2) }}
          </span>
        </v-avatar>
      </div>
    </template>
  </v-app-bar>
</template>
