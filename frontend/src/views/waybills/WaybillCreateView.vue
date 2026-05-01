<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { waybillApi, vehicleApi, driverApi, organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const step = ref(1)
const saving = ref(false)
const vehicles = ref([])
const drivers = ref([])
const organizations = ref([])

const form = ref({
  organization_id: auth.user?.organization_id || null,
  vehicle_id: null, driver_id: null, vehicle_type: 'car', tabel_number: '',
  created_date: new Date().toISOString().split('T')[0],
  valid_until: new Date(Date.now() + 86400000).toISOString().split('T')[0],
  trip_count: 1, destination: '', destination_organization: '',
  planned_km: '', fuel_issued: '', has_ac: false, notes: '',
})

const selectedVehicle = computed(() => vehicles.value.find(v => v.id === form.value.vehicle_id))
const estimatedFuel = computed(() => {
  const km = parseFloat(form.value.planned_km) || 0
  const norm = form.value.has_ac ? (selectedVehicle.value?.fuel_norm_ac || selectedVehicle.value?.fuel_norm) : selectedVehicle.value?.fuel_norm
  return km > 0 && norm ? ((km * norm) / 100).toFixed(1) : 0
})

onMounted(async () => {
  const [vRes, dRes, oRes] = await Promise.all([vehicleApi.list(), driverApi.list(), organizationApi.list()])
  vehicles.value = vRes.data.filter(v => v.status === 'active')
  drivers.value = dRes.data.filter(d => d.status === 'active')
  organizations.value = oRes.data
})

async function create() {
  saving.value = true
  try {
    await waybillApi.create(form.value)
    router.push('/waybills')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-container fluid class="pa-6" style="max-width:900px;">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-4" @click="router.back()">Orqaga</v-btn>
    <h2 class="text-h6 font-weight-bold mb-4">Yangi yo'l varaqasi</h2>

    <v-card rounded="xl" class="border">
      <!-- Stepper -->
      <v-stepper v-model="step" flat class="border-b" style="border-bottom:1px solid rgba(0,0,0,0.08);">
        <v-stepper-header>
          <v-stepper-item title="Ma'lumotlar" :value="1" :complete="step > 1" />
          <v-divider />
          <v-stepper-item title="Yo'nalish" :value="2" :complete="step > 2" />
          <v-divider />
          <v-stepper-item title="Yoqilg'i" :value="3" :complete="step > 3" />
          <v-divider />
          <v-stepper-item title="Tasdiqlash" :value="4" />
        </v-stepper-header>
      </v-stepper>

      <v-card-text class="pa-6">
        <!-- Step 1 -->
        <div v-if="step === 1">
          <v-row>
            <v-col cols="12" md="6">
              <v-select v-model="form.vehicle_id" :items="vehicles" item-title="state_number" item-value="id"
                label="Avtomobil (davlat raqami)" prepend-inner-icon="mdi-car" variant="outlined" rounded="lg">
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #subtitle>{{ item.raw.model }} | {{ item.raw.organization?.short_name }}</template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>
            <v-col cols="12" md="6">
              <v-select v-model="form.driver_id" :items="drivers" item-title="full_name" item-value="id"
                label="Haydovchi" prepend-inner-icon="mdi-account" variant="outlined" rounded="lg">
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #subtitle>Kat: {{ item.raw.license_category }} | {{ item.raw.work_experience }} yil</template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.destination" label="Yo'nalish / Manzil" prepend-inner-icon="mdi-map-marker"
                variant="outlined" rounded="lg" />
            </v-col>
            <v-col cols="12" md="6">
              <v-select v-model="form.destination_organization" :items="organizations" item-title="name" item-value="name"
                label="Qaysi tashkilotga" prepend-inner-icon="mdi-office-building" variant="outlined" rounded="lg" clearable />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model="form.created_date" type="date" label="Sana" variant="outlined" rounded="lg" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model="form.valid_until" type="date" label="Amal qilish muddati" variant="outlined" rounded="lg" />
            </v-col>
            <v-col cols="12" md="2">
              <v-text-field v-model="form.trip_count" type="number" label="Safarlar soni" variant="outlined" rounded="lg" />
            </v-col>
            <v-col cols="12" md="2">
              <v-switch v-model="form.has_ac" label="Konditsioner" color="primary" inset />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model="form.tabel_number" label="Tabel raqami" variant="outlined" rounded="lg" />
            </v-col>
          </v-row>
        </div>

        <!-- Step 2 -->
        <div v-if="step === 2">
          <v-alert type="info" variant="tonal" rounded="lg" class="mb-4">
            Yo'nalish masofasini belgilang yoki qo'lda kiriting
          </v-alert>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.planned_km" label="Masofa (km)" type="number" prepend-inner-icon="mdi-map-marker-distance"
                variant="outlined" rounded="lg" />
            </v-col>
          </v-row>
          <v-card v-if="selectedVehicle && form.planned_km" color="primary" variant="tonal" rounded="xl" class="pa-4 mt-3">
            <div class="text-h5 font-weight-bold text-primary">{{ estimatedFuel }} L</div>
            <div class="text-caption text-medium-emphasis">Taxminiy yoqilg'i sarfi</div>
          </v-card>
        </div>

        <!-- Step 3 -->
        <div v-if="step === 3">
          <v-row>
            <v-col cols="12" md="5">
              <v-text-field v-model="form.fuel_issued" label="Beriladigan yoqilg'i (L)" type="number"
                prepend-inner-icon="mdi-gas-station" variant="outlined" rounded="lg"
                :hint="estimatedFuel > 0 ? `Tavsiya: ${estimatedFuel} L` : ''" persistent-hint />
            </v-col>
          </v-row>
        </div>

        <!-- Step 4 - Preview -->
        <div v-if="step === 4">
          <v-list density="compact">
            <v-list-item title="Avtomobil" :subtitle="vehicles.find(v => v.id === form.vehicle_id)?.state_number" />
            <v-list-item title="Haydovchi" :subtitle="drivers.find(d => d.id === form.driver_id)?.full_name" />
            <v-list-item title="Yo'nalish" :subtitle="form.destination" />
            <v-list-item title="Sana" :subtitle="form.created_date" />
            <v-list-item title="Masofa" :subtitle="`${form.planned_km || 0} km`" />
            <v-list-item title="Yoqilg'i" :subtitle="`${form.fuel_issued || 0} L`" />
          </v-list>
        </div>
      </v-card-text>

      <v-card-actions class="pa-6 pt-0">
        <v-btn v-if="step > 1" variant="outlined" rounded="lg" @click="step--">
          <v-icon start>mdi-arrow-left</v-icon> Orqaga
        </v-btn>
        <v-spacer />
        <v-btn v-if="step < 4" color="primary" variant="flat" rounded="lg" @click="step++">
          Keyingisi <v-icon end>mdi-arrow-right</v-icon>
        </v-btn>
        <v-btn v-else color="success" variant="flat" rounded="lg" :loading="saving" @click="create">
          <v-icon start>mdi-check</v-icon> Yaratish
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
