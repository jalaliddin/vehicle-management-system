<script setup>
import { ref, onMounted, computed } from 'vue'
import { fuelApi } from '@/services/api'

const stations = ref([])
const loading = ref(true)
const search = ref('')

onMounted(async () => {
  try {
    const res = await fuelApi.monitoring()
    stations.value = res.data
  } finally {
    loading.value = false
  }
})

const totalFuel     = computed(() => stations.value.reduce((s, st) => s + Number(st.current_balance), 0))
const totalCapacity = computed(() => stations.value.reduce((s, st) => s + Number(st.capacity), 0))
const overallPct    = computed(() => totalCapacity.value ? Math.round((totalFuel.value / totalCapacity.value) * 100) : 0)
const normalCount   = computed(() => stations.value.filter(s => s.percentage >= 50).length)
const warningCount  = computed(() => stations.value.filter(s => s.percentage >= 20 && s.percentage < 50).length)
const criticalCount = computed(() => stations.value.filter(s => s.percentage < 20).length)

const filtered = computed(() => {
  if (!search.value) return stations.value
  const q = search.value.toLowerCase()
  return stations.value.filter(s =>
    s.organization?.short_name?.toLowerCase().includes(q) ||
    s.name?.toLowerCase().includes(q) ||
    s.fuel_type?.toLowerCase().includes(q)
  )
})

const fuelColor = (pct) => pct < 20 ? '#EF4444' : pct < 50 ? '#F59E0B' : '#10B981'
const fuelBg    = (pct) => pct < 20 ? '#FEF2F2' : pct < 50 ? '#FFFBEB' : '#F0FDF4'
const fuelBorderColor = (pct) => pct < 20 ? '#FECACA' : pct < 50 ? '#FDE68A' : '#BBF7D0'
const fuelLabel = (pct) => pct < 20 ? 'Kritik' : pct < 50 ? 'Ogohlantirish' : 'Normal'

const fuelTypeLabel = (t) => ({ benzin: 'Benzin', dizel: 'Dizel', gaz: 'Gaz' }[t] || t)
const fuelTypeColor = (t) => ({ benzin: '#2563EB', dizel: '#7C3AED', gaz: '#059669' }[t] || '#64748B')
const fuelTypeBg    = (t) => ({ benzin: '#DBEAFE', dizel: '#EDE9FE', gaz: '#D1FAE5' }[t] || '#F1F5F9')
</script>

<template>
  <div class="page">

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Yoqilg'i Monitoring</h1>
        <p class="page-sub">Barcha tashkilotlardagi yoqilg'i zaxirasi holati</p>
      </div>
      <div class="search-wrap">
        <v-icon size="16" color="#94A3B8" class="search-icon">mdi-magnify</v-icon>
        <input v-model="search" placeholder="Tashkilot yoki nom bo'yicha..." class="search-input" />
      </div>
    </div>

    <!-- KPI row -->
    <div class="kpi-row">
      <div class="kpi-card kpi-total">
        <div class="kpi-left">
          <div class="kpi-value">{{ totalFuel.toLocaleString() }}</div>
          <div class="kpi-label">Jami yoqilg'i (L)</div>
          <div class="kpi-sub">{{ totalCapacity.toLocaleString() }} L sig'imdan</div>
        </div>
        <div class="kpi-gauge">
          <svg viewBox="0 0 80 80" class="gauge-svg">
            <circle cx="40" cy="40" r="32" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="8"/>
            <circle cx="40" cy="40" r="32" fill="none" stroke="white" stroke-width="8"
              stroke-dasharray="201"
              :stroke-dashoffset="201 - (201 * overallPct / 100)"
              stroke-linecap="round"
              transform="rotate(-90 40 40)"
            />
          </svg>
          <div class="gauge-pct">{{ overallPct }}%</div>
        </div>
      </div>

      <div class="kpi-card kpi-normal">
        <div class="kpi-icon-wrap" style="background:rgba(16,185,129,0.15);">
          <v-icon color="#10B981" size="22">mdi-check-circle</v-icon>
        </div>
        <div class="kpi-value">{{ normalCount }}</div>
        <div class="kpi-label">Normal holat</div>
        <div class="kpi-badge" style="background:rgba(16,185,129,0.15); color:#10B981;">≥ 50%</div>
      </div>

      <div class="kpi-card kpi-warning">
        <div class="kpi-icon-wrap" style="background:rgba(245,158,11,0.15);">
          <v-icon color="#F59E0B" size="22">mdi-alert</v-icon>
        </div>
        <div class="kpi-value">{{ warningCount }}</div>
        <div class="kpi-label">Ogohlantirish</div>
        <div class="kpi-badge" style="background:rgba(245,158,11,0.15); color:#F59E0B;">20–50%</div>
      </div>

      <div class="kpi-card kpi-critical">
        <div class="kpi-icon-wrap" style="background:rgba(239,68,68,0.15);">
          <v-icon color="#EF4444" size="22">mdi-alert-circle</v-icon>
        </div>
        <div class="kpi-value">{{ criticalCount }}</div>
        <div class="kpi-label">Kritik holat</div>
        <div class="kpi-badge" style="background:rgba(239,68,68,0.15); color:#EF4444;">< 20%</div>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="stations-grid">
      <div v-for="i in 8" :key="i" class="skeleton-card">
        <v-skeleton-loader type="card" rounded="xl" />
      </div>
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <v-icon size="48" color="#CBD5E1">mdi-gas-station-off</v-icon>
      <p>Shahobcha topilmadi</p>
    </div>

    <!-- Station cards -->
    <div v-else class="stations-grid">
      <div
        v-for="station in filtered"
        :key="station.id"
        class="station-card"
        :style="`border-color: ${fuelBorderColor(station.percentage)};`"
      >
        <!-- Card header -->
        <div class="station-header" :style="`background: ${fuelBg(station.percentage)};`">
          <div class="station-org">{{ station.organization?.short_name }}</div>
          <div class="station-status-pill" :style="`background: ${fuelColor(station.percentage)}22; color: ${fuelColor(station.percentage)};`">
            <span class="status-dot" :style="`background: ${fuelColor(station.percentage)};`" />
            {{ fuelLabel(station.percentage) }}
          </div>
        </div>

        <!-- Card body -->
        <div class="station-body">
          <div class="station-name">{{ station.name }}</div>

          <div class="fuel-type-tag" :style="`background: ${fuelTypeBg(station.fuel_type)}; color: ${fuelTypeColor(station.fuel_type)};`">
            <v-icon size="12" :color="fuelTypeColor(station.fuel_type)">mdi-water</v-icon>
            {{ fuelTypeLabel(station.fuel_type) }}
          </div>

          <!-- Progress -->
          <div class="progress-section">
            <div class="progress-header">
              <span class="progress-balance">{{ Number(station.current_balance).toLocaleString() }} L</span>
              <span class="progress-pct" :style="`color: ${fuelColor(station.percentage)};`">{{ station.percentage }}%</span>
            </div>
            <div class="progress-track">
              <div
                class="progress-fill"
                :style="`width: ${station.percentage}%; background: ${fuelColor(station.percentage)};`"
              />
            </div>
            <div class="progress-footer">
              <span>0 L</span>
              <span>{{ Number(station.capacity).toLocaleString() }} L</span>
            </div>
          </div>

          <!-- Critical alert -->
          <div v-if="station.is_critical" class="critical-alert">
            <v-icon size="14" color="#EF4444">mdi-alert-circle</v-icon>
            <span>Min chegara: {{ Number(station.min_threshold).toLocaleString() }} L</span>
          </div>
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

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.page-title { font-size: 22px; font-weight: 800; color: #0F172A; margin: 0; }
.page-sub   { font-size: 13px; color: #94A3B8; margin: 4px 0 0; }

.search-wrap {
  display: flex; align-items: center; gap: 8px;
  background: white; border: 1px solid rgba(0,0,0,0.09);
  border-radius: 12px; padding: 10px 14px;
  min-width: 260px;
}
.search-icon { flex-shrink: 0; }
.search-input {
  border: none; outline: none; background: none;
  font-size: 13px; color: #0F172A; width: 100%;
  font-family: inherit;
}
.search-input::placeholder { color: #CBD5E1; }

/* KPI row */
.kpi-row {
  display: grid;
  grid-template-columns: 1fr repeat(3, 160px);
  gap: 16px;
  margin-bottom: 24px;
}
@media (max-width: 900px) { .kpi-row { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .kpi-row { grid-template-columns: 1fr; } }

.kpi-card {
  background: white;
  border: 1px solid rgba(0,0,0,0.07);
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.kpi-total {
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%);
  border-color: transparent;
}
.kpi-left { display: flex; flex-direction: column; gap: 4px; }
.kpi-total .kpi-value { font-size: 28px; font-weight: 800; color: white; }
.kpi-total .kpi-label { font-size: 12px; color: rgba(255,255,255,0.6); }
.kpi-total .kpi-sub   { font-size: 11px; color: rgba(255,255,255,0.4); }

.gauge-svg { width: 72px; height: 72px; }
.kpi-gauge { position: relative; display: flex; align-items: center; justify-content: center; }
.gauge-pct {
  position: absolute; font-size: 13px; font-weight: 800;
  color: white; text-align: center;
}

.kpi-normal, .kpi-warning, .kpi-critical {
  align-items: flex-start;
}
.kpi-icon-wrap {
  width: 40px; height: 40px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 4px;
}
.kpi-value { font-size: 28px; font-weight: 800; color: #0F172A; line-height: 1; }
.kpi-label { font-size: 12px; color: #64748B; }
.kpi-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; width: fit-content; }

/* Stations grid */
.stations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}

.station-card {
  background: white;
  border: 1.5px solid rgba(0,0,0,0.08);
  border-radius: 16px;
  overflow: hidden;
  transition: transform 0.15s, box-shadow 0.15s;
}
.station-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.station-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.station-org {
  font-size: 12px; font-weight: 700; color: #374151;
  letter-spacing: 0.2px;
}
.station-status-pill {
  display: flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 600;
  padding: 3px 9px; border-radius: 99px;
}
.status-dot {
  width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
}

.station-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }

.station-name {
  font-size: 14px; font-weight: 700; color: #0F172A; line-height: 1.3;
}

.fuel-type-tag {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700;
  padding: 4px 10px; border-radius: 8px;
  width: fit-content;
  text-transform: uppercase; letter-spacing: 0.3px;
}

/* Progress */
.progress-section { display: flex; flex-direction: column; gap: 6px; }
.progress-header { display: flex; justify-content: space-between; align-items: center; }
.progress-balance { font-size: 18px; font-weight: 800; color: #0F172A; }
.progress-pct { font-size: 14px; font-weight: 800; }

.progress-track {
  height: 10px; background: #F1F5F9;
  border-radius: 99px; overflow: hidden;
}
.progress-fill {
  height: 100%; border-radius: 99px;
  transition: width 0.5s ease;
}

.progress-footer {
  display: flex; justify-content: space-between;
  font-size: 10px; color: #94A3B8;
}

/* Critical alert */
.critical-alert {
  display: flex; align-items: center; gap: 6px;
  background: #FEF2F2; border: 1px solid #FECACA;
  border-radius: 10px; padding: 8px 12px;
  font-size: 11px; font-weight: 600; color: #DC2626;
}

/* Empty */
.empty-state {
  text-align: center; padding: 60px 20px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.empty-state p { font-size: 14px; color: #94A3B8; margin: 0; }
</style>
