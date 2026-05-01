<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { driverApi } from '@/services/api'

const router = useRouter()
const drivers = ref([])
const loading = ref(true)
const search = ref('')

const headers = [
  { title: 'F.I.Sh', key: 'full_name' },
  { title: 'Guvohnoma', key: 'license_number', width: 130 },
  { title: 'Kategoriya', key: 'license_category', width: 110 },
  { title: 'Tashkilot', key: 'organization.short_name' },
  { title: 'Avtomobil', key: 'vehicle.state_number', width: 130 },
  { title: 'Staj', key: 'work_experience', width: 80 },
  { title: 'Holat', key: 'status', width: 120 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 100, align: 'center' },
]

const statusLabel = (s) => ({ active: 'Faol', inactive: "Faol emas", sick_leave: 'Kasal', vacation: "Ta'tilda" }[s] || s)
const statusColor = (s) => ({ active: 'success', inactive: 'grey', sick_leave: 'warning', vacation: 'info' }[s] || 'grey')

async function load() {
  loading.value = true
  try {
    const res = await driverApi.list({ search: search.value })
    drivers.value = res.data
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Haydovchilar ro'yxati</h2>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="router.push('/drivers/create')">
          Yangi qo'shish
        </v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-text-field
          v-model="search"
          label="Qidirish"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          class="mb-4"
          style="max-width:400px;"
          @update:model-value="load"
        />

        <v-data-table :headers="headers" :items="drivers" :loading="loading" hover rounded="xl">
          <template #item.full_name="{ item }">
            <span class="font-weight-medium" style="cursor:pointer;" @click="router.push(`/drivers/${item.id}`)">
              {{ item.full_name }}
            </span>
          </template>
          <template #item.work_experience="{ item }">{{ item.work_experience }} yil</template>
          <template #item.status="{ item }">
            <v-chip :color="statusColor(item.status)" size="small" variant="flat">{{ statusLabel(item.status) }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" color="primary" @click="router.push(`/drivers/${item.id}/edit`)">
              <v-icon size="18">mdi-pencil</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>
