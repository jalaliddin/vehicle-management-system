<script setup>
import { ref, watch, onMounted } from 'vue'
import { fuelApi } from '@/services/api'
import { quantity, unitOf } from '@/utils/fuel'
import ShiftReport from '@/components/fuel/ShiftReport.vue'

const shifts = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)
const stations = ref([])
const filters = ref({ station_id: null, from: null, to: null, with_discrepancy: false })
const report = ref(null)
const reportOpen = ref(false)

const headers = [
  { title: 'Shahobcha', key: 'station.name' },
  { title: 'Ochildi', key: 'opened_at', width: 170 },
  { title: 'Quyuvchi', key: 'opened_by.name' },
  { title: 'Yopildi', key: 'closed_at', width: 170 },
  { title: 'Hisob', key: 'closing_balance', width: 120 },
  { title: "O'lchov", key: 'closing_measured', width: 120 },
  { title: 'Farq', key: 'discrepancy', width: 110 },
  { title: '', key: 'actions', sortable: false, width: 70 },
]

const time = (value) => (value ? new Date(value).toLocaleString('uz-UZ') : '—')

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries({ ...filters.value, page: page.value }).filter(([, v]) => v))
    const res = await fuelApi.shifts(params)
    shifts.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

function printReport() {
  window.print()
}

async function openReport(shift) {
  report.value = (await fuelApi.shift(shift.id)).data
  reportOpen.value = true
}

watch(filters, () => { page.value = 1; load() }, { deep: true })
onMounted(async () => {
  stations.value = (await fuelApi.stations()).data
  load()
})
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Smenalar</h2></v-col>
    </v-row>
    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense class="mb-2" align="center">
          <v-col cols="12" md="3"><v-select v-model="filters.station_id" :items="stations" item-title="name" item-value="id" label="Shahobcha" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.from" type="date" label="Dan" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.to" type="date" label="Gacha" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <v-col cols="12" md="3"><v-checkbox v-model="filters.with_discrepancy" label="Faqat farqi borlar" density="compact" hide-details /></v-col>
        </v-row>
        <v-data-table-server
          v-model:page="page" :headers="headers" :items="shifts" :items-length="total" :items-per-page="50" :items-per-page-options="[50]"
          :loading="loading" hover @update:page="load"
        >
          <template #item.opened_at="{ item }">{{ time(item.opened_at) }}</template>
          <template #item.closed_at="{ item }">
            <span v-if="item.status === 'open'"><v-chip size="small" color="success" variant="tonal">Ochiq</v-chip></span>
            <span v-else>{{ time(item.closed_at) }}</span>
          </template>
          <template #item.closing_balance="{ item }">{{ quantity(item.closing_balance) }}</template>
          <template #item.closing_measured="{ item }">{{ quantity(item.closing_measured) }}</template>
          <template #item.discrepancy="{ item }">
            <v-chip v-if="item.discrepancy !== null && Number(item.discrepancy) !== 0" size="small" color="error" variant="tonal" :title="item.discrepancy_note">
              {{ quantity(item.discrepancy) }} {{ unitOf(item.station?.fuel_type) }}
            </v-chip>
            <span v-else>{{ item.discrepancy === null ? '—' : '0' }}</span>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" title="Hisobot" @click="openReport(item)"><v-icon size="18">mdi-file-document-outline</v-icon></v-btn>
          </template>
        </v-data-table-server>
      </v-card-text>
    </v-card>

    <v-dialog v-model="reportOpen" max-width="760">
      <v-card v-if="report" rounded="xl">
        <v-card-text class="pa-5"><ShiftReport :report="report" :unit="unitOf(report.shift.station?.fuel_type)" /></v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="reportOpen = false">Yopish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-printer" @click="printReport">Chop etish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
