<script setup>
import { ref, onMounted, computed } from 'vue'
import { dashboardApi } from '@/services/api'
import { useRouter } from 'vue-router'

const router = useRouter()
const stats = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await dashboardApi.stats()
    stats.value = res.data
  } finally {
    loading.value = false
  }
})

const kpiCards = computed(() => {
  if (!stats.value) return []
  return [
    { label: 'Jami Avtomobillar', value: stats.value.total_vehicles, icon: 'mdi-car-multiple', color: '#1565C0', sub: `${stats.value.active_vehicles} faol` },
    { label: 'Haydovchilar', value: stats.value.total_drivers, icon: 'mdi-account-group-outline', color: '#2E7D32', sub: `${stats.value.active_drivers} faol` },
    { label: "Bugungi Yo'l Varaqalari", value: stats.value.today_waybills, icon: 'mdi-file-document-outline', color: '#0288D1', sub: `${stats.value.pending_waybills} kutmoqda` },
    { label: 'Nosoz Avtomobillar', value: stats.value.broken_vehicles, icon: 'mdi-car-wrench', color: '#C62828', sub: `${stats.value.maintenance_vehicles} ta'mirda` },
  ]
})

const statusLabel = (status) => {
  const labels = {
    draft: 'Yaratilgan', mechanic_check: 'Mexanik kutmoqda', mechanic_ok: 'Mexanik OK',
    doctor_check: 'Shifokor kutmoqda', doctor_ok: 'Shifokor OK', hq_review: 'HQ ko\'rib chiqmoqda',
    approved: 'Tasdiqlandi', in_progress: "Yo'lda", completed: 'Bajarildi', cancelled: 'Bekor',
  }
  return labels[status] || status
}

const statusColor = (status) => {
  const colors = {
    draft: 'grey', mechanic_check: 'warning', doctor_check: 'warning', hq_review: 'warning',
    mechanic_ok: 'info', doctor_ok: 'info', approved: 'success', in_progress: 'success',
    completed: 'primary', cancelled: 'error',
  }
  return colors[status] || 'grey'
}

const fuelPct = (station) => {
  if (!station.capacity) return 0
  return Math.round((station.current_balance / station.capacity) * 100)
}
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row v-if="loading">
      <v-col v-for="i in 4" :key="i" cols="12" sm="6" md="3">
        <v-skeleton-loader type="card" rounded="xl" />
      </v-col>
    </v-row>

    <template v-else-if="stats">
      <!-- KPI Cards -->
      <v-row class="mb-5">
        <v-col v-for="card in kpiCards" :key="card.label" cols="12" sm="6" md="3">
          <v-card rounded="xl" class="kpi-card border pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-h4 font-weight-bold" :style="`color:${card.color}`">{{ card.value }}</div>
                <div class="text-body-2 text-medium-emphasis mt-1">{{ card.label }}</div>
                <div class="text-caption mt-1" :style="`color:${card.color}`">{{ card.sub }}</div>
              </div>
              <v-avatar :color="card.color + '20'" size="56" rounded="xl">
                <v-icon :color="card.color" size="28">{{ card.icon }}</v-icon>
              </v-avatar>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <!-- Yo'ldagi avtomobillar -->
        <v-col cols="12" md="5">
          <v-card rounded="xl" class="border" height="100%">
            <v-card-title class="pa-5 pb-2 d-flex align-center gap-2">
              <v-icon color="success" size="20">mdi-map-marker-path</v-icon>
              <span class="text-body-1 font-weight-semibold">Yo'lda bo'lgan avtomobillar</span>
              <v-chip color="success" size="x-small" class="ml-auto">{{ stats.active_waybills_list?.length || 0 }}</v-chip>
            </v-card-title>
            <v-card-text class="pt-0">
              <div v-if="!stats.active_waybills_list?.length" class="text-center py-6 text-medium-emphasis">
                <v-icon size="40" color="grey-lighten-2">mdi-car-off</v-icon>
                <p class="mt-2 text-caption">Hozir yo'lda hech kim yo'q</p>
              </div>
              <v-list v-else density="compact">
                <v-list-item
                  v-for="wb in stats.active_waybills_list"
                  :key="wb.id"
                  :title="wb.vehicle?.state_number"
                  :subtitle="`${wb.driver?.full_name} → ${wb.destination}`"
                  rounded="lg"
                  class="mb-1"
                  style="background:#F5F7FA"
                  @click="router.push(`/waybills/${wb.id}`)"
                >
                  <template #prepend>
                    <v-avatar color="success" size="34" rounded="lg" class="mr-2">
                      <v-icon color="white" size="16">mdi-car</v-icon>
                    </v-avatar>
                  </template>
                  <template #append>
                    <v-chip color="success" size="x-small">Yo'lda</v-chip>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Yoqilg'i holati -->
        <v-col cols="12" md="7">
          <v-card rounded="xl" class="border">
            <v-card-title class="pa-5 pb-2 d-flex align-center gap-2">
              <v-icon color="warning" size="20">mdi-fuel</v-icon>
              <span class="text-body-1 font-weight-semibold">Yoqilg'i holati</span>
              <v-btn variant="text" size="x-small" color="primary" class="ml-auto" @click="router.push('/fuel/monitoring')">
                Barchasi <v-icon end size="14">mdi-arrow-right</v-icon>
              </v-btn>
            </v-card-title>
            <v-card-text class="pt-0">
              <v-row>
                <v-col
                  v-for="station in (stats.fuel_stations || []).slice(0, 6)"
                  :key="station.id"
                  cols="6" sm="4"
                >
                  <div
                    class="rounded-lg pa-3"
                    :style="`background:${fuelPct(station) < 20 ? '#FFEBEE' : fuelPct(station) < 50 ? '#FFF8E1' : '#F1F8E9'};`"
                  >
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-caption font-weight-bold" style="font-size:10px;">
                        {{ station.organization?.short_name }}
                      </span>
                      <v-chip
                        :color="fuelPct(station) < 20 ? 'error' : fuelPct(station) < 50 ? 'warning' : 'success'"
                        size="x-small" variant="flat"
                      >
                        {{ fuelPct(station) }}%
                      </v-chip>
                    </div>
                    <v-progress-linear
                      :model-value="fuelPct(station)"
                      :color="fuelPct(station) < 20 ? 'error' : fuelPct(station) < 50 ? 'warning' : 'success'"
                      rounded height="6" bg-color="white"
                    />
                    <div class="text-caption text-medium-emphasis mt-1" style="font-size:10px;">
                      {{ Number(station.current_balance).toLocaleString() }} L
                    </div>
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Oxirgi varaqalar -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" class="border">
            <v-card-title class="pa-5 pb-2 d-flex align-center gap-2">
              <v-icon color="primary" size="20">mdi-file-clock-outline</v-icon>
              <span class="text-body-1 font-weight-semibold">Oxirgi yo'l varaqalari</span>
              <v-btn variant="text" size="x-small" color="primary" class="ml-auto" @click="router.push('/waybills')">
                Barchasi <v-icon end size="14">mdi-arrow-right</v-icon>
              </v-btn>
            </v-card-title>
            <v-card-text class="pt-0">
              <v-list density="compact">
                <v-list-item
                  v-for="wb in (stats.recent_waybills || []).slice(0, 8)"
                  :key="wb.id"
                  rounded="lg"
                  class="mb-1"
                  @click="router.push(`/waybills/${wb.id}`)"
                >
                  <template #prepend>
                    <v-avatar :color="statusColor(wb.status)" size="8" class="mr-3" style="border-radius:50%;" />
                  </template>
                  <v-list-item-title class="text-body-2">{{ wb.waybill_number }}</v-list-item-title>
                  <v-list-item-subtitle>{{ wb.driver?.full_name }} — {{ wb.vehicle?.state_number }}</v-list-item-subtitle>
                  <template #append>
                    <v-chip :color="statusColor(wb.status)" size="x-small" variant="tonal">
                      {{ statusLabel(wb.status) }}
                    </v-chip>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Tashkilotlar kesimida -->
        <v-col cols="12" md="6">
          <v-card rounded="xl" class="border">
            <v-card-title class="pa-5 pb-2 d-flex align-center gap-2">
              <v-icon color="error" size="20">mdi-car-wrench</v-icon>
              <span class="text-body-1 font-weight-semibold">Nosoz avtomobillar</span>
            </v-card-title>
            <v-card-text class="pt-0">
              <v-list density="compact">
                <v-list-item
                  v-for="org in (stats.vehicles_by_org || []).filter(o => o.broken_vehicles_count > 0)"
                  :key="org.id"
                  rounded="lg"
                >
                  <v-list-item-title class="text-body-2">{{ org.short_name || org.name }}</v-list-item-title>
                  <v-list-item-subtitle>{{ org.vehicles_count }} ta jami avtomobil</v-list-item-subtitle>
                  <template #append>
                    <v-chip color="error" size="small" variant="flat">
                      {{ org.broken_vehicles_count }} nosoz
                    </v-chip>
                  </template>
                </v-list-item>
                <div v-if="!(stats.vehicles_by_org || []).filter(o => o.broken_vehicles_count > 0).length" class="text-center py-4 text-success text-caption">
                  <v-icon color="success">mdi-check-circle</v-icon> Hamma avtomobil ishlamoqda
                </div>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>
