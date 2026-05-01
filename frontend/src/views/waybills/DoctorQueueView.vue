<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { waybillApi } from '@/services/api'

const router = useRouter()
const items = ref([])
const loading = ref(true)
const checkDialog = ref(false)
const selectedItem = ref(null)
const checkPassed = ref(true)
const checkNotes = ref('')
const submitting = ref(false)

const headers = [
  { title: 'Raqam', key: 'waybill_number', width: 160 },
  { title: 'Avtomobil', key: 'vehicle.state_number', width: 130 },
  { title: 'Haydovchi', key: 'driver.full_name' },
  { title: 'Tashkilot', key: 'organization.short_name', width: 130 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 200, align: 'center' },
]

async function load() {
  loading.value = true
  const res = await waybillApi.list({ status: 'doctor_check', per_page: 100 })
  items.value = res.data.data
  loading.value = false
}

function openCheck(item, passed) {
  selectedItem.value = item
  checkPassed.value = passed
  checkNotes.value = ''
  checkDialog.value = true
}

async function submitCheck() {
  submitting.value = true
  try {
    await waybillApi.doctorCheck(selectedItem.value.id, { passed: checkPassed.value, notes: checkNotes.value })
    checkDialog.value = false
    load()
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <div class="d-flex align-center gap-3">
          <v-avatar color="info" size="48" rounded="xl">
            <v-icon color="white" size="24">mdi-stethoscope</v-icon>
          </v-avatar>
          <div>
            <h2 class="text-h6 font-weight-bold">Shifokor tekshiruvi navbati</h2>
            <p class="text-medium-emphasis text-body-2">{{ items.length }} ta yo'l varaqasi tekshiruvni kutmoqda</p>
          </div>
        </div>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-data-table :headers="headers" :items="items" :loading="loading" hover rounded="xl">
          <template #item.waybill_number="{ item }">
            <span class="font-weight-bold text-primary" style="cursor:pointer;" @click="router.push(`/waybills/${item.id}`)">
              {{ item.waybill_number }}
            </span>
          </template>
          <template #item.actions="{ item }">
            <v-btn color="success" variant="flat" size="small" rounded="lg" class="mr-2" @click="openCheck(item, true)">
              <v-icon start size="14">mdi-check</v-icon> Sog'lom
            </v-btn>
            <v-btn color="error" variant="outlined" size="small" rounded="lg" @click="openCheck(item, false)">
              <v-icon start size="14">mdi-close</v-icon> Rad
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="checkDialog" max-width="480" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">
          <v-icon :color="checkPassed ? 'success' : 'error'" class="mr-2">
            {{ checkPassed ? 'mdi-heart-pulse' : 'mdi-heart-off' }}
          </v-icon>
          Shifokor xulosasi — {{ selectedItem?.waybill_number }}
        </v-card-title>
        <v-card-text>
          <v-textarea v-model="checkNotes" :label="checkPassed ? 'Izoh (ixtiyoriy)' : 'Rad etish sababi'"
            variant="outlined" rounded="lg" rows="3" />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-btn variant="text" @click="checkDialog = false">Bekor</v-btn>
          <v-spacer />
          <v-btn :color="checkPassed ? 'success' : 'error'" variant="flat" rounded="lg" :loading="submitting" @click="submitCheck">
            Yuborish
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
