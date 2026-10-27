<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { employeeApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { absenceTypes, errorMessage, label, today } from '@/utils/hr'

const props = defineProps({ employee: { type: Object, required: true } })
const auth = useAuthStore()

const year = ref(new Date().getFullYear())
const years = computed(() => Array.from({ length: 6 }, (_, i) => new Date().getFullYear() + 1 - i))
const absences = ref([])
const balance = ref(null)
const loading = ref(true)
const dialog = ref(false)
const saving = ref(false)
const failure = ref('')
const form = ref({})
const typeOptions = Object.entries(absenceTypes).map(([value, title]) => ({ value, title }))
const canManage = computed(() => auth.can('employees.manage'))

const headers = [
  { title: 'Turi', key: 'type' },
  { title: 'Boshlanishi', key: 'start_date', width: 120 },
  { title: 'Tugashi', key: 'end_date', width: 120 },
  { title: 'Kun', key: 'days', width: 70 },
  { title: 'Reja', key: 'is_planned', width: 80 },
  { title: 'Hujjat', key: 'document_number' },
  { title: '', key: 'actions', sortable: false, width: 100, align: 'end' },
]

async function load() {
  loading.value = true
  try {
    const [list, bal] = await Promise.all([
      employeeApi.absences(props.employee.id, { year: year.value }),
      employeeApi.leaveBalance(props.employee.id, year.value),
    ])
    absences.value = list.data
    balance.value = bal.data
  } finally {
    loading.value = false
  }
}

function open(absence = null) {
  failure.value = ''
  form.value = absence
    ? { ...absence, start_date: absence.start_date.slice(0, 10), end_date: absence.end_date.slice(0, 10), document_date: absence.document_date?.slice(0, 10) ?? null }
    : { id: null, type: 'annual_leave', start_date: today(), end_date: today(), is_planned: false, document_number: '', document_date: null, notes: '' }
  dialog.value = true
}

async function save() {
  saving.value = true
  failure.value = ''
  try {
    const { id, type, start_date, end_date, is_planned, document_number, document_date, notes } = form.value
    const data = { type, start_date, end_date, is_planned, document_number: document_number || null, document_date: document_date || null, notes: notes || null }
    if (id) await employeeApi.updateAbsence(id, data)
    else await employeeApi.addAbsence(props.employee.id, data)
    dialog.value = false
    await load()
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(absence) {
  if (!confirm("Yozuv o'chirilsinmi?")) return
  await employeeApi.deleteAbsence(absence.id)
  await load()
}

watch(year, load)
onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center ga-3 mb-4 flex-wrap">
      <v-select v-model="year" :items="years" label="Yil" variant="outlined" density="compact" rounded="lg" hide-details style="max-width: 120px;" />
      <template v-if="balance">
        <v-chip variant="tonal">Ta'til huquqi: {{ balance.entitlement }} kun</v-chip>
        <v-chip variant="tonal" color="primary">Foydalanilgan: {{ balance.used }}</v-chip>
        <v-chip variant="tonal" :color="balance.remaining < 0 ? 'error' : 'success'">Qolgan: {{ balance.remaining }}</v-chip>
      </template>
      <v-spacer />
      <v-btn v-if="canManage" color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="open()">Qo'shish</v-btn>
    </div>

    <v-data-table :headers="headers" :items="absences" :loading="loading" density="compact">
      <template #item.type="{ item }">{{ label(absenceTypes, item.type) }}</template>
      <template #item.start_date="{ item }">{{ item.start_date.slice(0, 10) }}</template>
      <template #item.end_date="{ item }">{{ item.end_date.slice(0, 10) }}</template>
      <template #item.is_planned="{ item }"><v-icon v-if="item.is_planned" size="18" color="info">mdi-calendar-clock</v-icon></template>
      <template #item.document_number="{ item }">{{ item.document_number || '—' }}</template>
      <template #item.actions="{ item }">
        <template v-if="canManage">
          <v-btn icon size="small" variant="text" color="primary" @click="open(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
          <v-btn icon size="small" variant="text" color="error" @click="remove(item)"><v-icon size="18">mdi-delete</v-icon></v-btn>
        </template>
      </template>
    </v-data-table>

    <v-dialog v-model="dialog" max-width="520">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ form.id ? 'Tahrirlash' : "Ta'til yoki yo'qlik" }}</v-card-title>
        <v-card-text>
          <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
          <v-row dense>
            <v-col cols="12"><v-select v-model="form.type" :items="typeOptions" label="Turi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.start_date" type="date" label="Boshlanishi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.end_date" type="date" label="Tugashi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12"><v-checkbox v-model="form.is_planned" label="Rejalashtirilgan (ta'til jadvali)" density="compact" hide-details /></v-col>
            <v-col cols="6"><v-text-field v-model="form.document_number" label="Hujjat raqami" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.document_date" type="date" label="Hujjat sanasi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12"><v-textarea v-model="form.notes" label="Izoh" rows="2" variant="outlined" rounded="lg" /></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
