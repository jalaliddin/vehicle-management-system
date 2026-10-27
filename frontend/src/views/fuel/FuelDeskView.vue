<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { fuelApi } from '@/services/api'
import { errorMessage, label } from '@/utils/hr'
import { quantity, transactionKinds, unitOf } from '@/utils/fuel'
import RefuelSheet from '@/components/fuel/RefuelSheet.vue'
import ReceiptSheet from '@/components/fuel/ReceiptSheet.vue'
import ShiftReport from '@/components/fuel/ShiftReport.vue'

const stations = ref([])
const stationId = ref(null)
const report = ref(null)
const loading = ref(false)
const failure = ref('')
const measured = ref(null)
const note = ref('')
const closing = ref(false)
const refuelOpen = ref(false)
const receiptOpen = ref(false)
const reportOpen = ref(false)
const busy = ref(false)

const station = computed(() => stations.value.find(s => s.id === stationId.value))
const shift = computed(() => report.value?.shift)
const unit = computed(() => unitOf(station.value?.fuel_type))
const discrepancy = computed(() => (measured.value === null || measured.value === '' || !station.value)
  ? 0 : Number(measured.value) - Number(station.value.current_balance))

async function loadStations() {
  stations.value = (await fuelApi.stations()).data
  if (!stations.value.some(s => s.id === stationId.value)) stationId.value = stations.value[0]?.id ?? null
}

async function loadShift() {
  if (!stationId.value) return
  loading.value = true
  try {
    report.value = (await fuelApi.currentShift(stationId.value)).data
  } finally {
    loading.value = false
  }
}

async function refresh() {
  await loadStations()
  await loadShift()
}

async function openShift() {
  busy.value = true
  failure.value = ''
  try {
    await fuelApi.openShift(stationId.value, measured.value)
    measured.value = null
    await refresh()
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

async function closeShift() {
  busy.value = true
  failure.value = ''
  try {
    await fuelApi.closeShift(shift.value.id, { closing_measured: measured.value, discrepancy_note: note.value || null })
    report.value = (await fuelApi.shift(shift.value.id)).data
    reportOpen.value = true
    closing.value = false
    measured.value = null
    note.value = ''
    await loadStations()
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

async function cancel(operation) {
  const reason = prompt('Bekor qilish sababi:')
  if (!reason) return
  try {
    await fuelApi.cancel(operation.id, reason)
    await refresh()
  } catch (e) {
    failure.value = errorMessage(e)
  }
}

function printReport() {
  window.print()
}

function afterReportClosed(open) {
  if (!open) loadShift()
}

watch(stationId, () => {
  failure.value = ''
  measured.value = null
  closing.value = false
  loadShift()
})

onMounted(refresh)
</script>

<template>
  <v-container class="pa-4 desk" style="max-width: 640px;">
    <h2 class="text-h6 font-weight-bold mb-3">Yoqilg'i quyish</h2>

    <v-alert v-if="!stations.length && !loading" type="info" variant="tonal" rounded="lg">Sizga shahobcha biriktirilmagan.</v-alert>

    <template v-else>
      <v-select v-if="stations.length > 1" v-model="stationId" :items="stations" item-title="name" item-value="id"
        label="Shahobcha" variant="outlined" rounded="lg" density="comfortable" hide-details class="mb-3" />

      <v-card v-if="station" rounded="xl" class="border mb-3">
        <v-card-text class="pa-4">
          <div class="text-caption text-medium-emphasis">{{ station.name }}</div>
          <div class="balance">{{ quantity(station.current_balance) }} <span>{{ unit }}</span></div>
          <v-progress-linear :model-value="station.percentage" height="8" rounded color="primary" class="mt-1" />
        </v-card-text>
      </v-card>

      <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>

      <v-card v-if="station && !loading" rounded="xl" class="border mb-3">
        <v-card-text class="pa-4">
          <template v-if="!shift">
            <div class="font-weight-bold mb-2">Smena yopiq</div>
            <v-text-field v-model="measured" type="number" inputmode="decimal" :label="`O'lchov bo'yicha qoldiq (${unit})`"
              variant="outlined" rounded="lg" :hint="`Hisob bo'yicha: ${quantity(station.current_balance)} ${unit}`" persistent-hint />
            <v-btn block size="x-large" color="success" variant="flat" rounded="lg" class="mt-3" :loading="busy"
              :disabled="measured === null || measured === ''" @click="openShift">Smenani ochish</v-btn>
          </template>
          <template v-else>
            <div class="d-flex align-center">
              <div>
                <div class="font-weight-bold">Smena ochiq</div>
                <div class="text-caption">{{ new Date(shift.opened_at).toLocaleString('uz-UZ') }} · {{ shift.opened_by?.name }}</div>
              </div>
              <v-spacer />
              <v-btn variant="text" size="small" prepend-icon="mdi-printer" @click="reportOpen = true">Hisobot</v-btn>
            </div>
            <div class="text-body-2 mt-2">Qabul: {{ quantity(report.totals.receipts) }} · Quyildi: {{ quantity(report.totals.refuels) }} {{ unit }}</div>

            <template v-if="closing">
              <v-text-field v-model="measured" type="number" inputmode="decimal" :label="`O'lchov bo'yicha qoldiq (${unit})`"
                variant="outlined" rounded="lg" class="mt-3" :hint="`Hisob bo'yicha: ${quantity(station.current_balance)} ${unit}`" persistent-hint />
              <v-textarea v-if="discrepancy !== 0" v-model="note" :label="`Farq ${discrepancy.toFixed(2)} ${unit} — izoh`" rows="2" variant="outlined" rounded="lg" class="mt-2" />
              <div class="d-flex ga-2 mt-2">
                <v-btn variant="text" @click="closing = false">Bekor</v-btn>
                <v-btn class="flex-grow-1" size="large" color="error" variant="flat" rounded="lg" :loading="busy"
                  :disabled="measured === null || measured === '' || (discrepancy !== 0 && !note)" @click="closeShift">Smenani yopish</v-btn>
              </div>
            </template>
            <v-btn v-else block variant="outlined" color="error" rounded="lg" class="mt-3" @click="closing = true">Smenani yopish</v-btn>
          </template>
        </v-card-text>
      </v-card>

      <div v-if="shift" class="actions mb-4">
        <v-btn size="x-large" color="primary" variant="flat" rounded="lg" prepend-icon="mdi-fuel" @click="refuelOpen = true">Yoqilg'i quyish</v-btn>
        <v-btn size="x-large" color="secondary" variant="flat" rounded="lg" prepend-icon="mdi-truck-delivery" @click="receiptOpen = true">Qabul qilish</v-btn>
      </div>

      <v-card v-if="shift" rounded="xl" class="border">
        <v-card-title class="text-subtitle-1 font-weight-bold">Smena amallari</v-card-title>
        <v-list density="compact">
          <v-list-item v-for="op in report.operations" :key="op.id" :class="{ 'text-disabled': op.cancelled_at }">
            <v-list-item-title>
              {{ label(transactionKinds, op.kind) }} · {{ op.vehicle?.state_number || op.supplier?.name || '—' }}
              <span class="float-right font-weight-bold">{{ op.type === 'out' ? '−' : '+' }}{{ quantity(op.amount) }}</span>
            </v-list-item-title>
            <v-list-item-subtitle>
              {{ new Date(op.created_at).toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit' }) }}
              <span v-if="op.odometer"> · {{ op.odometer }} km</span>
              <span v-if="op.kind === 'refuel' && !op.waybill_id"> · yo'l varaqasisiz</span>
              <span v-if="op.cancelled_at"> · bekor qilingan</span>
            </v-list-item-subtitle>
            <template #append>
              <v-btn v-if="!op.cancelled_at" icon="mdi-undo" size="small" variant="text" title="Bekor qilish" @click="cancel(op)" />
            </template>
          </v-list-item>
          <v-list-item v-if="!report.operations.length"><v-list-item-title class="text-medium-emphasis">Hali amal yo'q</v-list-item-title></v-list-item>
        </v-list>
      </v-card>
    </template>

    <RefuelSheet v-if="station" v-model="refuelOpen" :station="station" @saved="refresh" />
    <ReceiptSheet v-if="station" v-model="receiptOpen" :station="station" @saved="refresh" />

    <v-dialog v-model="reportOpen" max-width="760" @update:model-value="afterReportClosed">
      <v-card v-if="report?.shift" rounded="xl">
        <v-card-text class="pa-5"><ShiftReport :report="report" :unit="unit" /></v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="reportOpen = false">Yopish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-printer" @click="printReport">Chop etish</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<style scoped>
.balance { font-size: 32px; font-weight: 800; color: #0F172A; line-height: 1.2; }
.balance span { font-size: 16px; font-weight: 600; color: #64748B; }
.actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 420px) { .actions { grid-template-columns: 1fr; } }
</style>
