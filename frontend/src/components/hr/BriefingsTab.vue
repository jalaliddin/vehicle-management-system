<script setup>
import { ref, onMounted } from 'vue'
import { briefingApi } from '@/services/api'
import { briefingTypes, label } from '@/utils/hr'

const props = defineProps({ employee: { type: Object, required: true } })

const briefings = ref([])
const loading = ref(true)
const headers = [
  { title: 'Sana', key: 'conducted_on', width: 120 },
  { title: 'Turi', key: 'type', width: 170 },
  { title: 'Mavzu', key: 'topic' },
  { title: "O'tkazgan", key: 'instructor' },
]

onMounted(async () => {
  try {
    briefings.value = (await briefingApi.list({ employee_id: props.employee.id })).data.data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div v-if="employee.position?.briefing_months" class="text-body-2 text-medium-emphasis mb-3">
      Lavozim bo'yicha takroriy yo'riqnoma: har {{ employee.position.briefing_months }} oyda
    </div>
    <v-data-table :headers="headers" :items="briefings" :loading="loading" density="compact">
      <template #item.conducted_on="{ item }">{{ item.conducted_on.slice(0, 10) }}</template>
      <template #item.type="{ item }">{{ label(briefingTypes, item.type) }}</template>
      <template #item.instructor="{ item }">
        {{ item.instructor ? [item.instructor.last_name, item.instructor.first_name].join(' ') : item.instructor_name || '—' }}
      </template>
    </v-data-table>
  </div>
</template>
