<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const waybill = ref(null)
const loading = ref(true)
const notFound = ref(false)

const STATUS_META = {
  draft:          { label: 'Yaratilgan',          color: '#64748B', bg: '#F1F5F9', icon: '📄' },
  mechanic_check: { label: 'Mexanik tekshiruvi',  color: '#F59E0B', bg: '#FFFBEB', icon: '🔧' },
  mechanic_ok:    { label: 'Mexanik OK',           color: '#3B82F6', bg: '#EFF6FF', icon: '✅' },
  doctor_check:   { label: 'Shifokor tekshiruvi', color: '#F59E0B', bg: '#FFFBEB', icon: '🩺' },
  doctor_ok:      { label: 'Shifokor OK',          color: '#3B82F6', bg: '#EFF6FF', icon: '✅' },
  hq_review:      { label: 'Markaziy apparat',    color: '#8B5CF6', bg: '#F5F3FF', icon: '🏢' },
  approved:       { label: 'Tasdiqlandi',          color: '#10B981', bg: '#F0FDF4', icon: '✅' },
  in_progress:    { label: "Yo'lda",              color: '#2563EB', bg: '#EFF6FF', icon: '🚗' },
  completed:      { label: 'Bajarildi',            color: '#059669', bg: '#ECFDF5', icon: '🏁' },
  cancelled:      { label: 'Bekor qilindi',        color: '#EF4444', bg: '#FEF2F2', icon: '❌' },
}

const statusMeta = computed(() => STATUS_META[waybill.value?.status] || STATUS_META.draft)

const fuelTypeLabel = (t) => ({ benzin: 'Benzin', dizel: 'Dizel', gaz: 'Gaz' }[t] || t || '—')

function formatDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric' }) }
  catch { return d }
}

const fuelPct = computed(() => {
  if (!waybill.value?.fuel_issued || !waybill.value?.fuel_consumed) return null
  return Math.round((waybill.value.fuel_consumed / waybill.value.fuel_issued) * 100)
})

onMounted(async () => {
  try {
    const res = await axios.get(`/api/waybills/${route.params.id}/public`)
    waybill.value = res.data
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="pub-page">

    <!-- Loading -->
    <div v-if="loading" class="centered">
      <div class="spinner" />
      <p>Yuklanmoqda...</p>
    </div>

    <!-- Not found -->
    <div v-else-if="notFound" class="centered">
      <div class="nf-icon">🔍</div>
      <h2>Varaqa topilmadi</h2>
      <p>Bu QR kod eskirgan yoki yo'l varaqasi mavjud emas.</p>
    </div>

    <!-- Waybill card -->
    <div v-else-if="waybill" class="card">

      <!-- Header -->
      <div class="card-head">
        <div class="head-left">
          <img src="/logo.svg" alt="UTG" class="logo" />
          <div>
            <div class="company">Urganchtransgaz</div>
            <div class="org">{{ waybill.organization?.name }}</div>
          </div>
        </div>
        <div class="head-right">
          <div class="wnum">{{ waybill.waybill_number }}</div>
          <div
            class="status-badge"
            :style="`background:${statusMeta.bg}; color:${statusMeta.color};`"
          >
            {{ statusMeta.icon }} {{ statusMeta.label }}
          </div>
        </div>
      </div>

      <!-- Body -->
      <div class="card-body">

        <!-- Marshrut -->
        <div class="section">
          <div class="section-title">🗺 Marshrut</div>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-k">Yo'nalish</span>
              <span class="info-v">{{ waybill.destination || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Sana</span>
              <span class="info-v">{{ formatDate(waybill.created_date) }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Rej. km</span>
              <span class="info-v">{{ waybill.planned_km ? waybill.planned_km + ' km' : '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Haq. km</span>
              <span class="info-v">{{ waybill.actual_km ? waybill.actual_km + ' km' : '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Transport -->
        <div class="section">
          <div class="section-title">🚗 Transport</div>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-k">Davlat raqami</span>
              <span class="info-v plate">{{ waybill.vehicle?.state_number || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Model</span>
              <span class="info-v">{{ waybill.vehicle?.model || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Haydovchi</span>
              <span class="info-v bold">{{ waybill.driver?.full_name || '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Kategoriya</span>
              <span class="info-v">{{ waybill.driver?.license_category || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Yoqilg'i -->
        <div class="section">
          <div class="section-title">⛽ Yoqilg'i</div>
          <div class="info-grid">
            <div class="info-row">
              <span class="info-k">Turi</span>
              <span class="info-v">{{ fuelTypeLabel(waybill.vehicle?.fuel_type) }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Berilgan</span>
              <span class="info-v">{{ waybill.fuel_issued ? waybill.fuel_issued + ' L' : '—' }}</span>
            </div>
            <div class="info-row">
              <span class="info-k">Sarflangan</span>
              <span class="info-v">{{ waybill.fuel_consumed ? waybill.fuel_consumed + ' L' : '—' }}</span>
            </div>
          </div>
          <div v-if="fuelPct !== null" class="fuel-bar-wrap">
            <div class="fuel-bar-track">
              <div class="fuel-bar-fill"
                :style="`width:${Math.min(fuelPct,100)}%; background:${fuelPct > 100 ? '#EF4444' : '#10B981'}`" />
            </div>
            <span class="fuel-pct" :style="`color:${fuelPct > 100 ? '#EF4444' : '#10B981'}`">{{ fuelPct }}%</span>
          </div>
        </div>

        <!-- Tekshiruvlar -->
        <div v-if="waybill.mechanic_notes || waybill.doctor_notes" class="section">
          <div class="section-title">📋 Tekshiruvlar</div>
          <div v-if="waybill.mechanic_notes" class="check-row" :class="waybill.mechanic_passed ? 'check-ok' : 'check-fail'">
            <div class="check-head">
              {{ waybill.mechanic_passed ? '✅' : '❌' }} Mexanik
              <span v-if="waybill.mechanic">— {{ waybill.mechanic }}</span>
            </div>
            <div class="check-note">{{ waybill.mechanic_notes }}</div>
          </div>
          <div v-if="waybill.doctor_notes" class="check-row" :class="waybill.doctor_passed ? 'check-ok' : 'check-fail'">
            <div class="check-head">
              {{ waybill.doctor_passed ? '✅' : '❌' }} Shifokor
              <span v-if="waybill.doctor">— {{ waybill.doctor }}</span>
            </div>
            <div class="check-note">{{ waybill.doctor_notes }}</div>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="card-foot">
        <span>Urganchtransgaz ABS · vms.utg.uz</span>
        <span>{{ formatDate(new Date()) }}</span>
      </div>

    </div>

  </div>
</template>

<style scoped>
* { box-sizing: border-box; }

.pub-page {
  min-height: 100vh;
  background: #F1F5F9;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 24px 16px 40px;
  font-family: 'Inter', system-ui, sans-serif;
}

/* Loading / Not found */
.centered {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 12px; min-height: 60vh; text-align: center;
}
.spinner {
  width: 36px; height: 36px; border-radius: 50%;
  border: 3px solid #E2E8F0;
  border-top-color: #2563EB;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.centered p { font-size: 14px; color: #94A3B8; margin: 0; }
.nf-icon { font-size: 48px; }
.centered h2 { font-size: 20px; font-weight: 800; color: #0F172A; margin: 0; }

/* Card */
.card {
  background: white;
  border-radius: 20px;
  box-shadow: 0 4px 32px rgba(0,0,0,0.08);
  width: 100%; max-width: 520px;
  overflow: hidden;
}

/* Header */
.card-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 20px 22px;
  background: linear-gradient(135deg, #0F172A, #1E3A5F);
  gap: 12px;
}
.head-left { display: flex; align-items: center; gap: 12px; }
.logo { height: 38px; width: auto; filter: brightness(0) invert(1); opacity: 0.92; }
.company { font-size: 15px; font-weight: 800; color: white; }
.org { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 2px; }
.head-right { text-align: right; }
.wnum { font-size: 18px; font-weight: 800; color: white; letter-spacing: -0.3px; }
.status-badge {
  display: inline-block; margin-top: 5px;
  font-size: 11px; font-weight: 700;
  padding: 3px 10px; border-radius: 99px;
}

/* Body */
.card-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 18px; }

.section {}
.section-title {
  font-size: 11px; font-weight: 800; text-transform: uppercase;
  letter-spacing: 0.6px; color: #94A3B8; margin-bottom: 10px;
}

.info-grid { display: flex; flex-direction: column; gap: 0; }
.info-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 9px 0; border-bottom: 1px solid rgba(0,0,0,0.04);
}
.info-row:last-child { border-bottom: none; }
.info-k { font-size: 13px; color: #94A3B8; }
.info-v { font-size: 13px; font-weight: 600; color: #0F172A; text-align: right; }
.info-v.plate {
  font-family: monospace; background: #0F172A; color: white;
  padding: 2px 8px; border-radius: 6px; font-size: 12px;
}
.info-v.bold { font-weight: 700; }

/* Fuel bar */
.fuel-bar-wrap { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
.fuel-bar-track { flex: 1; height: 8px; background: #F1F5F9; border-radius: 99px; overflow: hidden; }
.fuel-bar-fill  { height: 100%; border-radius: 99px; transition: width 0.4s; }
.fuel-pct { font-size: 12px; font-weight: 800; min-width: 36px; text-align: right; }

/* Check rows */
.check-row {
  border-radius: 10px; padding: 10px 12px; margin-bottom: 8px;
  border: 1px solid;
}
.check-ok   { background: #F0FDF4; border-color: #BBF7D0; }
.check-fail { background: #FEF2F2; border-color: #FECACA; }
.check-head { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px; }
.check-note { font-size: 12px; color: #64748B; line-height: 1.5; }

/* Footer */
.card-foot {
  display: flex; justify-content: space-between;
  padding: 12px 22px; background: #F8FAFC;
  border-top: 1px solid rgba(0,0,0,0.05);
  font-size: 10px; color: #CBD5E1;
}
</style>
