<script setup>
import { ref, computed, onMounted } from 'vue'
import { employeeApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, examResults, examStatusColors, examStatuses, examTypes, label, saveBlob, today } from '@/utils/hr'

const props = defineProps({ employee: { type: Object, required: true } })
const auth = useAuthStore()

const exams = ref([])
const loading = ref(true)
const dialog = ref(false)
const saving = ref(false)
const failure = ref('')
const form = ref({})
const docFile = ref(null)

const canManage = computed(() => auth.can('ohs.medical.manage'))
const canSeeDetails = computed(() => auth.can('ohs.medical.view'))
const typeOptions = Object.entries(examTypes).map(([value, title]) => ({ value, title }))
const resultOptions = Object.entries(examResults).map(([value, title]) => ({ value, title }))

const headers = computed(() => [
  { title: 'Turi', key: 'type' },
  { title: "Ko'rik sanasi", key: 'exam_date', width: 130 },
  ...(canSeeDetails.value ? [{ title: 'Muassasa', key: 'institution' }, { title: 'Xulosa', key: 'result' }] : []),
  { title: 'Keyingi ko\'rik', key: 'next_due_date', width: 130 },
  { title: 'Holati', key: 'status', width: 140 },
  { title: '', key: 'actions', sortable: false, width: 140, align: 'end' },
])

async function load() {
  loading.value = true
  try {
    exams.value = (await employeeApi.medicalExams(props.employee.id)).data
  } finally {
    loading.value = false
  }
}

function open(exam = null) {
  failure.value = ''
  docFile.value = null
  form.value = exam
    ? { id: exam.id, type: exam.type, exam_date: exam.exam_date, institution: exam.institution, result: exam.result, restrictions: exam.restrictions, next_due_date: exam.next_due_date, notes: exam.notes }
    : { id: null, type: 'periodic', exam_date: today(), institution: '', result: 'fit', restrictions: '', next_due_date: null, notes: '' }
  dialog.value = true
}

async function save() {
  saving.value = true
  failure.value = ''
  try {
    const data = new FormData()
    for (const [key, value] of Object.entries(form.value)) {
      if (key !== 'id' && value !== null && value !== '') data.append(key, value)
    }
    const file = Array.isArray(docFile.value) ? docFile.value[0] : docFile.value
    if (file) data.append('document', file)
    if (form.value.id) await employeeApi.updateMedicalExam(form.value.id, data)
    else await employeeApi.addMedicalExam(props.employee.id, data)
    dialog.value = false
    await load()
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(exam) {
  if (!confirm("Tibbiy ko'rik yozuvi o'chirilsinmi?")) return
  await employeeApi.deleteMedicalExam(exam.id)
  await load()
}

async function download(exam) {
  saveBlob(await employeeApi.medicalDocument(exam.id), `tibbiy-xulosa-${exam.exam_date}.pdf`)
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <span v-if="employee.position?.medical_exam_months" class="text-body-2 text-medium-emphasis">
        Lavozim bo'yicha davriy ko'rik: har {{ employee.position.medical_exam_months }} oyda
      </span>
      <v-spacer />
      <v-btn v-if="canManage" color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="open()">Ko'rik qo'shish</v-btn>
    </div>

    <v-data-table :headers="headers" :items="exams" :loading="loading" density="compact">
      <template #item.type="{ item }">{{ label(examTypes, item.type) }}</template>
      <template #item.result="{ item }">{{ label(examResults, item.result) }}<div v-if="item.restrictions" class="text-caption">{{ item.restrictions }}</div></template>
      <template #item.next_due_date="{ item }">{{ item.next_due_date || '—' }}</template>
      <template #item.status="{ item }">
        <v-chip size="small" variant="tonal" :color="examStatusColors[item.status]">{{ label(examStatuses, item.status) }}</v-chip>
      </template>
      <template #item.actions="{ item }">
        <v-btn v-if="item.has_document" icon size="small" variant="text" title="Xulosa" @click="download(item)"><v-icon size="18">mdi-file-download</v-icon></v-btn>
        <template v-if="canManage">
          <v-btn icon size="small" variant="text" color="primary" @click="open(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
          <v-btn icon size="small" variant="text" color="error" @click="remove(item)"><v-icon size="18">mdi-delete</v-icon></v-btn>
        </template>
      </template>
    </v-data-table>

    <v-dialog v-model="dialog" max-width="560">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Tibbiy ko'rik</v-card-title>
        <v-card-text>
          <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
          <v-row dense>
            <v-col cols="6"><v-select v-model="form.type" :items="typeOptions" label="Turi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.exam_date" type="date" label="Ko'rik sanasi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12"><v-text-field v-model="form.institution" label="Tibbiyot muassasasi" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12"><v-select v-model="form.result" :items="resultOptions" label="Xulosa" variant="outlined" rounded="lg" /></v-col>
            <v-col v-if="form.result === 'fit_with_restrictions'" cols="12"><v-textarea v-model="form.restrictions" label="Cheklovlar" rows="2" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="12">
              <v-text-field
                v-model="form.next_due_date" type="date" label="Keyingi ko'rik" variant="outlined" rounded="lg"
                hint="Bo'sh qoldirilsa, lavozim davriyligi bo'yicha hisoblanadi" persistent-hint
              />
            </v-col>
            <v-col cols="12"><v-file-input v-model="docFile" accept=".pdf,.jpg,.jpeg,.png" label="Xulosa fayli (PDF yoki rasm)" variant="outlined" rounded="lg" prepend-icon="" prepend-inner-icon="mdi-paperclip" /></v-col>
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
