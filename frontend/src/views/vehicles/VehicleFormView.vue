<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { vehicleApi, organizationApi } from '@/services/api'

const router = useRouter()
const route = useRoute()
const isEdit = !!route.params.id
const loading = ref(false)
const saving = ref(false)
const organizations = ref([])

const form = ref({
  organization_id: null, state_number: '', model: '', manufacture_year: new Date().getFullYear(),
  chassis_number: '', body_type: '', tech_passport_series: '', tech_passport_number: '',
  engine_number: '', empty_weight: '', full_weight: '', color: '', engine_power: '',
  seat_count: '', vehicle_type: 'car', fuel_norm: '', fuel_norm_ac: '', fuel_type: 'benzin',
  status: 'active', notes: '',
})

const vehicleTypes = ['car', 'truck', 'bus', 'special']
const fuelTypes = ['benzin', 'dizel', 'gaz']
const statusItems = ['active', 'broken', 'maintenance', 'inactive']

async function load() {
  organizations.value = (await organizationApi.list()).data
  if (isEdit) {
    loading.value = true
    const res = await vehicleApi.show(route.params.id)
    Object.assign(form.value, res.data)
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    if (isEdit) {
      await vehicleApi.update(route.params.id, form.value)
    } else {
      await vehicleApi.create(form.value)
    }
    router.push('/vehicles')
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6" style="max-width:900px;">
    <v-row class="mb-4" align="center">
      <v-col>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="router.back()">Orqaga</v-btn>
        <h2 class="text-h6 font-weight-bold mt-1">{{ isEdit ? 'Avtomobilni tahrirlash' : "Yangi avtomobil qo'shish" }}</h2>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border" :loading="loading">
      <v-card-text class="pa-6">
        <v-row>
          <v-col cols="12" md="6">
            <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" label="Tashkilot" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.state_number" label="Davlat raqami" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.model" label="Model" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="form.manufacture_year" label="Ishlab chiqarilgan yil" type="number" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.vehicle_type" :items="vehicleTypes" label="Transport turi" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.fuel_type" :items="fuelTypes" label="Yoqilg'i turi" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.fuel_norm" label="Yoqilg'i me'yori (L/100km)" type="number" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.fuel_norm_ac" label="Konditsionerli me'yor" type="number" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="form.color" label="Rang" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="form.engine_power" label="Kuch (ot kuchi)" type="number" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="form.seat_count" label="O'rindiqlar soni" type="number" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="3">
            <v-select v-model="form.status" :items="statusItems" label="Holat" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.chassis_number" label="Shassi raqami" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.engine_number" label="Motor raqami" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.tech_passport_number" label="Texnik pasport raqami" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.notes" label="Izoh" variant="outlined" rounded="lg" rows="2" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="pa-6 pt-0">
        <v-spacer />
        <v-btn variant="outlined" rounded="lg" @click="router.back()">Bekor</v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">
          {{ isEdit ? 'Saqlash' : "Qo'shish" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
