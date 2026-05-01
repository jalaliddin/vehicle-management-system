<script setup>
import { ref, onMounted } from 'vue'
import { fuelApi } from '@/services/api'

const items = ref([])
const loading = ref(true)
const page = ref(1)
const total = ref(0)
const typeFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const headers = [
  { title: 'Vaqt', key: 'created_at', width: 160 },
  { title: 'Shahobcha', key: 'fuel_station.name' },
  { title: 'Tashkilot', key: 'fuel_station.organization.short_name', width: 120 },
  { title: 'Tur', key: 'type', width: 90 },
  { title: 'Miqdor (L)', key: 'amount', width: 110 },
  { title: "Yoqilg'i", key: 'fuel_type', width: 100 },
  { title: 'Avtomobil', key: 'vehicle.state_number', width: 130 },
]

async function load() {
  loading.value = true
  const res = await fuelApi.transactions({ type: typeFilter.value, from: dateFrom.value, to: dateTo.value, page: page.value })
  items.value = res.data.data
  total.value = res.data.total
  loading.value = false
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Yoqilg'i harakatlari</h2>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row class="mb-3">
          <v-col cols="12" md="3">
            <v-select v-model="typeFilter" :items="[{title:'Barchasi',value:''},{title:'Kirim',value:'in'},{title:'Chiqim',value:'out'}]"
              label="Tur" variant="outlined" density="compact" rounded="lg" hide-details @update:model-value="load" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="dateFrom" type="date" label="Boshlanish sanasi" variant="outlined" density="compact" rounded="lg" hide-details @update:model-value="load" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="dateTo" type="date" label="Tugash sanasi" variant="outlined" density="compact" rounded="lg" hide-details @update:model-value="load" />
          </v-col>
        </v-row>

        <v-data-table :headers="headers" :items="items" :loading="loading" hover rounded="xl">
          <template #item.created_at="{ item }">
            {{ new Date(item.created_at).toLocaleString('uz-UZ') }}
          </template>
          <template #item.type="{ item }">
            <v-chip :color="item.type === 'in' ? 'success' : 'error'" size="small" variant="flat">
              {{ item.type === 'in' ? 'Kirim' : 'Chiqim' }}
            </v-chip>
          </template>
          <template #item.amount="{ item }">
            <span :class="item.type === 'in' ? 'text-success font-weight-bold' : 'text-error font-weight-bold'">
              {{ item.type === 'in' ? '+' : '-' }}{{ Number(item.amount).toLocaleString() }}
            </span>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>
