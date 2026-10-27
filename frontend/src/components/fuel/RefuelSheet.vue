<script setup>
import { ref, computed, watch } from 'vue'
import { fuelApi } from '@/services/api'
import { errorMessage } from '@/utils/hr'
import { unitOf } from '@/utils/fuel'

const props = defineProps({ modelValue: Boolean, station: { type: Object, required: true } })
const emit = defineEmits(['update:modelValue', 'saved'])

const show = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })
const search = ref('')
const vehicles = ref([])
const vehicleId = ref(null)
const odometer = ref(null)
const amount = ref(null)
const saving = ref(false)
const failure = ref('')
const vehicle = computed(() => vehicles.value.find(v => v.id === vehicleId.value))

let timer
watch(search, (text) => {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    vehicles.value = (await fuelApi.vehicles(props.station.id, text || '')).data
  }, 300)
})

watch(show, async (open) => {
  if (!open) return
  failure.value = ''
  vehicleId.value = null
  odometer.value = null
  amount.value = null
  vehicles.value = (await fuelApi.vehicles(props.station.id, '')).data
})

async function save() {
  saving.value = true
  failure.value = ''
  try {
    await fuelApi.refuel(props.station.id, { vehicle_id: vehicleId.value, odometer: odometer.value, amount: amount.value })
    show.value = false
    emit('saved')
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog v-model="show" max-width="520" :fullscreen="$vuetify.display.xs">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-2 d-flex align-center">
        Yoqilg'i quyish
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="show = false" />
      </v-card-title>
      <v-card-text>
        <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
        <v-autocomplete
          v-model="vehicleId" v-model:search="search" :items="vehicles" item-title="state_number" item-value="id" no-filter
          label="Davlat raqami" variant="outlined" rounded="lg" prepend-inner-icon="mdi-car" autofocus
          no-data-text="Avtomobil topilmadi"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps" :subtitle="`${item.raw.model} · ${item.raw.driver?.full_name || 'haydovchi biriktirilmagan'}`" />
          </template>
        </v-autocomplete>

        <v-card v-if="vehicle" variant="tonal" rounded="lg" class="mb-4 pa-3">
          <div class="font-weight-bold">{{ vehicle.state_number }} — {{ vehicle.model }}</div>
          <div class="text-body-2">Haydovchi: {{ vehicle.driver?.full_name || '—' }}</div>
          <div class="text-body-2">Yo'l varaqasi: {{ vehicle.waybill?.waybill_number || "yo'q" }}</div>
          <div class="text-body-2">Oxirgi spidometr: {{ vehicle.odometer ?? '—' }} km · Bak: {{ vehicle.tank_capacity ?? '—' }} {{ unitOf(station.fuel_type) }}</div>
          <v-alert v-if="!vehicle.fuel_type_matches" type="error" variant="flat" density="compact" class="mt-2">
            Yoqilg'i turi mos emas: avtomobil {{ vehicle.fuel_type }}, shahobcha {{ station.fuel_type }}
          </v-alert>
          <v-alert v-else-if="!vehicle.waybill" type="warning" variant="tonal" density="compact" class="mt-2">Ochiq yo'l varaqasi yo'q</v-alert>
        </v-card>

        <v-text-field v-model.number="odometer" type="number" inputmode="numeric" label="Spidometr (km)" variant="outlined" rounded="lg" prepend-inner-icon="mdi-counter" />
        <v-text-field v-model="amount" type="number" inputmode="decimal" :label="`Miqdor (${unitOf(station.fuel_type)})`" variant="outlined" rounded="lg" prepend-inner-icon="mdi-fuel" />
      </v-card-text>
      <v-card-actions class="pa-5 pt-0">
        <v-btn block size="x-large" color="primary" variant="flat" rounded="lg" :loading="saving"
          :disabled="!vehicleId || !odometer || !amount || (vehicle && !vehicle.fuel_type_matches)" @click="save">Saqlash</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
