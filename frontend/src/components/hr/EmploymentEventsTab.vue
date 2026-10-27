<script setup>
import { ref, computed, onMounted } from 'vue'
import { departmentApi, employeeApi, organizationApi, positionApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { dismissalReasons, errorMessage, eventTypes, label, today } from '@/utils/hr'

const props = defineProps({ employee: { type: Object, required: true } })
const emit = defineEmits(['changed'])
const auth = useAuthStore()

const events = ref([])
const loading = ref(true)
const dialog = ref(false)
const saving = ref(false)
const failure = ref('')
const form = ref({})
const organizations = ref([])
const departments = ref([])
const positions = ref([])

const canManage = computed(() => auth.can('employees.manage'))
const isActive = computed(() => props.employee.status === 'active')
const latestId = computed(() => events.value.find(e => !e.cancelled_at)?.id)
const reasonOptions = Object.entries(dismissalReasons).map(([value, title]) => ({ value, title }))

async function load() {
  loading.value = true
  try {
    events.value = (await employeeApi.events(props.employee.id)).data
  } finally {
    loading.value = false
  }
}

async function loadDepartments(organizationId) {
  departments.value = organizationId ? (await departmentApi.list({ organization_id: organizationId })).data : []
}

async function open(type) {
  failure.value = ''
  form.value = {
    type, effective_date: today(), order_number: '', order_date: today(),
    organization_id: props.employee.organization_id,
    department_id: props.employee.department_id,
    position_id: props.employee.position_id,
    dismissal_reason: null, dismissal_article: '', notes: '',
  }
  if (!positions.value.length) {
    const [orgs, pos] = await Promise.all([organizationApi.list(), positionApi.list()])
    organizations.value = auth.assignableOrganizations(orgs.data)
    positions.value = pos.data
  }
  await loadDepartments(form.value.organization_id)
  dialog.value = true
}

async function onOrganizationChange(id) {
  form.value.department_id = null
  await loadDepartments(id)
}

async function save() {
  saving.value = true
  failure.value = ''
  try {
    const { type } = form.value
    const data = { ...form.value }
    if (type === 'dismissal') {
      delete data.organization_id
      delete data.department_id
      delete data.position_id
    }
    await employeeApi.addEvent(props.employee.id, data)
    dialog.value = false
    await load()
    emit('changed')
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function cancel(event) {
  const reason = prompt('Bekor qilish sababi:')
  if (!reason) return
  try {
    await employeeApi.cancelEvent(event.id, reason)
    await load()
    emit('changed')
  } catch (e) {
    alert(errorMessage(e))
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="canManage" class="d-flex ga-2 mb-4">
      <template v-if="isActive">
        <v-btn variant="tonal" color="primary" rounded="lg" prepend-icon="mdi-swap-horizontal" @click="open('transfer')">O'tkazish</v-btn>
        <v-btn variant="tonal" color="error" rounded="lg" prepend-icon="mdi-account-remove" @click="open('dismissal')">Ishdan bo'shatish</v-btn>
      </template>
      <v-btn v-else variant="tonal" color="success" rounded="lg" prepend-icon="mdi-account-plus" @click="open('hire')">Qayta ishga qabul</v-btn>
    </div>

    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <div v-else-if="!events.length" class="text-medium-emphasis pa-4">Kadrlar buyruqlari kiritilmagan</div>
    <v-timeline v-else side="end" density="compact" align="start">
      <v-timeline-item
        v-for="event in events" :key="event.id" size="small"
        :dot-color="event.cancelled_at ? 'grey' : { hire: 'success', transfer: 'primary', dismissal: 'error' }[event.type]"
      >
        <div :class="{ 'text-disabled': event.cancelled_at }">
          <div class="font-weight-bold">
            {{ label(eventTypes, event.type) }} — {{ event.effective_date?.slice(0, 10) }}
            <v-chip v-if="event.cancelled_at" size="x-small" class="ml-2">Bekor qilingan</v-chip>
          </div>
          <div class="text-body-2">
            Buyruq № {{ event.order_number }} ({{ event.order_date?.slice(0, 10) }})
          </div>
          <div class="text-body-2 text-medium-emphasis">
            {{ [event.organization?.short_name, event.department?.name, event.position?.name].filter(Boolean).join(' · ') }}
          </div>
          <div v-if="event.dismissal_reason" class="text-body-2">
            Asos: {{ label(dismissalReasons, event.dismissal_reason) }}<span v-if="event.dismissal_article">, {{ event.dismissal_article }}</span>
          </div>
          <div v-if="event.cancel_reason" class="text-body-2">Bekor qilish sababi: {{ event.cancel_reason }}</div>
          <v-btn
            v-if="canManage && event.id === latestId && events.filter(e => !e.cancelled_at).length > 1"
            size="small" variant="text" color="error" class="px-0" @click="cancel(event)"
          >Bekor qilish</v-btn>
        </div>
      </v-timeline-item>
    </v-timeline>

    <v-dialog v-model="dialog" max-width="560">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ label(eventTypes, form.type) }}</v-card-title>
        <v-card-text>
          <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
          <v-row dense>
            <v-col cols="12" md="4"><v-text-field v-model="form.effective_date" type="date" label="Sana" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6" md="4"><v-text-field v-model="form.order_number" label="Buyruq raqami" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6" md="4"><v-text-field v-model="form.order_date" type="date" label="Buyruq sanasi" variant="outlined" rounded="lg" /></v-col>
            <template v-if="form.type !== 'dismissal'">
              <v-col v-if="auth.canAccessAllOrganizations" cols="12">
                <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" label="Tashkilot" variant="outlined" rounded="lg" @update:model-value="onOrganizationChange" />
              </v-col>
              <v-col cols="12" md="6"><v-select v-model="form.department_id" :items="departments" item-title="name" item-value="id" label="Bo'lim" variant="outlined" rounded="lg" clearable /></v-col>
              <v-col cols="12" md="6"><v-autocomplete v-model="form.position_id" :items="positions" item-title="name" item-value="id" label="Lavozim" variant="outlined" rounded="lg" /></v-col>
            </template>
            <template v-else>
              <v-col cols="12"><v-select v-model="form.dismissal_reason" :items="reasonOptions" label="Asos" variant="outlined" rounded="lg" /></v-col>
              <v-col cols="12"><v-text-field v-model="form.dismissal_article" label="Mehnat kodeksi moddasi" variant="outlined" rounded="lg" /></v-col>
            </template>
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
