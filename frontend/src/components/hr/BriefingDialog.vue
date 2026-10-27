<script setup>
import { ref, computed, watch } from 'vue'
import { briefingApi, departmentApi, employeeApi, organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { briefingTypes, errorMessage, today } from '@/utils/hr'

const props = defineProps({ modelValue: Boolean, briefing: { type: Object, default: null } })
const emit = defineEmits(['update:modelValue', 'saved'])
const auth = useAuthStore()

const form = ref({})
const organizations = ref([])
const departments = ref([])
const employees = ref([])
const saving = ref(false)
const failure = ref('')
const typeOptions = Object.entries(briefingTypes).map(([value, title]) => ({ value, title }))
const show = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

async function loadOrganization(organizationId) {
  if (!organizationId) return
  const [deps, emps] = await Promise.all([
    departmentApi.list({ organization_id: organizationId }),
    employeeApi.list({ organization_id: organizationId, status: 'active', page: 1 }),
  ])
  departments.value = deps.data
  employees.value = await allPages(emps.data, { organization_id: organizationId, status: 'active' })
}

// The employee list is paginated; collect every page for the participant picker.
async function allPages(first, params) {
  const rows = [...first.data]
  for (let page = 2; page <= first.meta.last_page; page++) {
    rows.push(...(await employeeApi.list({ ...params, page })).data.data)
  }
  return rows
}

function addDepartment(departmentId) {
  const ids = employees.value.filter(e => e.department?.id === departmentId).map(e => e.id)
  form.value.participant_ids = [...new Set([...form.value.participant_ids, ...ids])]
}

watch(show, async (open) => {
  if (!open) return
  failure.value = ''
  if (!organizations.value.length) organizations.value = auth.assignableOrganizations((await organizationApi.list()).data)
  const b = props.briefing
  form.value = b
    ? { organization_id: b.organization_id, type: b.type, conducted_on: b.conducted_on.slice(0, 10), topic: b.topic,
        instructor_employee_id: b.instructor_employee_id, instructor_name: b.instructor_name, notes: b.notes,
        participant_ids: b.participants.map(p => p.id) }
    : { organization_id: auth.user?.organization_id ?? organizations.value[0]?.id, type: 'repeated', conducted_on: today(), topic: '',
        instructor_employee_id: null, instructor_name: '', notes: '', participant_ids: [] }
  await loadOrganization(form.value.organization_id)
})

async function onOrganizationChange(id) {
  form.value.participant_ids = []
  form.value.instructor_employee_id = null
  await loadOrganization(id)
}

async function save() {
  saving.value = true
  failure.value = ''
  try {
    const data = { ...form.value, instructor_name: form.value.instructor_name || null }
    if (props.briefing) await briefingApi.update(props.briefing.id, data)
    else await briefingApi.create(data)
    show.value = false
    emit('saved')
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog v-model="show" max-width="720">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-2">{{ briefing ? "Yo'riqnomani tahrirlash" : "Yo'riqnoma o'tkazish" }}</v-card-title>
      <v-card-text>
        <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
        <v-row dense>
          <v-col v-if="!briefing" cols="12">
            <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" label="Tashkilot" variant="outlined" rounded="lg" @update:model-value="onOrganizationChange" />
          </v-col>
          <v-col cols="6"><v-select v-model="form.type" :items="typeOptions" label="Turi" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="6"><v-text-field v-model="form.conducted_on" type="date" label="Sana" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12"><v-text-field v-model="form.topic" label="Mavzu" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="6">
            <v-autocomplete v-model="form.instructor_employee_id" :items="employees" item-title="full_name" item-value="id" label="O'tkazgan xodim" variant="outlined" rounded="lg" clearable />
          </v-col>
          <v-col cols="6"><v-text-field v-model="form.instructor_name" label="yoki tashqi mutaxassis" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12">
            <v-autocomplete
              v-model="form.participant_ids" :items="employees" item-title="full_name" item-value="id" label="Qatnashchilar"
              variant="outlined" rounded="lg" multiple chips closable-chips
            />
          </v-col>
          <v-col cols="12" class="d-flex flex-wrap ga-2 align-center">
            <span class="text-caption text-medium-emphasis">Bo'limni to'liq qo'shish:</span>
            <v-chip v-for="d in departments" :key="d.id" size="small" variant="outlined" @click="addDepartment(d.id)">+ {{ d.name }}</v-chip>
          </v-col>
          <v-col cols="12"><v-textarea v-model="form.notes" label="Izoh" rows="2" variant="outlined" rounded="lg" /></v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="pa-5 pt-0">
        <span class="text-caption text-medium-emphasis">{{ form.participant_ids?.length || 0 }} qatnashchi</span>
        <v-spacer />
        <v-btn variant="text" @click="show = false">Bekor</v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!form.topic || !form.participant_ids?.length" @click="save">Saqlash</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
