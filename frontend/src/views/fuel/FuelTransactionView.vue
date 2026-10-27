<script setup>
import { ref, watch, onMounted } from 'vue'
import { fuelApi } from '@/services/api'
import { label } from '@/utils/hr'
import { quantity, transactionKinds } from '@/utils/fuel'

const items = ref([])
const loading = ref(true)
const page = ref(1)
const total = ref(0)
const stations = ref([])
const filters = ref({ kind: null, station_id: null, date_from: null, date_to: null })
const kindOptions = Object.entries(transactionKinds).map(([value, title]) => ({ value, title }))

const headers = [
  { title: 'Vaqt', key: 'created_at', width: 160 },
  { title: 'Shahobcha', key: 'fuel_station.name' },
  { title: 'Amal', key: 'kind', width: 140 },
  { title: 'Miqdor', key: 'amount', width: 120 },
  { title: 'Avtomobil / yetkazib beruvchi', key: 'subject' },
  { title: 'Haydovchi', key: 'driver.full_name' },
  { title: 'Spidometr', key: 'odometer', width: 110 },
  { title: 'Hujjat', key: 'document_number', width: 130 },
  { title: 'Kiritdi', key: 'created_by.name' },
]

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries({ ...filters.value, page: page.value }).filter(([, v]) => v))
    const res = await fuelApi.transactions(params)
    items.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
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
      <v-col><h2 class="text-h6 font-weight-bold">Yoqilg'i harakatlari</h2></v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense class="mb-2">
          <v-col cols="12" md="2"><v-select v-model="filters.kind" :items="kindOptions" label="Amal" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="12" md="3"><v-select v-model="filters.station_id" :items="stations" item-title="name" item-value="id" label="Shahobcha" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.date_from" type="date" label="Dan" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.date_to" type="date" label="Gacha" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
        </v-row>

        <v-data-table-server
          v-model:page="page" :headers="headers" :items="items" :items-length="total" :items-per-page="20" :items-per-page-options="[20]"
          :loading="loading" hover @update:page="load"
        >
          <template #item.created_at="{ item }">{{ new Date(item.created_at).toLocaleString('uz-UZ') }}</template>
          <template #item.kind="{ item }">
            <v-chip size="small" variant="tonal" :color="item.cancelled_at ? 'grey' : item.type === 'in' ? 'success' : 'error'">
              {{ label(transactionKinds, item.kind) }}
            </v-chip>
            <div v-if="item.cancelled_at" class="text-caption text-medium-emphasis" :title="item.cancel_reason">bekor qilingan</div>
          </template>
          <template #item.amount="{ item }">
            <span :class="[item.type === 'in' ? 'text-success' : 'text-error', 'font-weight-bold', { 'text-decoration-line-through': item.cancelled_at }]">
              {{ item.type === 'in' ? '+' : '−' }}{{ quantity(item.amount) }}
            </span>
          </template>
          <template #item.subject="{ item }">{{ item.vehicle?.state_number || item.supplier?.name || '—' }}</template>
          <template #item.driver.full_name="{ item }">{{ item.driver?.full_name || '—' }}</template>
          <template #item.odometer="{ item }">{{ item.odometer ?? '—' }}</template>
          <template #item.document_number="{ item }">{{ item.document_number || '—' }}</template>
          <template #item.created_by.name="{ item }">{{ item.created_by?.name || '—' }}</template>
        </v-data-table-server>
      </v-card-text>
    </v-card>
  </v-container>
</template>
