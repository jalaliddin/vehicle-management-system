<script setup>
import { ref, onMounted } from 'vue'
import { fuelApi, organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/hr'
import { quantity } from '@/utils/fuel'
import { useSnack } from '@/utils/useSnack'

const auth = useAuthStore()

const stations = ref([])
const organizations = ref([])
const loading = ref(true)
const createDialog = ref(false)
const selectedStation = ref(null)
const saving = ref(false)
const { snack, notify } = useSnack()
const fuelersDialog = ref(false)
const candidates = ref([])
const fuelerIds = ref([])
const adjustDialog = ref(false)
const adjustForm = ref({})

const form = ref({ organization_id: null, name: '', fuel_type: 'benzin', capacity: '', current_balance: 0, min_threshold: 500 })

async function load() {
  loading.value = true
  const [sRes, oRes] = await Promise.all([fuelApi.stations(), organizationApi.list()])
  stations.value = sRes.data
  organizations.value = auth.assignableOrganizations(oRes.data)
  if (organizations.value.length === 1) form.value.organization_id = organizations.value[0].id
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

async function openFuelers(station) {
  selectedStation.value = station
  candidates.value = (await fuelApi.fuelerCandidates(station.id)).data
  fuelerIds.value = (station.fuelers || []).map(f => f.id)
  fuelersDialog.value = true
}

async function saveFuelers() {
  saving.value = true
  try {
    await fuelApi.assignFuelers(selectedStation.value.id, fuelerIds.value)
    fuelersDialog.value = false
    notify('Quyuvchilar saqlandi')
    load()
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

function openAdjust(station) {
  selectedStation.value = station
  adjustForm.value = { actual_balance: null, document_number: '', notes: '' }
  adjustDialog.value = true
}

async function saveAdjust() {
  saving.value = true
  try {
    await fuelApi.adjust(selectedStation.value.id, adjustForm.value)
    adjustDialog.value = false
    notify('Inventarizatsiya akti saqlandi')
    load()
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

const headers = [
  { title: 'Tashkilot', key: 'organization.short_name' },
  { title: 'Nomi', key: 'name' },
  { title: "Yoqilg'i", key: 'fuel_type', width: 100 },
  { title: "Sig'im", key: 'capacity', width: 110 },
  { title: 'Qoldiq', key: 'current_balance', width: 120 },
  { title: '%', key: 'percentage', width: 100 },
  { title: 'Quyuvchilar', key: 'fuelers', sortable: false },
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
      <v-col v-if="auth.can('fuel.stations.manage')" cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="createDialog = true">
          Yangi shahobcha
        </v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-data-table :headers="headers" :items="stations" :loading="loading" hover rounded="xl">
          <template #item.capacity="{ item }">{{ quantity(item.capacity) }} {{ item.unit }}</template>
          <template #item.current_balance="{ item }">{{ quantity(item.current_balance) }} {{ item.unit }}</template>
          <template #item.fuelers="{ item }">
            <span v-if="!item.fuelers?.length" class="text-medium-emphasis">—</span>
            <v-chip v-for="f in item.fuelers" :key="f.id" size="x-small" class="mr-1">{{ f.name }}</v-chip>
          </template>
          <template #item.percentage="{ item }">
            <div class="d-flex align-center gap-2">
              <v-progress-linear :model-value="item.percentage"
                :color="item.percentage < 20 ? 'error' : item.percentage < 50 ? 'warning' : 'success'"
                rounded height="6" bg-color="grey-lighten-3" style="min-width:60px;" />
              <span class="text-caption">{{ item.percentage }}%</span>
            </div>
          </template>
          <template #item.actions="{ item }">
            <v-btn v-if="auth.can('fuel.stations.manage')" icon size="small" variant="text" title="Quyuvchilar" @click="openFuelers(item)"><v-icon size="18">mdi-account-hard-hat</v-icon></v-btn>
            <v-btn v-if="auth.can('fuel.adjust')" icon size="small" variant="text" title="Inventarizatsiya" @click="openAdjust(item)"><v-icon size="18">mdi-clipboard-check-outline</v-icon></v-btn>
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

    <v-dialog v-model="fuelersDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Quyuvchilar — {{ selectedStation?.name }}</v-card-title>
        <v-card-text>
          <v-autocomplete v-model="fuelerIds" :items="candidates" item-title="name" item-value="id" label="Quyuvchilar"
            variant="outlined" rounded="lg" multiple chips closable-chips no-data-text="Yoqilg'i harakati huquqiga ega xodim yo'q" />
          <div class="text-caption text-medium-emphasis">Biriktirilgan quyuvchi faqat o'z shahobchalarini ko'radi.</div>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="fuelersDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="saveFuelers">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="adjustDialog" max-width="460">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Inventarizatsiya — {{ selectedStation?.name }}</v-card-title>
        <v-card-text>
          <div class="text-body-2 mb-3">Hisob bo'yicha qoldiq: <b>{{ quantity(selectedStation?.current_balance) }} {{ selectedStation?.unit }}</b></div>
          <v-text-field v-model="adjustForm.actual_balance" type="number" :label="`Haqiqiy qoldiq (${selectedStation?.unit})`" variant="outlined" rounded="lg" />
          <v-text-field v-model="adjustForm.document_number" label="Akt raqami" variant="outlined" rounded="lg" />
          <v-textarea v-model="adjustForm.notes" label="Izoh" rows="2" variant="outlined" rounded="lg" />
          <div class="text-caption text-medium-emphasis">Akt faqat ochiq smena yo'qligida kiritiladi.</div>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="adjustDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving"
            :disabled="adjustForm.actual_balance === null || adjustForm.actual_balance === '' || !adjustForm.document_number || !adjustForm.notes" @click="saveAdjust">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="4000">{{ snack.text }}</v-snackbar>
  </v-container>
</template>
