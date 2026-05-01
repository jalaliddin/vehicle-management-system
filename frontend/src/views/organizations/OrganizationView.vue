<script setup>
import { ref, onMounted } from 'vue'
import { organizationApi } from '@/services/api'

const items = ref([])
const loading = ref(true)
const dialog = ref(false)
const editItem = ref(null)
const saving = ref(false)

const form = ref({ name: '', short_name: '', code: '', address: '', phone: '', director: '', is_active: true })

const headers = [
  { title: 'Nomi', key: 'name' },
  { title: 'Qisqa nomi', key: 'short_name', width: 140 },
  { title: 'Kod', key: 'code', width: 120 },
  { title: 'Direktor', key: 'director' },
  { title: 'Holat', key: 'is_active', width: 100 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 100, align: 'center' },
]

async function load() {
  loading.value = true
  items.value = (await organizationApi.list()).data
  loading.value = false
}

function openCreate() {
  editItem.value = null
  form.value = { name: '', short_name: '', code: '', address: '', phone: '', director: '', is_active: true }
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
    if (editItem.value) await organizationApi.update(editItem.value.id, form.value)
    else await organizationApi.create(form.value)
    dialog.value = false
    load()
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Tashkilotlar</h2>
        <p class="text-body-2 text-medium-emphasis">14 ta quyi tashkilot</p>
      </v-col>
      <v-col cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="openCreate">Qo'shish</v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-data-table :headers="headers" :items="items" :loading="loading" hover rounded="xl">
          <template #item.is_active="{ item }">
            <v-chip :color="item.is_active ? 'success' : 'grey'" size="small" variant="flat">
              {{ item.is_active ? 'Faol' : 'Faol emas' }}
            </v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" color="primary" @click="openEdit(item)">
              <v-icon size="18">mdi-pencil</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="600">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ editItem ? 'Tahrirlash' : "Yangi tashkilot" }}</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12"><v-text-field v-model="form.name" label="To'liq nomi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.short_name" label="Qisqa nomi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.code" label="Kod" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.director" label="Direktor" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.phone" label="Telefon" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12"><v-text-field v-model="form.address" label="Manzil" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12"><v-switch v-model="form.is_active" label="Faol" color="primary" inset /></v-col>
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
