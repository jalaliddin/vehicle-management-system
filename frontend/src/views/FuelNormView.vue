<script setup>
import { ref, onMounted } from 'vue'
import { fuelNormApi } from '@/services/api'

const items = ref([])
const loading = ref(true)
const dialog = ref(false)
const editItem = ref(null)
const saving = ref(false)

const form = ref({ route_name: '', route_number: '', vehicle_model: '', norm_without_ac: '', norm_with_ac: '', heating_norm: '', fuel_type: 'benzin' })

const headers = [
  { title: 'Yo\'nalish', key: 'route_name' },
  { title: 'Raqami', key: 'route_number', width: 100 },
  { title: 'Model', key: 'vehicle_model', width: 140 },
  { title: 'Konditsionersiz', key: 'norm_without_ac', width: 140 },
  { title: 'Konditsionerli', key: 'norm_with_ac', width: 140 },
  { title: "Yoqilg'i turi", key: 'fuel_type', width: 120 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 100, align: 'center' },
]

async function load() {
  loading.value = true
  items.value = (await fuelNormApi.list()).data
  loading.value = false
}

function openCreate() {
  editItem.value = null
  form.value = { route_name: '', route_number: '', vehicle_model: '', norm_without_ac: '', norm_with_ac: '', heating_norm: '', fuel_type: 'benzin' }
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  Object.assign(form.value, item)
  dialog.value = true
}

async function save() {
  saving.value = true
  try {
    if (editItem.value) await fuelNormApi.update(editItem.value.id, form.value)
    else await fuelNormApi.create(form.value)
    dialog.value = false
    load()
  } finally {
    saving.value = false
  }
}

async function remove(id) {
  await fuelNormApi.delete(id)
  load()
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Yoqilg'i Me'yorlari</h2>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="openCreate">Qo'shish</v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-data-table :headers="headers" :items="items" :loading="loading" hover rounded="xl">
          <template #item.norm_without_ac="{ item }">{{ item.norm_without_ac }} L/100km</template>
          <template #item.norm_with_ac="{ item }">{{ item.norm_with_ac || '—' }} L/100km</template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" color="primary" @click="openEdit(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
            <v-btn icon size="small" variant="text" color="error" @click="remove(item.id)"><v-icon size="18">mdi-delete</v-icon></v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="580">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Yoqilg'i me'yori</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="8"><v-text-field v-model="form.route_name" label="Yo'nalish nomi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="4"><v-text-field v-model="form.route_number" label="Raqami" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.vehicle_model" label="Avtomobil modeli" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-select v-model="form.fuel_type" :items="['benzin','dizel','gaz']" label="Yoqilg'i" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.norm_without_ac" label="Konditsionersiz (L/100km)" type="number" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.norm_with_ac" label="Konditsionerli (L/100km)" type="number" variant="outlined" rounded="lg" /></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
