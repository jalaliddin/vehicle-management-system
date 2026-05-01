<script setup>
import { ref, onMounted } from 'vue'
import { fuelApi, organizationApi } from '@/services/api'

const stations = ref([])
const organizations = ref([])
const loading = ref(true)
const createDialog = ref(false)
const txDialog = ref(false)
const selectedStation = ref(null)
const saving = ref(false)

const form = ref({ organization_id: null, name: '', fuel_type: 'benzin', capacity: '', current_balance: 0, min_threshold: 500 })
const txForm = ref({ type: 'in', amount: '', notes: '' })

async function load() {
  loading.value = true
  const [sRes, oRes] = await Promise.all([fuelApi.stations(), organizationApi.list()])
  stations.value = sRes.data
  organizations.value = oRes.data
  loading.value = false
}

async function createStation() {
  saving.value = true
  try {
    await fuelApi.createStation(form.value)
    createDialog.value = false
    load()
  } finally {
    saving.value = false
  }
}

async function addTx() {
  saving.value = true
  try {
    await fuelApi.addTransaction(selectedStation.value.id, txForm.value)
    txDialog.value = false
    load()
  } finally {
    saving.value = false
  }
}

const headers = [
  { title: 'Tashkilot', key: 'organization.short_name' },
  { title: 'Nomi', key: 'name' },
  { title: "Yoqilg'i", key: 'fuel_type', width: 100 },
  { title: 'Sig\'im (L)', key: 'capacity', width: 110 },
  { title: 'Qoldiq (L)', key: 'current_balance', width: 110 },
  { title: '%', key: 'percentage', width: 100 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 120, align: 'center' },
]

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Yoqilg'i Shahobchalari</h2>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createDialog = true">
          Yangi shahobcha
        </v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-data-table :headers="headers" :items="stations" :loading="loading" hover rounded="xl">
          <template #item.capacity="{ item }">{{ Number(item.capacity).toLocaleString() }}</template>
          <template #item.current_balance="{ item }">{{ Number(item.current_balance).toLocaleString() }}</template>
          <template #item.percentage="{ item }">
            <div class="d-flex align-center gap-2">
              <v-progress-linear :model-value="item.percentage"
                :color="item.percentage < 20 ? 'error' : item.percentage < 50 ? 'warning' : 'success'"
                rounded height="6" bg-color="grey-lighten-3" style="min-width:60px;" />
              <span class="text-caption">{{ item.percentage }}%</span>
            </div>
          </template>
          <template #item.actions="{ item }">
            <v-btn size="small" variant="flat" color="primary" rounded="lg" @click="selectedStation = item; txForm = { type: 'in', amount: '', notes: '' }; txDialog = true">
              Kirim/Chiqim
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Create Dialog -->
    <v-dialog v-model="createDialog" max-width="520">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Yangi shahobcha</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12"><v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" label="Tashkilot" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12" md="6"><v-text-field v-model="form.name" label="Nomi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12" md="6"><v-select v-model="form.fuel_type" :items="['benzin','dizel','gaz']" label="Yoqilg'i turi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12" md="6"><v-text-field v-model="form.capacity" label="Sig'im (L)" type="number" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12" md="6"><v-text-field v-model="form.min_threshold" label="Minimal chegara (L)" type="number" variant="outlined" rounded="lg" /></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="createDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="createStation">Yaratish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Transaction Dialog -->
    <v-dialog v-model="txDialog" max-width="420">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Kirim / Chiqim — {{ selectedStation?.name }}</v-card-title>
        <v-card-text>
          <v-btn-toggle v-model="txForm.type" color="primary" variant="outlined" rounded="lg" class="mb-3" mandatory>
            <v-btn value="in">Kirim</v-btn>
            <v-btn value="out">Chiqim</v-btn>
          </v-btn-toggle>
          <v-text-field v-model="txForm.amount" label="Miqdor (L)" type="number" variant="outlined" rounded="lg" class="mb-2" />
          <v-textarea v-model="txForm.notes" label="Izoh" variant="outlined" rounded="lg" rows="2" />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="txDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="addTx">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
