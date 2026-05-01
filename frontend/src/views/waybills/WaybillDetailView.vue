<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { waybillApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const waybill = ref(null)
const loading = ref(true)
const checkDialog = ref(false)
const checkPassed = ref(true)
const checkNotes = ref('')
const submitting = ref(false)
const checkType = ref('')

const statusSteps = ['draft', 'mechanic_check', 'mechanic_ok', 'doctor_check', 'doctor_ok', 'hq_review', 'approved', 'in_progress', 'completed']
const statusLabel = (s) => ({
  draft: 'Yaratilgan', mechanic_check: 'Mexanik tekshiruvi', mechanic_ok: 'Mexanik OK',
  doctor_check: 'Shifokor tekshiruvi', doctor_ok: 'Shifokor OK', hq_review: "Markaziy apparat",
  approved: 'Tasdiqlandi', in_progress: "Yo'lda", completed: 'Bajarildi', cancelled: 'Bekor qilindi',
}[s] || s)

const statusColor = (s) => ({
  draft: 'grey', mechanic_check: 'warning', mechanic_ok: 'info',
  doctor_check: 'warning', doctor_ok: 'info', hq_review: 'warning',
  approved: 'success', in_progress: 'success', completed: 'primary', cancelled: 'error',
}[s] || 'grey')

async function load() {
  const res = await waybillApi.show(route.params.id)
  waybill.value = res.data
  loading.value = false
}

async function submitToMechanic() {
  await waybillApi.submit(waybill.value.id)
  load()
}

async function submitCheck() {
  submitting.value = true
  try {
    if (checkType.value === 'mechanic') {
      await waybillApi.mechanicCheck(waybill.value.id, { passed: checkPassed.value, notes: checkNotes.value })
    } else {
      await waybillApi.doctorCheck(waybill.value.id, { passed: checkPassed.value, notes: checkNotes.value })
    }
    checkDialog.value = false
    load()
  } finally {
    submitting.value = false
  }
}

async function approve() {
  await waybillApi.approve(waybill.value.id)
  load()
}

async function depart() {
  await waybillApi.depart(waybill.value.id)
  load()
}

async function complete() {
  await waybillApi.complete(waybill.value.id, { actual_km: waybill.value.actual_km, fuel_consumed: waybill.value.fuel_consumed })
  load()
}

function openCheck(type, passed) {
  checkType.value = type
  checkPassed.value = passed
  checkNotes.value = ''
  checkDialog.value = true
}

onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6" style="max-width:900px;">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-4" @click="router.back()">Orqaga</v-btn>

    <v-skeleton-loader v-if="loading" type="article" rounded="xl" />

    <template v-else-if="waybill">
      <v-card rounded="xl" class="border mb-4">
        <v-card-text class="pa-6">
          <div class="d-flex align-center gap-3 mb-4">
            <div>
              <h2 class="text-h5 font-weight-bold">{{ waybill.waybill_number }}</h2>
              <p class="text-medium-emphasis">{{ waybill.organization?.name }}</p>
            </div>
            <v-spacer />
            <v-chip :color="statusColor(waybill.status)" variant="flat" size="large">
              {{ statusLabel(waybill.status) }}
            </v-chip>
          </div>

          <!-- Action buttons -->
          <div class="d-flex gap-2 flex-wrap mb-4">
            <v-btn v-if="waybill.status === 'draft'" color="primary" variant="flat" rounded="lg" size="small" @click="submitToMechanic">
              <v-icon start size="16">mdi-send</v-icon> Mexanikga yuborish
            </v-btn>
            <v-btn v-if="waybill.status === 'mechanic_check' && auth.isMechanic" color="success" variant="flat" rounded="lg" size="small" @click="openCheck('mechanic', true)">
              <v-icon start size="16">mdi-check</v-icon> Tasdiqlash
            </v-btn>
            <v-btn v-if="waybill.status === 'mechanic_check' && auth.isMechanic" color="error" variant="outlined" rounded="lg" size="small" @click="openCheck('mechanic', false)">
              <v-icon start size="16">mdi-close</v-icon> Rad etish
            </v-btn>
            <v-btn v-if="waybill.status === 'mechanic_ok' || waybill.status === 'doctor_check' && auth.isDoctor" color="success" variant="flat" rounded="lg" size="small" @click="openCheck('doctor', true)">
              <v-icon start size="16">mdi-stethoscope</v-icon> Shifokor: Tasdiqlash
            </v-btn>
            <v-btn v-if="waybill.status === 'doctor_ok' && auth.isHqDispatcher" color="success" variant="flat" rounded="lg" size="small" @click="approve">
              <v-icon start size="16">mdi-check-all</v-icon> Tasdiqlash
            </v-btn>
            <v-btn v-if="waybill.status === 'approved'" color="primary" variant="flat" rounded="lg" size="small" @click="depart">
              <v-icon start size="16">mdi-car-arrow-right</v-icon> Yo'lga chiqdi
            </v-btn>
            <v-btn v-if="waybill.status === 'in_progress'" color="warning" variant="flat" rounded="lg" size="small" @click="complete">
              <v-icon start size="16">mdi-flag-checkered</v-icon> Yakunlash
            </v-btn>
          </div>

          <v-divider class="mb-4" />

          <v-row>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Avtomobil</div><div class="font-weight-medium">{{ waybill.vehicle?.state_number }}</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Haydovchi</div><div class="font-weight-medium">{{ waybill.driver?.full_name }}</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Yo'nalish</div><div class="font-weight-medium">{{ waybill.destination }}</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Sana</div><div class="font-weight-medium">{{ waybill.created_date }}</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Rejalashtirilgan km</div><div class="font-weight-medium">{{ waybill.planned_km || '—' }} km</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Haqiqiy km</div><div class="font-weight-medium">{{ waybill.actual_km || '—' }} km</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Berilgan yoqilg'i</div><div class="font-weight-medium">{{ waybill.fuel_issued || '—' }} L</div></v-col>
            <v-col cols="6" md="3"><div class="text-caption text-medium-emphasis">Sarflangan yoqilg'i</div><div class="font-weight-medium">{{ waybill.fuel_consumed || '—' }} L</div></v-col>
          </v-row>

          <!-- Mexanik / Shifokor natijalar -->
          <template v-if="waybill.mechanic_notes || waybill.doctor_notes">
            <v-divider class="my-4" />
            <v-row>
              <v-col v-if="waybill.mechanic_notes" cols="12" md="6">
                <v-alert :type="waybill.mechanic_passed ? 'success' : 'error'" variant="tonal" rounded="lg" density="compact">
                  <strong>Mexanik:</strong> {{ waybill.mechanic_notes }}
                </v-alert>
              </v-col>
              <v-col v-if="waybill.doctor_notes" cols="12" md="6">
                <v-alert :type="waybill.doctor_passed ? 'success' : 'error'" variant="tonal" rounded="lg" density="compact">
                  <strong>Shifokor:</strong> {{ waybill.doctor_notes }}
                </v-alert>
              </v-col>
            </v-row>
          </template>
        </v-card-text>
      </v-card>
    </template>

    <!-- Check Dialog -->
    <v-dialog v-model="checkDialog" max-width="480" persistent>
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">
          <v-icon :color="checkPassed ? 'success' : 'error'" class="mr-2">
            {{ checkPassed ? 'mdi-check-circle' : 'mdi-close-circle' }}
          </v-icon>
          {{ checkPassed ? 'Tasdiqlash' : 'Rad etish' }}
        </v-card-title>
        <v-card-text>
          <v-textarea v-model="checkNotes" :label="checkPassed ? 'Izoh (ixtiyoriy)' : 'Sabab (majburiy)'"
            variant="outlined" rounded="lg" rows="3" />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-btn variant="text" @click="checkDialog = false">Bekor</v-btn>
          <v-spacer />
          <v-btn :color="checkPassed ? 'success' : 'error'" variant="flat" rounded="lg" :loading="submitting" @click="submitCheck">
            Tasdiqlash
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
