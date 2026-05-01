<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { vehicleApi } from '@/services/api'

const router = useRouter()
const route = useRoute()
const vehicle = ref(null)
const loading = ref(true)

const statusLabel = (s) => ({ active: 'Faol', broken: 'Nosoz', maintenance: "Ta'mirda", inactive: 'Faol emas' }[s] || s)
const statusColor = (s) => ({ active: 'success', broken: 'error', maintenance: 'warning', inactive: 'grey' }[s] || 'grey')

onMounted(async () => {
  const res = await vehicleApi.show(route.params.id)
  vehicle.value = res.data
  loading.value = false
})
</script>

<template>
  <v-container fluid class="pa-6" style="max-width:900px;">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-4" @click="router.back()">Orqaga</v-btn>

    <v-skeleton-loader v-if="loading" type="article" rounded="xl" />

    <template v-else-if="vehicle">
      <v-card rounded="xl" class="border mb-4">
        <v-card-text class="pa-6">
          <div class="d-flex align-center gap-4 mb-4">
            <v-avatar color="primary" size="60" rounded="xl">
              <v-icon color="white" size="30">mdi-car</v-icon>
            </v-avatar>
            <div>
              <h2 class="text-h5 font-weight-bold">{{ vehicle.state_number }}</h2>
              <p class="text-medium-emphasis">{{ vehicle.model }} ({{ vehicle.manufacture_year }})</p>
            </div>
            <v-spacer />
            <v-chip :color="statusColor(vehicle.status)" variant="flat">{{ statusLabel(vehicle.status) }}</v-chip>
            <v-btn icon variant="text" color="primary" @click="router.push(`/vehicles/${vehicle.id}/edit`)">
              <v-icon>mdi-pencil</v-icon>
            </v-btn>
          </div>

          <v-divider class="mb-4" />

          <v-row>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Tashkilot</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.organization?.name }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Transport turi</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.vehicle_type }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Yoqilg'i turi</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.fuel_type }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Yoqilg'i me'yori</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.fuel_norm }} L/100km</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Rang</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.color || '—' }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Motor kuchi</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.engine_power || '—' }} ot kuchi</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-medium-emphasis">Haydovchi</div>
              <div class="text-body-2 font-weight-medium">{{ vehicle.driver?.full_name || "Biriktirilmagan" }}</div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Oxirgi yo'l varaqalari -->
      <v-card rounded="xl" class="border">
        <v-card-title class="pa-5 pb-2">Oxirgi yo'l varaqalari</v-card-title>
        <v-card-text>
          <v-list v-if="vehicle.waybills?.length" density="compact">
            <v-list-item
              v-for="wb in vehicle.waybills"
              :key="wb.id"
              :title="wb.waybill_number"
              :subtitle="wb.destination"
              @click="router.push(`/waybills/${wb.id}`)"
            />
          </v-list>
          <div v-else class="text-center py-4 text-medium-emphasis text-caption">Yo'l varaqasi yo'q</div>
        </v-card-text>
      </v-card>
    </template>
  </v-container>
</template>
