<script setup>
import { ref, onMounted } from 'vue'
import { employeeApi } from '@/services/api'
import { auditEvents, label } from '@/utils/hr'

const props = defineProps({ employee: { type: Object, required: true } })

const entries = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)

const headers = [
  { title: 'Vaqt', key: 'created_at', width: 170 },
  { title: 'Foydalanuvchi', key: 'user.name', width: 180 },
  { title: 'Amal', key: 'event', width: 150 },
  { title: 'Obyekt', key: 'auditable_type', width: 170 },
  { title: "O'zgarishlar", key: 'changes' },
]

const subject = (type) => ({
  Employee: 'Xodim', EmploymentEvent: 'Kadrlar buyrug\'i', EmployeeAbsence: "Ta'til / yo'qlik",
  MedicalExam: "Tibbiy ko'rik", PpeIssue: 'SHHV', SafetyBriefing: "Yo'riqnoma",
}[type?.split('\\').pop()] ?? type?.split('\\').pop())

const changes = (entry) => {
  const keys = Object.keys(entry.new_values || entry.old_values || {}).filter(k => !['id', 'employee_id', 'created_by'].includes(k))
  return keys.map((k) => entry.old_values && entry.new_values
    ? `${k}: ${entry.old_values[k] ?? '—'} → ${entry.new_values[k] ?? '—'}`
    : `${k}: ${(entry.new_values || entry.old_values)[k] ?? '—'}`).join('; ')
}

async function load() {
  loading.value = true
  try {
    const res = await employeeApi.history(props.employee.id, { page: page.value })
    entries.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <v-data-table-server
    v-model:page="page" :headers="headers" :items="entries" :items-length="total" :items-per-page="50" :items-per-page-options="[50]"
    :loading="loading" density="compact" @update:page="load"
  >
    <template #item.created_at="{ item }">{{ new Date(item.created_at).toLocaleString('uz-UZ') }}</template>
    <template #item.user.name="{ item }">{{ item.user?.name || 'Tizim' }}</template>
    <template #item.event="{ item }">{{ label(auditEvents, item.event) }}</template>
    <template #item.auditable_type="{ item }">{{ subject(item.auditable_type) }}</template>
    <template #item.changes="{ item }"><span class="text-caption">{{ changes(item) }}</span></template>
  </v-data-table-server>
</template>
