<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { waybillApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const items = ref([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const total = ref(0)

const headers = [
  { title: 'Raqam', key: 'waybill_number', width: 160 },
  { title: 'Tashkilot', key: 'organization.short_name', width: 120 },
  { title: 'Avtomobil', key: 'vehicle.state_number', width: 130 },
  { title: 'Haydovchi', key: 'driver.full_name' },
  { title: 'Yo\'nalish', key: 'destination' },
  { title: 'Sana', key: 'created_date', width: 110 },
  { title: 'Holat', key: 'status', width: 160 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 80, align: 'center' },
]

const statusLabel = (s) => ({
  draft: 'Yaratilgan', mechanic_check: 'Mexanik kutmoqda', mechanic_ok: 'Mexanik OK',
  doctor_check: 'Shifokor kutmoqda', doctor_ok: 'Shifokor OK', hq_review: 'HQ',
  approved: 'Tasdiqlandi', in_progress: "Yo'lda", completed: 'Bajarildi', cancelled: 'Bekor',
}[s] || s)

const statusColor = (s) => ({
  draft: 'grey', mechanic_check: 'orange', doctor_check: 'orange', hq_review: 'orange',
  mechanic_ok: 'light-blue', doctor_ok: 'light-blue', approved: 'green', in_progress: 'success',
  completed: 'primary', cancelled: 'error',
}[s] || 'grey')

const statusItems = [
  { title: 'Barchasi', value: '' },
  { title: 'Yaratilgan', value: 'draft' },
  { title: 'Mexanik kutmoqda', value: 'mechanic_check' },
  { title: 'Shifokor kutmoqda', value: 'doctor_check' },
  { title: "Yo'lda", value: 'in_progress' },
  { title: 'Bajarildi', value: 'completed' },
  { title: 'Bekor', value: 'cancelled' },
]

async function load() {
  loading.value = true
  try {
    const res = await waybillApi.list({ search: search.value, status: statusFilter.value, page: page.value })
    items.value = res.data.data
    total.value = res.data.total
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
        <h2 class="text-h6 font-weight-bold">Yo'l varaqalari</h2>
      </v-col>
      <v-col cols="auto" v-if="!auth.isMechanic && !auth.isDoctor">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="router.push('/waybills/create')">
          Yangi varaq
        </v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row class="mb-3">
          <v-col cols="12" md="5">
            <v-text-field v-model="search" label="Raqam bo'yicha qidirish" prepend-inner-icon="mdi-magnify"
              variant="outlined" density="compact" rounded="lg" hide-details @update:model-value="load" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="statusFilter" :items="statusItems" label="Holat" variant="outlined"
              density="compact" rounded="lg" hide-details @update:model-value="load" />
          </v-col>
        </v-row>

        <v-data-table :headers="headers" :items="items" :loading="loading" hover rounded="xl">
          <template #item.waybill_number="{ item }">
            <span class="font-weight-bold text-primary" style="cursor:pointer;" @click="router.push(`/waybills/${item.id}`)">
              {{ item.waybill_number }}
            </span>
          </template>
          <template #item.created_date="{ item }">
            {{ new Date(item.created_date).toLocaleDateString('uz-UZ') }}
          </template>
          <template #item.status="{ item }">
            <v-chip :color="statusColor(item.status)" size="small" variant="flat">
              {{ statusLabel(item.status) }}
            </v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" @click="router.push(`/waybills/${item.id}`)">
              <v-icon size="18">mdi-eye</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>
