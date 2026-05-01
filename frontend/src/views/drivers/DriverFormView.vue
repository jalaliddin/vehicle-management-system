<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { driverApi, organizationApi, vehicleApi } from '@/services/api'

const router = useRouter()
const route = useRoute()
const isEdit = !!route.params.id
const loading = ref(false)
const saving = ref(false)
const organizations = ref([])
const vehicles = ref([])

const form = ref({
  organization_id: null, full_name: '', license_number: '', license_category: 'B',
  vehicle_id: null, work_experience: 0, pinfl: '', phone: '', license_expiry: '',
  employment_type: 'staff', status: 'active', notes: '',
})

async function load() {
  const [orgsRes, vehRes] = await Promise.all([organizationApi.list(), vehicleApi.list()])
  organizations.value = orgsRes.data
  vehicles.value = vehRes.data
  if (isEdit) {
    loading.value = true
    const res = await driverApi.show(route.params.id)
    Object.assign(form.value, res.data)
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    if (isEdit) await driverApi.update(route.params.id, form.value)
    else await driverApi.create(form.value)
    router.push('/drivers')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6" style="max-width:900px;">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-4" @click="router.back()">Orqaga</v-btn>
    <h2 class="text-h6 font-weight-bold mb-4">{{ isEdit ? 'Haydovchini tahrirlash' : "Yangi haydovchi qo'shish" }}</h2>

    <v-card rounded="xl" class="border" :loading="loading">
      <v-card-text class="pa-6">
        <v-row>
          <v-col cols="12" md="6">
            <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" label="Tashkilot" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.full_name" label="F.I.Sh (to'liq ism)" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.license_number" label="Guvohnoma raqami" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.license_category" label="Kategoriya (A, B, C...)" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.license_expiry" label="Guvohnoma muddati" type="date" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.pinfl" label="JSHSHIR (PINFL) — 14 ta raqam" variant="outlined" rounded="lg" maxlength="14" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.phone" label="Telefon raqami" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="form.work_experience" label="Mehnat staji (yil)" type="number" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.employment_type" :items="['staff','contract','part_time']" label="Bandlik turi" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="form.status" :items="['active','inactive','sick_leave','vacation']" label="Holat" variant="outlined" rounded="lg" />
          </v-col>
          <v-col cols="12">
            <v-select v-model="form.vehicle_id" :items="vehicles" item-title="state_number" item-value="id" label="Biriktirilgan avtomobil (ixtiyoriy)" variant="outlined" rounded="lg" clearable />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="pa-6 pt-0">
        <v-spacer />
        <v-btn variant="outlined" rounded="lg" @click="router.back()">Bekor</v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
