<script setup>
import { ref, onMounted, computed } from 'vue'
import { fuelApi } from '@/services/api'

const stations = ref([])
const loading = ref(true)

onMounted(async () => {
  const res = await fuelApi.monitoring()
  stations.value = res.data
  loading.value = false
})

const totalFuel = computed(() => stations.value.reduce((s, st) => s + Number(st.current_balance), 0))
const normalStations = computed(() => stations.value.filter(s => s.percentage >= 50).length)
const warningStations = computed(() => stations.value.filter(s => s.percentage >= 20 && s.percentage < 50).length)
const criticalStations = computed(() => stations.value.filter(s => s.percentage < 20).length)

const pctColor = (pct) => pct < 20 ? 'error' : pct < 50 ? 'warning' : 'success'
const fuelTypeIcon = (t) => ({ benzin: 'mdi-gas-station', dizel: 'mdi-engine', gaz: 'mdi-weather-windy' }[t] || 'mdi-fuel')
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Yoqilg'i Monitoring</h2>
        <p class="text-medium-emphasis text-body-2">Barcha tashkilotlardagi yoqilg'i holati</p>
      </v-col>
    </v-row>

    <v-row class="mb-6">
      <v-col cols="6" md="3">
        <v-card color="primary" variant="flat" rounded="xl" class="pa-4 text-white text-center">
          <div class="text-h4 font-weight-bold">{{ totalFuel.toLocaleString() }}</div>
          <div class="text-caption opacity-80">Jami yoqilg'i (L)</div>
        </v-card>
      </v-col>
      <v-col cols="6" md="3">
        <v-card color="success" variant="flat" rounded="xl" class="pa-4 text-white text-center">
          <div class="text-h4 font-weight-bold">{{ normalStations }}</div>
          <div class="text-caption opacity-80">Normal (≥50%)</div>
        </v-card>
      </v-col>
      <v-col cols="6" md="3">
        <v-card color="warning" variant="flat" rounded="xl" class="pa-4 text-white text-center">
          <div class="text-h4 font-weight-bold">{{ warningStations }}</div>
          <div class="text-caption opacity-80">Ogohlantirish (20–50%)</div>
        </v-card>
      </v-col>
      <v-col cols="6" md="3">
        <v-card color="error" variant="flat" rounded="xl" class="pa-4 text-white text-center">
          <div class="text-h4 font-weight-bold">{{ criticalStations }}</div>
          <div class="text-caption opacity-80">Kritik (&lt;20%)</div>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="loading">
      <v-col v-for="i in 6" :key="i" cols="12" sm="6" md="4" lg="3">
        <v-skeleton-loader type="card" rounded="xl" />
      </v-col>
    </v-row>

    <v-row v-else>
      <v-col
        v-for="station in stations"
        :key="station.id"
        cols="12" sm="6" md="4" lg="3"
      >
        <v-card
          rounded="xl"
          class="border"
          :style="station.percentage < 20 ? 'border-color: #C62828 !important;' : station.percentage < 50 ? 'border-color: #F57F17 !important;' : ''"
        >
          <v-card-text class="pa-4">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-caption font-weight-bold" style="font-size:11px; letter-spacing:0.5px;">
                {{ station.organization?.short_name }}
              </span>
              <v-chip :color="pctColor(station.percentage)" size="x-small" variant="flat">
                {{ station.percentage }}%
              </v-chip>
            </div>

            <div class="d-flex align-center gap-2 mb-3">
              <v-icon size="16" color="primary">{{ fuelTypeIcon(station.fuel_type) }}</v-icon>
              <span class="text-caption text-uppercase font-weight-medium">{{ station.fuel_type }}</span>
              <span class="text-caption text-medium-emphasis ml-auto">{{ station.name }}</span>
            </div>

            <v-progress-linear
              :model-value="station.percentage"
              :color="pctColor(station.percentage)"
              rounded height="10"
              bg-color="grey-lighten-3"
              class="mb-2"
            />

            <div class="d-flex justify-space-between">
              <span class="text-caption font-weight-medium">{{ Number(station.current_balance).toLocaleString() }} L</span>
              <span class="text-caption text-medium-emphasis">/ {{ Number(station.capacity).toLocaleString() }} L</span>
            </div>

            <v-alert v-if="station.is_critical" type="error" variant="tonal" rounded="lg" density="compact" class="mt-2" style="font-size:11px;">
              Minimal chegara: {{ Number(station.min_threshold).toLocaleString() }} L
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
