<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { driverApi, employeeApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { employeeStatuses, employmentTypes, errorMessage, genders, label } from '@/utils/hr'
import EmploymentEventsTab from '@/components/hr/EmploymentEventsTab.vue'
import AbsencesTab from '@/components/hr/AbsencesTab.vue'
import MedicalExamsTab from '@/components/hr/MedicalExamsTab.vue'
import BriefingsTab from '@/components/hr/BriefingsTab.vue'
import PpeTab from '@/components/hr/PpeTab.vue'
import HistoryTab from '@/components/hr/HistoryTab.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const employee = ref(null)
const clearance = ref(null)
const photoUrl = ref(null)
const tab = ref('info')
const photoInput = ref(null)

const tabs = computed(() => [
  { value: 'info', title: "Ma'lumotlar", show: true },
  { value: 'events', title: 'Kadrlar tarixi', show: true },
  { value: 'absences', title: "Ta'tillar", show: true },
  { value: 'medical', title: "Tibbiy ko'riklar", show: auth.canAny(['ohs.view', 'ohs.medical.view']) },
  { value: 'briefings', title: "Yo'riqnomalar", show: auth.can('ohs.view') },
  { value: 'ppe', title: 'Maxsus kiyim va SHHV', show: auth.can('ohs.view') },
  { value: 'history', title: 'Tarix', show: auth.can('audit.view') },
].filter(t => t.show))

const details = computed(() => {
  const e = employee.value
  if (!e) return []
  return [
    ['JShShIR', e.pinfl], ['Pasport', e.passport], ["Tug'ilgan sana", e.birth_date], ['Jinsi', e.gender && label(genders, e.gender)],
    ['Telefon', e.phone], ['Manzil', e.address], ["Ma'lumoti", e.education], ['Tabel raqami', e.personnel_number],
    ['Bandlik turi', label(employmentTypes, e.employment_type)], ['Ishga qabul', e.hire_date], ["Bo'shatilgan", e.dismissal_date],
    ["Yillik ta'til", `${e.annual_leave_days} kun`], ["Kiyim o'lchami", e.clothing_size], ["Poyabzal o'lchami", e.shoe_size], ['Izoh', e.notes],
  ]
})

async function load() {
  employee.value = (await employeeApi.show(route.params.id)).data
  if (employee.value.driver && auth.can('drivers.view')) {
    clearance.value = (await driverApi.clearance(employee.value.driver.id)).data
  }
  if (employee.value.has_photo) {
    const res = await employeeApi.photo(employee.value.id)
    if (photoUrl.value) URL.revokeObjectURL(photoUrl.value)
    photoUrl.value = URL.createObjectURL(res.data)
  }
}

async function uploadPhoto(event) {
  const file = event.target.files?.[0]
  if (!file) return
  try {
    await employeeApi.uploadPhoto(employee.value.id, file)
    await load()
  } catch (e) {
    alert(errorMessage(e))
  }
}

async function remove() {
  if (!confirm("Xodim kartochkasi o'chirilsinmi? Faqat xato kiritilgan yozuvlar uchun.")) return
  try {
    await employeeApi.delete(employee.value.id)
    router.push('/employees')
  } catch (e) {
    alert(errorMessage(e))
  }
}

onMounted(load)
onBeforeUnmount(() => photoUrl.value && URL.revokeObjectURL(photoUrl.value))
</script>

<template>
  <v-container v-if="employee" fluid class="pa-6">
    <v-card rounded="xl" class="border mb-4">
      <v-card-text class="pa-5 d-flex align-center ga-4 flex-wrap">
        <v-avatar size="72" color="grey-lighten-3" class="cursor-pointer" @click="auth.can('employees.manage') && photoInput.click()">
          <v-img v-if="photoUrl" :src="photoUrl" cover />
          <v-icon v-else size="36" color="grey">mdi-account</v-icon>
        </v-avatar>
        <input ref="photoInput" type="file" accept="image/*" hidden @change="uploadPhoto">
        <div class="flex-grow-1">
          <div class="text-h6 font-weight-bold">{{ employee.full_name }}</div>
          <div class="text-body-2 text-medium-emphasis">
            {{ [employee.organization?.short_name, employee.department?.name, employee.position?.name].filter(Boolean).join(' · ') }}
          </div>
          <div class="mt-2 d-flex ga-2 flex-wrap">
            <v-chip size="small" variant="tonal" :color="employee.status === 'active' ? 'success' : 'grey'">{{ label(employeeStatuses, employee.status) }}</v-chip>
            <template v-if="clearance">
              <v-chip size="small" variant="tonal" :color="clearance.admitted ? 'success' : 'error'" prepend-icon="mdi-steering">
                {{ clearance.admitted ? "Haydovchi: yo'lga chiqishga ruxsat" : "Haydovchi: ruxsat yo'q" }}
              </v-chip>
              <v-chip v-for="reason in clearance.reasons" :key="reason.code" size="small" variant="outlined" color="error">{{ reason.message }}</v-chip>
            </template>
          </div>
        </div>
        <div v-if="auth.can('employees.manage')" class="d-flex ga-2">
          <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-pencil" @click="router.push(`/employees/${employee.id}/edit`)">Tahrirlash</v-btn>
          <v-btn variant="text" color="error" icon="mdi-delete" @click="remove" />
        </div>
      </v-card-text>
    </v-card>

    <v-card rounded="xl" class="border">
      <v-tabs v-model="tab" color="primary" show-arrows>
        <v-tab v-for="t in tabs" :key="t.value" :value="t.value">{{ t.title }}</v-tab>
      </v-tabs>
      <v-divider />
      <v-card-text class="pa-5">
        <v-window v-model="tab">
          <v-window-item value="info">
            <v-row dense>
              <v-col v-for="[title, value] in details" :key="title" cols="12" sm="6" md="4">
                <div class="text-caption text-medium-emphasis">{{ title }}</div>
                <div class="text-body-2">{{ value || '—' }}</div>
              </v-col>
            </v-row>
          </v-window-item>
          <v-window-item value="events"><EmploymentEventsTab :employee="employee" @changed="load" /></v-window-item>
          <v-window-item value="absences"><AbsencesTab :employee="employee" /></v-window-item>
          <v-window-item value="medical"><MedicalExamsTab :employee="employee" /></v-window-item>
          <v-window-item value="briefings"><BriefingsTab :employee="employee" /></v-window-item>
          <v-window-item value="ppe"><PpeTab :employee="employee" /></v-window-item>
          <v-window-item value="history"><HistoryTab :employee="employee" /></v-window-item>
        </v-window>
      </v-card-text>
    </v-card>
  </v-container>
</template>
