<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { vehicleApi } from '@/services/api'

const router = useRouter()
const vehicles = ref([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const deleteDialog = ref(false)
const selectedVehicle = ref(null)

const headers = [
  { title: 'Davlat raqami', key: 'state_number', width: 130 },
  { title: 'Model', key: 'model' },
  { title: 'Yil', key: 'manufacture_year', width: 80 },
  { title: 'Tashkilot', key: 'organization.short_name' },
  { title: "Yoqilg'i turi", key: 'fuel_type', width: 110 },
  { title: "Me'yor", key: 'fuel_norm', width: 90 },
  { title: 'Holat', key: 'status', width: 130 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 120, align: 'center' },
]

const statusItems = [
  { title: 'Barchasi', value: '' },
  { title: 'Faol', value: 'active' },
  { title: 'Nosoz', value: 'broken' },
  { title: "Ta'mirda", value: 'maintenance' },
  { title: 'Faol emas', value: 'inactive' },
]

const statusLabel = (s) => ({ active: 'Faol', broken: 'Nosoz', maintenance: "Ta'mirda", inactive: 'Faol emas' }[s] || s)
const statusColor = (s) => ({ active: 'success', broken: 'error', maintenance: 'warning', inactive: 'grey' }[s] || 'grey')

async function load() {
  loading.value = true
  try {
    const res = await vehicleApi.list({ search: search.value, status: statusFilter.value })
    vehicles.value = res.data
  } finally {
    loading.value = false
  }
}

async function confirmDelete() {
  await vehicleApi.delete(selectedVehicle.value.id)
  deleteDialog.value = false
  load()
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Avtomobillar ro'yxati</h2>
        <p class="text-medium-emphasis text-body-2">Barcha tashkilotlar avtomobillari</p>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="router.push('/vehicles/create')">
          Yangi qo'shish
        </v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row class="mb-3">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              label="Qidirish (davlat raqami, model)"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              @update:model-value="load"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="statusFilter"
              :items="statusItems"
              label="Holat bo'yicha"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              @update:model-value="load"
            />
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="vehicles"
          :loading="loading"
          hover
          rounded="xl"
        >
          <template #item.state_number="{ item }">
            <span class="font-weight-bold text-primary" style="cursor:pointer;" @click="router.push(`/vehicles/${item.id}`)">
              {{ item.state_number }}
            </span>
          </template>
          <template #item.fuel_norm="{ item }">
            {{ item.fuel_norm }} L/100km
          </template>
          <template #item.status="{ item }">
            <v-chip :color="statusColor(item.status)" size="small" variant="flat">
              {{ statusLabel(item.status) }}
            </v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" color="primary" @click="router.push(`/vehicles/${item.id}/edit`)">
              <v-icon size="18">mdi-pencil</v-icon>
            </v-btn>
            <v-btn icon size="small" variant="text" color="error" @click="selectedVehicle = item; deleteDialog = true">
              <v-icon size="18">mdi-delete</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">O'chirishni tasdiqlang</v-card-title>
        <v-card-text>
          <strong>{{ selectedVehicle?.state_number }}</strong> avtomobilni o'chirmoqchimisiz?
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Bekor</v-btn>
          <v-btn color="error" variant="flat" rounded="lg" @click="confirmDelete">O'chirish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
