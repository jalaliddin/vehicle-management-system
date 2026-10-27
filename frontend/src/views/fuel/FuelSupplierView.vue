<script setup>
import { ref, onMounted } from 'vue'
import { fuelSupplierApi } from '@/services/api'
import { errorMessage } from '@/utils/hr'
import { useSnack } from '@/utils/useSnack'

const { snack, notify } = useSnack()
const items = ref([])
const loading = ref(true)
const dialog = ref(false)
const saving = ref(false)
const form = ref({})

const headers = [
  { title: 'Nomi', key: 'name' },
  { title: 'STIR', key: 'tin', width: 140 },
  { title: 'Telefon', key: 'phone', width: 160 },
  { title: 'Holati', key: 'is_active', width: 110 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 110, align: 'center' },
]

async function load() {
  loading.value = true
  try {
    items.value = (await fuelSupplierApi.list()).data
  } finally {
    loading.value = false
  }
}

function open(item = null) {
  form.value = item ? { ...item } : { id: null, name: '', tin: '', phone: '', is_active: true }
  dialog.value = true
}

async function save() {
  saving.value = true
  try {
    const { id, name, tin, phone, is_active } = form.value
    const data = { name, tin: tin || null, phone: phone || null, is_active }
    if (id) await fuelSupplierApi.update(id, data)
    else await fuelSupplierApi.create(data)
    dialog.value = false
    await load()
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!confirm(`"${item.name}" o'chirilsinmi?`)) return
  try {
    await fuelSupplierApi.delete(item.id)
    await load()
  } catch (e) {
    notify(errorMessage(e), 'error')
  }
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Yetkazib beruvchilar</h2></v-col>
      <v-col cols="auto"><v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="open()">Qo'shish</v-btn></v-col>
    </v-row>
    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-data-table :headers="headers" :items="items" :loading="loading" hover>
          <template #item.tin="{ item }">{{ item.tin || '—' }}</template>
          <template #item.phone="{ item }">{{ item.phone || '—' }}</template>
          <template #item.is_active="{ item }">
            <v-chip size="small" variant="tonal" :color="item.is_active ? 'success' : 'grey'">{{ item.is_active ? 'Faol' : 'Faol emas' }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon size="small" variant="text" color="primary" @click="open(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
            <v-btn icon size="small" variant="text" color="error" @click="remove(item)"><v-icon size="18">mdi-delete</v-icon></v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="460">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ form.id ? 'Tahrirlash' : 'Yangi yetkazib beruvchi' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" label="Nomi" variant="outlined" rounded="lg" />
          <v-text-field v-model="form.tin" label="STIR" variant="outlined" rounded="lg" />
          <v-text-field v-model="form.phone" label="Telefon" variant="outlined" rounded="lg" />
          <v-switch v-model="form.is_active" label="Faol" color="primary" hide-details />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!form.name" @click="save">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="4000">{{ snack.text }}</v-snackbar>
  </v-container>
</template>
