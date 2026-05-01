<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { waybillApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import QRCode from 'qrcode'

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
const qrDataUrl = ref('')

const STATUS_STEPS = ['draft','mechanic_check','mechanic_ok','doctor_check','doctor_ok','hq_review','approved','in_progress','completed']

const STATUS_META = {
  draft:          { label: "Yaratilgan",           color: '#64748B', bg: '#F1F5F9', icon: 'mdi-file-document-outline' },
  mechanic_check: { label: "Mexanik tekshiruvi",   color: '#F59E0B', bg: '#FFFBEB', icon: 'mdi-wrench-clock' },
  mechanic_ok:    { label: "Mexanik OK",            color: '#3B82F6', bg: '#EFF6FF', icon: 'mdi-wrench-check' },
  doctor_check:   { label: "Shifokor tekshiruvi",  color: '#F59E0B', bg: '#FFFBEB', icon: 'mdi-stethoscope' },
  doctor_ok:      { label: "Shifokor OK",           color: '#3B82F6', bg: '#EFF6FF', icon: 'mdi-medical-bag' },
  hq_review:      { label: "Markaziy apparat",     color: '#8B5CF6', bg: '#F5F3FF', icon: 'mdi-office-building-check' },
  approved:       { label: "Tasdiqlandi",           color: '#10B981', bg: '#F0FDF4', icon: 'mdi-check-circle' },
  in_progress:    { label: "Yo'lda",               color: '#2563EB', bg: '#EFF6FF', icon: 'mdi-car-arrow-right' },
  completed:      { label: "Bajarildi",             color: '#059669', bg: '#ECFDF5', icon: 'mdi-flag-checkered' },
  cancelled:      { label: "Bekor qilindi",         color: '#EF4444', bg: '#FEF2F2', icon: 'mdi-cancel' },
}

const statusMeta = computed(() => STATUS_META[waybill.value?.status] || STATUS_META.draft)

const currentStepIndex = computed(() => {
  const idx = STATUS_STEPS.indexOf(waybill.value?.status)
  return idx === -1 ? 0 : idx
})

const fuelPct = computed(() => {
  if (!waybill.value?.fuel_issued || !waybill.value?.fuel_consumed) return null
  return Math.round((waybill.value.fuel_consumed / waybill.value.fuel_issued) * 100)
})

async function load() {
  try {
    const res = await waybillApi.show(route.params.id)
    waybill.value = res.data
    // Generate QR code
    const qrText = `${window.location.origin}/waybills/${res.data.id}\nRaqam: ${res.data.waybill_number}\nHaydovchi: ${res.data.driver?.full_name || ''}\nAvtomobil: ${res.data.vehicle?.state_number || ''}`
    qrDataUrl.value = await QRCode.toDataURL(qrText, { width: 180, margin: 1, color: { dark: '#0F172A', light: '#FFFFFF' } })
  } finally {
    loading.value = false
  }
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

async function approve() { await waybillApi.approve(waybill.value.id); load() }
async function depart()  { await waybillApi.depart(waybill.value.id);  load() }
async function complete() {
  await waybillApi.complete(waybill.value.id, { actual_km: waybill.value.actual_km, fuel_consumed: waybill.value.fuel_consumed })
  load()
}

function openCheck(type, passed) {
  checkType.value = type; checkPassed.value = passed; checkNotes.value = ''; checkDialog.value = true
}

function printPdf() { window.print() }

const fuelTypeLabel = (t) => ({ benzin: 'Benzin', dizel: 'Dizel', gaz: 'Gaz' }[t] || t || '—')

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

onMounted(load)
</script>

<template>
  <div class="page no-print-bg">
    <!-- Back + Print -->
    <div class="top-bar no-print">
      <button class="back-btn" @click="router.back()">
        <v-icon size="18">mdi-arrow-left</v-icon>
        Orqaga
      </button>
      <button class="print-btn" @click="printPdf">
        <v-icon size="16" color="white">mdi-printer</v-icon>
        PDF / Chop etish
      </button>
    </div>

    <v-skeleton-loader v-if="loading" type="article" rounded="xl" class="mt-4" />

    <template v-else-if="waybill">

      <!-- ============ PRINTABLE DOCUMENT ============ -->
      <div class="waybill-doc" id="print-area">

        <!-- Doc Header -->
        <div class="doc-header">
          <div class="doc-header-left">
            <img src="/logo.svg" alt="UTG" class="doc-logo" />
            <div>
              <div class="doc-company">Urganchtransgaz</div>
              <div class="doc-sub">{{ waybill.organization?.name }}</div>
            </div>
          </div>
          <div class="doc-header-right">
            <div class="doc-number">{{ waybill.waybill_number }}</div>
            <div
              class="doc-status-badge"
              :style="`background:${statusMeta.bg}; color:${statusMeta.color}; border-color:${statusMeta.color}30;`"
            >
              <v-icon :color="statusMeta.color" size="14">{{ statusMeta.icon }}</v-icon>
              {{ statusMeta.label }}
            </div>
          </div>
        </div>

        <!-- Action buttons (no print) -->
        <div class="action-bar no-print">
          <button v-if="waybill.status === 'draft'"
            class="action-btn btn-primary" @click="submitToMechanic">
            <v-icon size="15">mdi-send</v-icon> Mexanikga yuborish
          </button>
          <template v-if="waybill.status === 'mechanic_check' && auth.isMechanic">
            <button class="action-btn btn-success" @click="openCheck('mechanic', true)">
              <v-icon size="15">mdi-check</v-icon> Tasdiqlash
            </button>
            <button class="action-btn btn-danger" @click="openCheck('mechanic', false)">
              <v-icon size="15">mdi-close</v-icon> Rad etish
            </button>
          </template>
          <button v-if="(waybill.status === 'mechanic_ok' || waybill.status === 'doctor_check') && auth.isDoctor"
            class="action-btn btn-info" @click="openCheck('doctor', true)">
            <v-icon size="15">mdi-stethoscope</v-icon> Shifokor: Tasdiqlash
          </button>
          <button v-if="waybill.status === 'doctor_ok' && auth.isHqDispatcher"
            class="action-btn btn-success" @click="approve">
            <v-icon size="15">mdi-check-all</v-icon> Tasdiqlash
          </button>
          <button v-if="waybill.status === 'approved'"
            class="action-btn btn-primary" @click="depart">
            <v-icon size="15">mdi-car-arrow-right</v-icon> Yo'lga chiqdi
          </button>
          <button v-if="waybill.status === 'in_progress'"
            class="action-btn btn-warning" @click="complete">
            <v-icon size="15">mdi-flag-checkered</v-icon> Yakunlash
          </button>
        </div>

        <!-- Status Timeline -->
        <div class="timeline-wrap no-print">
          <div
            v-for="(step, i) in STATUS_STEPS"
            :key="step"
            class="timeline-step"
            :class="{
              'step-done':    i < currentStepIndex,
              'step-current': i === currentStepIndex,
              'step-future':  i > currentStepIndex
            }"
          >
            <div class="step-dot">
              <v-icon v-if="i < currentStepIndex" size="12" color="white">mdi-check</v-icon>
              <div v-else-if="i === currentStepIndex" class="step-dot-inner" />
            </div>
            <div class="step-label">{{ STATUS_META[step]?.label }}</div>
            <div v-if="i < STATUS_STEPS.length - 1" class="step-line" :class="{ 'line-done': i < currentStepIndex }" />
          </div>
        </div>

        <!-- Main info + QR -->
        <div class="doc-body">
          <div class="doc-info">

            <!-- Section: Marshrut -->
            <div class="info-section">
              <div class="section-label">
                <v-icon size="14" color="#2563EB">mdi-road-variant</v-icon>
                Marshrut ma'lumotlari
              </div>
              <div class="info-grid">
                <div class="info-item">
                  <div class="info-key">Yo'nalish</div>
                  <div class="info-val">{{ waybill.destination || '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Sana</div>
                  <div class="info-val">{{ formatDate(waybill.created_date || waybill.created_at) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Rejalashtirilgan km</div>
                  <div class="info-val">{{ waybill.planned_km ? waybill.planned_km + ' km' : '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Haqiqiy km</div>
                  <div class="info-val">{{ waybill.actual_km ? waybill.actual_km + ' km' : '—' }}</div>
                </div>
              </div>
            </div>

            <!-- Section: Transport -->
            <div class="info-section">
              <div class="section-label">
                <v-icon size="14" color="#7C3AED">mdi-car</v-icon>
                Transport
              </div>
              <div class="info-grid">
                <div class="info-item">
                  <div class="info-key">Davlat raqami</div>
                  <div class="info-val plate">{{ waybill.vehicle?.state_number || '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Model</div>
                  <div class="info-val">{{ waybill.vehicle?.model || '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Haydovchi</div>
                  <div class="info-val">{{ waybill.driver?.full_name || '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Kategoriya</div>
                  <div class="info-val">{{ waybill.driver?.license_category || '—' }}</div>
                </div>
              </div>
            </div>

            <!-- Section: Yoqilg'i -->
            <div class="info-section">
              <div class="section-label">
                <v-icon size="14" color="#059669">mdi-gas-station</v-icon>
                Yoqilg'i
              </div>
              <div class="info-grid">
                <div class="info-item">
                  <div class="info-key">Turi</div>
                  <div class="info-val">{{ fuelTypeLabel(waybill.vehicle?.fuel_type) }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Berilgan</div>
                  <div class="info-val">{{ waybill.fuel_issued ? waybill.fuel_issued + ' L' : '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Sarflangan</div>
                  <div class="info-val">{{ waybill.fuel_consumed ? waybill.fuel_consumed + ' L' : '—' }}</div>
                </div>
                <div class="info-item">
                  <div class="info-key">Sarflash ulushi</div>
                  <div class="info-val">
                    <span v-if="fuelPct !== null">
                      <span :style="`color: ${fuelPct > 100 ? '#EF4444' : '#10B981'}`">{{ fuelPct }}%</span>
                    </span>
                    <span v-else>—</span>
                  </div>
                </div>
              </div>
              <!-- Fuel bar -->
              <div v-if="fuelPct !== null" class="fuel-bar-wrap">
                <div class="fuel-bar-track">
                  <div class="fuel-bar-fill"
                    :style="`width:${Math.min(fuelPct,100)}%; background:${fuelPct > 100 ? '#EF4444' : '#10B981'}`" />
                </div>
                <span class="fuel-bar-label">{{ fuelPct }}%</span>
              </div>
            </div>

            <!-- Notes: Mexanik / Shifokor -->
            <div v-if="waybill.mechanic_notes || waybill.doctor_notes" class="checks-row">
              <div v-if="waybill.mechanic_notes" class="check-card" :class="waybill.mechanic_passed ? 'check-ok' : 'check-fail'">
                <div class="check-title">
                  <v-icon size="14" :color="waybill.mechanic_passed ? '#10B981' : '#EF4444'">
                    {{ waybill.mechanic_passed ? 'mdi-wrench-check' : 'mdi-wrench-alert' }}
                  </v-icon>
                  Mexanik xulosasi
                </div>
                <div class="check-note">{{ waybill.mechanic_notes }}</div>
              </div>
              <div v-if="waybill.doctor_notes" class="check-card" :class="waybill.doctor_passed ? 'check-ok' : 'check-fail'">
                <div class="check-title">
                  <v-icon size="14" :color="waybill.doctor_passed ? '#10B981' : '#EF4444'">
                    {{ waybill.doctor_passed ? 'mdi-stethoscope' : 'mdi-stethoscope' }}
                  </v-icon>
                  Shifokor xulosasi
                </div>
                <div class="check-note">{{ waybill.doctor_notes }}</div>
              </div>
            </div>

          </div>

          <!-- QR Code -->
          <div class="doc-qr">
            <div class="qr-box">
              <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR" class="qr-img" />
              <div v-else class="qr-placeholder">
                <v-icon size="40" color="#CBD5E1">mdi-qrcode</v-icon>
              </div>
            </div>
            <div class="qr-label">{{ waybill.waybill_number }}</div>
            <div class="qr-hint">Skanerlash uchun</div>

            <!-- Signature boxes (print only) -->
            <div class="sign-boxes">
              <div class="sign-box">
                <div class="sign-label">Mexanik</div>
                <div class="sign-line"></div>
                <div class="sign-name">{{ waybill.mechanic_name || '_____________' }}</div>
              </div>
              <div class="sign-box">
                <div class="sign-label">Shifokor</div>
                <div class="sign-line"></div>
                <div class="sign-name">{{ waybill.doctor_name || '_____________' }}</div>
              </div>
              <div class="sign-box">
                <div class="sign-label">Dispatcher</div>
                <div class="sign-line"></div>
                <div class="sign-name">_____________</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Doc Footer -->
        <div class="doc-footer">
          <span>Urganchtransgaz Avtotransport Boshqaruv Tizimi</span>
          <span>{{ new Date().toLocaleDateString('uz-UZ') }}</span>
        </div>

      </div><!-- /waybill-doc -->
    </template>

    <!-- Check Dialog -->
    <div v-if="checkDialog" class="dialog-overlay" @click.self="checkDialog = false">
      <div class="dialog-box">
        <div class="dialog-header">
          <div class="dialog-icon" :style="`background: ${checkPassed ? '#F0FDF4' : '#FEF2F2'}`">
            <v-icon :color="checkPassed ? '#10B981' : '#EF4444'" size="22">
              {{ checkPassed ? 'mdi-check-circle' : 'mdi-close-circle' }}
            </v-icon>
          </div>
          <div class="dialog-title">{{ checkPassed ? 'Tasdiqlash' : 'Rad etish' }}</div>
        </div>
        <div class="dialog-body">
          <label class="dlg-label">{{ checkPassed ? 'Izoh (ixtiyoriy)' : 'Sabab (majburiy)' }}</label>
          <textarea v-model="checkNotes" class="dlg-textarea" rows="3" :placeholder="checkPassed ? 'Texnik holat yaxshi...' : 'Sababini kiriting...'" />
        </div>
        <div class="dialog-footer">
          <button class="dlg-btn dlg-cancel" @click="checkDialog = false">Bekor</button>
          <button
            class="dlg-btn"
            :class="checkPassed ? 'dlg-success' : 'dlg-danger'"
            :disabled="submitting"
            @click="submitCheck"
          >
            <v-progress-circular v-if="submitting" size="16" width="2" indeterminate color="white" />
            <span v-else>{{ checkPassed ? 'Tasdiqlash' : 'Rad etish' }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  font-family: 'Inter', system-ui, sans-serif;
  min-height: 100%;
}

/* Top bar */
.top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.back-btn {
  display: flex; align-items: center; gap: 6px;
  background: white; border: 1px solid rgba(0,0,0,0.1);
  border-radius: 10px; padding: 8px 14px;
  font-size: 13px; font-weight: 600; color: #374151;
  cursor: pointer; font-family: inherit;
}
.back-btn:hover { background: #F8FAFC; }
.print-btn {
  display: flex; align-items: center; gap: 6px;
  background: linear-gradient(135deg, #0F172A, #1E3A5F);
  border: none; border-radius: 10px; padding: 9px 18px;
  font-size: 13px; font-weight: 700; color: white;
  cursor: pointer; font-family: inherit;
}
.print-btn:hover { opacity: 0.9; }

/* Waybill document */
.waybill-doc {
  background: white;
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 20px;
  overflow: hidden;
  max-width: 960px;
  margin: 0 auto;
  box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}

/* Doc Header */
.doc-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 24px 28px;
  background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%);
  border-bottom: none;
}
.doc-header-left {
  display: flex; align-items: center; gap: 14px;
}
.doc-logo {
  height: 44px; width: auto;
  filter: brightness(0) invert(1); opacity: 0.95;
}
.doc-company { font-size: 16px; font-weight: 800; color: white; }
.doc-sub { font-size: 12px; color: rgba(255,255,255,0.55); margin-top: 2px; }

.doc-header-right { text-align: right; }
.doc-number {
  font-size: 22px; font-weight: 800; color: white; letter-spacing: -0.5px;
}
.doc-status-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700;
  padding: 4px 10px; border-radius: 99px;
  border: 1px solid;
  margin-top: 6px;
}

/* Action bar */
.action-bar {
  display: flex; flex-wrap: wrap; gap: 8px;
  padding: 16px 28px;
  background: #F8FAFC;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.action-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 10px; border: none;
  font-size: 13px; font-weight: 600; cursor: pointer;
  font-family: inherit; transition: opacity 0.15s;
}
.action-btn:hover { opacity: 0.85; }
.btn-primary { background: #2563EB; color: white; }
.btn-success { background: #10B981; color: white; }
.btn-danger  { background: #EF4444; color: white; }
.btn-warning { background: #F59E0B; color: white; }
.btn-info    { background: #3B82F6; color: white; }

/* Timeline */
.timeline-wrap {
  display: flex; align-items: flex-start;
  padding: 20px 28px;
  overflow-x: auto;
  gap: 0;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.timeline-step {
  display: flex; flex-direction: column; align-items: center;
  position: relative; flex: 1; min-width: 70px;
}
.step-dot {
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  z-index: 1; flex-shrink: 0;
}
.step-done .step-dot    { background: #10B981; }
.step-current .step-dot { background: #2563EB; box-shadow: 0 0 0 4px rgba(37,99,235,0.18); }
.step-future .step-dot  { background: #E2E8F0; }
.step-dot-inner { width: 8px; height: 8px; background: white; border-radius: 50%; }
.step-label {
  font-size: 9px; font-weight: 600; text-align: center;
  margin-top: 5px; line-height: 1.3; color: #64748B;
  max-width: 72px;
}
.step-done .step-label    { color: #10B981; }
.step-current .step-label { color: #2563EB; font-weight: 800; }
.step-line {
  position: absolute; top: 11px; left: 50%; width: 100%;
  height: 2px; background: #E2E8F0; z-index: 0;
}
.line-done { background: #10B981; }

/* Doc body */
.doc-body {
  display: flex; gap: 0;
  padding: 28px;
}
.doc-info { flex: 1; display: flex; flex-direction: column; gap: 20px; }
.doc-qr { flex-shrink: 0; width: 200px; display: flex; flex-direction: column; align-items: center; padding-left: 24px; border-left: 1px solid rgba(0,0,0,0.07); }

/* Info sections */
.info-section {
  background: #F8FAFC;
  border: 1px solid rgba(0,0,0,0.06);
  border-radius: 14px;
  padding: 16px;
}
.section-label {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 800; color: #64748B;
  text-transform: uppercase; letter-spacing: 0.5px;
  margin-bottom: 12px;
}
.info-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
}
.info-item {}
.info-key { font-size: 11px; color: #94A3B8; font-weight: 600; margin-bottom: 2px; }
.info-val { font-size: 14px; font-weight: 700; color: #0F172A; }
.info-val.plate {
  font-family: monospace; background: #0F172A; color: white;
  display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 13px;
}

/* Fuel bar */
.fuel-bar-wrap { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
.fuel-bar-track { flex: 1; height: 8px; background: #E2E8F0; border-radius: 99px; overflow: hidden; }
.fuel-bar-fill  { height: 100%; border-radius: 99px; transition: width 0.4s; }
.fuel-bar-label { font-size: 12px; font-weight: 700; color: #374151; }

/* Check cards */
.checks-row { display: flex; gap: 12px; flex-wrap: wrap; }
.check-card {
  flex: 1; min-width: 200px;
  border-radius: 12px; padding: 12px 14px;
  border: 1px solid;
}
.check-ok   { background: #F0FDF4; border-color: #BBF7D0; }
.check-fail { background: #FEF2F2; border-color: #FECACA; }
.check-title {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 800; margin-bottom: 6px;
  color: #374151;
}
.check-note { font-size: 12px; color: #64748B; line-height: 1.5; }

/* QR */
.qr-box {
  background: white; border: 1.5px solid rgba(0,0,0,0.08);
  border-radius: 14px; padding: 10px;
  margin-bottom: 8px;
}
.qr-img { width: 160px; height: 160px; display: block; }
.qr-placeholder {
  width: 160px; height: 160px;
  display: flex; align-items: center; justify-content: center;
  background: #F8FAFC; border-radius: 8px;
}
.qr-label { font-size: 12px; font-weight: 800; color: #0F172A; text-align: center; letter-spacing: 0.5px; }
.qr-hint  { font-size: 10px; color: #94A3B8; text-align: center; margin-top: 2px; }

/* Signature boxes */
.sign-boxes { margin-top: 20px; display: flex; flex-direction: column; gap: 14px; width: 100%; }
.sign-box {}
.sign-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #94A3B8; letter-spacing: 0.4px; margin-bottom: 18px; }
.sign-line  { height: 1px; background: #CBD5E1; margin-bottom: 4px; }
.sign-name  { font-size: 10px; color: #94A3B8; text-align: center; }

/* Doc footer */
.doc-footer {
  display: flex; justify-content: space-between;
  padding: 12px 28px;
  background: #F8FAFC;
  border-top: 1px solid rgba(0,0,0,0.06);
  font-size: 10px; color: #94A3B8;
}

/* Dialog */
.dialog-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.4); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999;
}
.dialog-box {
  background: white; border-radius: 20px; width: 440px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  overflow: hidden;
}
.dialog-header {
  display: flex; align-items: center; gap: 12px;
  padding: 20px 24px 16px;
}
.dialog-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.dialog-title { font-size: 17px; font-weight: 800; color: #0F172A; }
.dialog-body { padding: 0 24px 20px; }
.dlg-label { font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 6px; }
.dlg-textarea {
  width: 100%; padding: 12px 14px;
  border: 1.5px solid #E2E8F0; border-radius: 12px;
  font-size: 13px; font-family: inherit; color: #0F172A;
  resize: none; outline: none; background: #FAFAFA;
  box-sizing: border-box;
}
.dlg-textarea:focus { border-color: #2563EB; background: white; }
.dialog-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 16px 24px; background: #F8FAFC;
  border-top: 1px solid rgba(0,0,0,0.06);
}
.dlg-btn {
  padding: 10px 20px; border-radius: 10px; border: none;
  font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;
}
.dlg-cancel  { background: white; color: #64748B; border: 1px solid #E2E8F0; }
.dlg-success { background: #10B981; color: white; }
.dlg-danger  { background: #EF4444; color: white; }
.dlg-btn:disabled { opacity: 0.7; cursor: not-allowed; }

/* ===================== PRINT STYLES ===================== */
@media print {
  .no-print { display: none !important; }
  .no-print-bg { padding: 0 !important; background: white !important; }
  .waybill-doc {
    border: none !important; border-radius: 0 !important;
    box-shadow: none !important; max-width: 100% !important;
    margin: 0 !important;
  }
  .doc-header {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .doc-body { padding: 20px !important; }
  .info-section {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    break-inside: avoid;
  }
  .qr-img { width: 140px !important; height: 140px !important; }
  .doc-footer { border-top: 1px solid #E2E8F0 !important; }
  .sign-boxes { display: flex !important; }
}
</style>
