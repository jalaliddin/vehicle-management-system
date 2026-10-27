<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { medicalExamApi } from '@/services/api'
import { examStatusColors, examStatuses, examTypes, label } from '@/utils/hr'

const router = useRouter()
const exams = ref([])
const loading = ref(false)
const state = ref('overdue')
const stateOptions = [{ value: null, title: 'Barchasi' }, ...Object.entries(examStatuses).map(([value, title]) => ({ value, title }))]

const headers = [
  { title: 'Xodim', key: 'employee.full_name' },
  { title: 'Turi', key: 'type', width: 170 },
  { title: "Oxirgi ko'rik", key: 'exam_date', width: 130 },
  { title: "Keyingi ko'rik", key: 'next_due_date', width: 130 },
  { title: 'Holati', key: 'status', width: 150 },
]

async function load() {
  loading.value = true
  try {
    exams.value = (await medicalExamApi.list(state.value ? { state: state.value } : {})).data
  } finally {
    loading.value = false
  }
}

watch(state, load)
onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Tibbiy ko'riklar</h2></v-col>
      <v-col cols="12" md="3"><v-select v-model="state" :items="stateOptions" label="Holati" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
    </v-row>
    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <div class="text-caption text-medium-emphasis mb-2">Har bir ishlayotgan xodimning oxirgi ko'rigi. Ko'rik kiritish — xodim kartochkasida.</div>
        <v-data-table :headers="headers" :items="exams" :loading="loading" hover @click:row="(_, { item }) => router.push(`/employees/${item.employee_id}`)">
          <template #item.type="{ item }">{{ label(examTypes, item.type) }}</template>
          <template #item.next_due_date="{ item }">{{ item.next_due_date || '—' }}</template>
          <template #item.status="{ item }">
            <v-chip size="small" variant="tonal" :color="examStatusColors[item.status]">{{ label(examStatuses, item.status) }}</v-chip>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>
