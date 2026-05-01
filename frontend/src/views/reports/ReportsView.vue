<script setup>
import { ref } from 'vue'
import { reportApi, organizationApi } from '@/services/api'
import { onMounted } from 'vue'

const tab = ref('waybills')
const loading = ref(false)
const report = ref(null)
const organizations = ref([])
const filters = ref({ from: '', to: '', org_id: '', status: '' })

onMounted(async () => {
  organizations.value = (await organizationApi.list()).data
})

async function loadReport() {
  loading.value = true
  report.value = null
  try {
    const params = { ...filters.value }
    const apis = { waybills: reportApi.waybills, fuel: reportApi.fuel, vehicles: reportApi.vehicles, drivers: reportApi.drivers, 'fuel-consumption': reportApi.fuelConsumption }
    const res = await apis[tab.value](params)
    report.value = res.data
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-container fluid class="pa-6">
    <h2 class="text-h6 font-weight-bold mb-4">Hisobotlar</h2>

    <v-card rounded="xl" class="border mb-4">
      <v-tabs v-model="tab" color="primary" @update:model-value="report = null">
        <v-tab value="waybills">Yo'l varaqalari</v-tab>
        <v-tab value="fuel">Yoqilg'i</v-tab>
        <v-tab value="vehicles">Avtomobillar</v-tab>
        <v-tab value="drivers">Haydovchilar</v-tab>
        <v-tab value="fuel-consumption">Yoqilg'i sarfi tahlili</v-tab>
      </v-tabs>
    </v-card>

    <v-card rounded="xl" class="border mb-4">
      <v-card-text class="pa-4">
        <v-row align="center">
          <v-col cols="12" md="3">
            <v-text-field v-model="filters.from" type="date" label="Boshlanish" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="filters.to" type="date" label="Tugash" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="filters.org_id" :items="[{name:'Barchasi',id:''},...organizations]" item-title="name" item-value="id"
              label="Tashkilot" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
          <v-col cols="12" md="3">
            <v-btn color="primary" variant="flat" rounded="lg" block :loading="loading" @click="loadReport">
              <v-icon start>mdi-magnify</v-icon> Hisobot olish
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Results -->
    <template v-if="report">
      <!-- Summary chips -->
      <v-row class="mb-4">
        <v-col v-for="(val, key) in report" :key="key" v-if="typeof val !== 'object'" cols="6" md="2">
          <v-card rounded="xl" color="primary" variant="tonal" class="pa-3 text-center">
            <div class="text-h6 font-weight-bold text-primary">{{ typeof val === 'number' ? Number(val).toLocaleString() : val }}</div>
            <div class="text-caption text-medium-emphasis">{{ key }}</div>
          </v-card>
        </v-col>
      </v-row>

      <!-- Table for items -->
      <v-card v-if="report.items?.length" rounded="xl" class="border">
        <v-card-text class="pa-4 overflow-x-auto">
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
              <tr style="background:#F5F7FA;">
                <th v-for="key in Object.keys(report.items[0]).filter(k => typeof report.items[0][k] !== 'object')" :key="key"
                  style="padding:8px 12px; text-align:left; font-weight:600; border-bottom:1px solid #E0E0E0;">
                  {{ key }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in report.items" :key="i" :style="i % 2 === 0 ? '' : 'background:#FAFAFA;'">
                <td v-for="key in Object.keys(row).filter(k => typeof row[k] !== 'object')" :key="key"
                  style="padding:8px 12px; border-bottom:1px solid #F0F0F0;">
                  {{ row[key] ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </v-card-text>
      </v-card>
    </template>
  </v-container>
</template>
