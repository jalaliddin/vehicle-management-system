<script setup>
import { ref, onMounted, computed } from 'vue'
import { dashboardApi } from '@/services/api'
import { useRouter } from 'vue-router'

const router = useRouter()
const stats = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await dashboardApi.stats()
    stats.value = res.data
  } finally {
    loading.value = false
  }
})

const kpiCards = computed(() => {
  if (!stats.value) return []
  return [
    {
      label: 'Jami Avtomobillar',
      value: stats.value.total_vehicles,
      sub: `${stats.value.active_vehicles} faol`,
      badge: stats.value.broken_vehicles > 0 ? `${stats.value.broken_vehicles} nosoz` : null,
      badgeColor: 'error',
      icon: 'mdi-car',
      gradient: 'linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%)',
      iconBg: 'rgba(255,255,255,0.15)',
    },
    {
      label: 'Haydovchilar',
      value: stats.value.total_drivers,
      sub: `${stats.value.active_drivers} faol`,
      badge: null,
      icon: 'mdi-account-tie',
      gradient: 'linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%)',
      iconBg: 'rgba(255,255,255,0.15)',
    },
    {
      label: "Bugungi varaqalar",
      value: stats.value.today_waybills,
      sub: `${stats.value.pending_waybills} tekshiruvda`,
      badge: stats.value.pending_waybills > 0 ? `${stats.value.pending_waybills} kutmoqda` : null,
      badgeColor: 'warning',
      icon: 'mdi-file-document-multiple',
      gradient: 'linear-gradient(135deg, #059669 0%, #047857 100%)',
      iconBg: 'rgba(255,255,255,0.15)',
    },
    {
      label: "Yo'ldagi transport",
      value: stats.value.active_waybills,
      sub: `${stats.value.maintenance_vehicles} ta'mirda`,
      badge: null,
      icon: 'mdi-map-marker-path',
      gradient: 'linear-gradient(135deg, #D97706 0%, #B45309 100%)',
      iconBg: 'rgba(255,255,255,0.15)',
    },
  ]
})

const statusLabel = (s) => ({
  draft: 'Yaratilgan', mechanic_check: 'Mexanik', mechanic_ok: 'Mexanik ✓',
  doctor_check: 'Shifokor', doctor_ok: 'Shifokor ✓', hq_review: 'HQ',
  approved: 'Tasdiqlandi', in_progress: "Yo'lda", completed: 'Bajarildi', cancelled: 'Bekor',
}[s] || s)

const statusColor = (s) => ({
  draft: '#94A3B8', mechanic_check: '#F59E0B', mechanic_ok: '#3B82F6',
  doctor_check: '#F59E0B', doctor_ok: '#3B82F6', hq_review: '#8B5CF6',
  approved: '#10B981', in_progress: '#059669', completed: '#2563EB', cancelled: '#EF4444',
}[s] || '#94A3B8')

const statusBg = (s) => ({
  draft: '#F1F5F9', mechanic_check: '#FEF3C7', mechanic_ok: '#DBEAFE',
  doctor_check: '#FEF3C7', doctor_ok: '#DBEAFE', hq_review: '#EDE9FE',
  approved: '#D1FAE5', in_progress: '#D1FAE5', completed: '#DBEAFE', cancelled: '#FEE2E2',
}[s] || '#F1F5F9')

const fuelPct = (s) => s.capacity ? Math.round((s.current_balance / s.capacity) * 100) : 0
const fuelColor = (p) => p < 20 ? '#EF4444' : p < 50 ? '#F59E0B' : '#10B981'
const fuelBg   = (p) => p < 20 ? '#FEE2E2' : p < 50 ? '#FEF3C7' : '#D1FAE5'
</script>

<template>
  <div class="dashboard">

    <!-- Skeleton -->
    <div v-if="loading" class="p-grid">
      <div v-for="i in 4" :key="i" class="p-grid-item">
        <v-skeleton-loader type="card" rounded="xl" />
      </div>
    </div>

    <template v-else-if="stats">

      <!-- KPI Cards -->
      <div class="kpi-row">
        <div
          v-for="card in kpiCards"
          :key="card.label"
          class="kpi-card"
          :style="`background: ${card.gradient};`"
        >
          <div class="kpi-top">
            <div>
              <div class="kpi-value">{{ card.value }}</div>
              <div class="kpi-label">{{ card.label }}</div>
            </div>
            <div class="kpi-icon" :style="`background: ${card.iconBg};`">
              <v-icon color="white" size="22">{{ card.icon }}</v-icon>
            </div>
          </div>
          <div class="kpi-bottom">
            <span class="kpi-sub">{{ card.sub }}</span>
            <span v-if="card.badge" class="kpi-badge">{{ card.badge }}</span>
          </div>
        </div>
      </div>

      <!-- Row 2 -->
      <div class="content-row">

        <!-- Active waybills -->
        <div class="content-card" style="flex:0 0 340px;">
          <div class="card-header">
            <div class="card-header-icon" style="background:#DBEAFE;">
              <v-icon color="#2563EB" size="18">mdi-car-arrow-right</v-icon>
            </div>
            <div>
              <div class="card-title">Yo'ldagi transport</div>
              <div class="card-sub">Hozir harakatdagi</div>
            </div>
            <div class="card-badge" style="background:#DBEAFE; color:#1D4ED8;">
              {{ stats.active_waybills_list?.length || 0 }}
            </div>
          </div>

          <div class="card-body">
            <div v-if="!stats.active_waybills_list?.length" class="empty-state">
              <v-icon size="40" color="#CBD5E1">mdi-car-off</v-icon>
              <p>Hozir yo'lda hech kim yo'q</p>
            </div>
            <div
              v-for="wb in stats.active_waybills_list"
              :key="wb.id"
              class="list-row"
              @click="router.push(`/waybills/${wb.id}`)"
            >
              <div class="list-icon" style="background:#D1FAE5;">
                <v-icon color="#059669" size="16">mdi-car</v-icon>
              </div>
              <div class="list-text">
                <span class="list-primary">{{ wb.vehicle?.state_number }}</span>
                <span class="list-secondary">{{ wb.driver?.full_name }}</span>
              </div>
              <v-icon size="14" color="#CBD5E1">mdi-chevron-right</v-icon>
            </div>
          </div>
        </div>

        <!-- Fuel -->
        <div class="content-card" style="flex:1;">
          <div class="card-header">
            <div class="card-header-icon" style="background:#FEF3C7;">
              <v-icon color="#D97706" size="18">mdi-fuel</v-icon>
            </div>
            <div>
              <div class="card-title">Yoqilg'i holati</div>
              <div class="card-sub">Shahobchalar bo'yicha</div>
            </div>
            <button class="card-link" @click="router.push('/fuel/monitoring')">
              Barchasi →
            </button>
          </div>

          <div class="card-body">
            <div class="fuel-grid">
              <div
                v-for="s in (stats.fuel_stations || []).slice(0, 6)"
                :key="s.id"
                class="fuel-item"
                :style="`background: ${fuelBg(fuelPct(s))};`"
              >
                <div class="fuel-header">
                  <span class="fuel-org">{{ s.organization?.short_name }}</span>
                  <span class="fuel-pct" :style="`color: ${fuelColor(fuelPct(s))};`">{{ fuelPct(s) }}%</span>
                </div>
                <div class="fuel-bar-track">
                  <div
                    class="fuel-bar-fill"
                    :style="`width:${fuelPct(s)}%; background:${fuelColor(fuelPct(s))};`"
                  />
                </div>
                <div class="fuel-amount">{{ Number(s.current_balance).toLocaleString() }} L</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 3 -->
      <div class="content-row">

        <!-- Recent waybills -->
        <div class="content-card" style="flex:1;">
          <div class="card-header">
            <div class="card-header-icon" style="background:#EDE9FE;">
              <v-icon color="#7C3AED" size="18">mdi-file-clock</v-icon>
            </div>
            <div>
              <div class="card-title">Oxirgi yo'l varaqalari</div>
              <div class="card-sub">Eng so'nggi faoliyat</div>
            </div>
            <button class="card-link" @click="router.push('/waybills')">
              Barchasi →
            </button>
          </div>

          <div class="card-body pa-0">
            <div v-if="!stats.recent_waybills?.length" class="empty-state">
              <v-icon size="40" color="#CBD5E1">mdi-file-outline</v-icon>
              <p>Yo'l varaqalari mavjud emas</p>
            </div>
            <table v-else class="waybill-table">
              <thead>
                <tr>
                  <th>Raqam</th>
                  <th>Avtomobil</th>
                  <th>Haydovchi</th>
                  <th>Yo'nalish</th>
                  <th>Holat</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="wb in (stats.recent_waybills || []).slice(0, 8)"
                  :key="wb.id"
                  class="waybill-row"
                  @click="router.push(`/waybills/${wb.id}`)"
                >
                  <td><span class="waybill-num">{{ wb.waybill_number }}</span></td>
                  <td>{{ wb.vehicle?.state_number || '—' }}</td>
                  <td>{{ wb.driver?.full_name || '—' }}</td>
                  <td>{{ wb.destination || '—' }}</td>
                  <td>
                    <span
                      class="status-pill"
                      :style="`background:${statusBg(wb.status)}; color:${statusColor(wb.status)};`"
                    >
                      {{ statusLabel(wb.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Broken vehicles -->
        <div class="content-card" style="flex:0 0 300px;">
          <div class="card-header">
            <div class="card-header-icon" style="background:#FEE2E2;">
              <v-icon color="#DC2626" size="18">mdi-car-wrench</v-icon>
            </div>
            <div>
              <div class="card-title">Nosoz avtomobillar</div>
              <div class="card-sub">Tashkilotlar kesimida</div>
            </div>
          </div>

          <div class="card-body">
            <div
              v-if="!(stats.vehicles_by_org || []).filter(o => o.broken_vehicles_count > 0).length"
              class="empty-state"
            >
              <v-icon size="40" color="#10B981">mdi-check-circle</v-icon>
              <p style="color:#10B981;">Barcha avtomobillar ishlamoqda</p>
            </div>
            <div
              v-for="org in (stats.vehicles_by_org || []).filter(o => o.broken_vehicles_count > 0)"
              :key="org.id"
              class="list-row"
            >
              <div class="list-text">
                <span class="list-primary">{{ org.short_name || org.name }}</span>
                <span class="list-secondary">{{ org.vehicles_count }} ta jami</span>
              </div>
              <div class="broken-badge">
                <v-icon size="12" color="#DC2626">mdi-alert-circle</v-icon>
                {{ org.broken_vehicles_count }} nosoz
              </div>
            </div>
          </div>
        </div>

      </div>
    </template>
  </div>
</template>

<style scoped>
.dashboard {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-height: 100%;
}

/* KPI */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
@media (max-width: 1100px) { .kpi-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .kpi-row { grid-template-columns: 1fr; } }

.kpi-card {
  border-radius: 16px;
  padding: 20px;
  cursor: default;
  transition: transform 0.18s, box-shadow 0.18s;
  box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 36px rgba(0,0,0,0.18); }

.kpi-top  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.kpi-value { font-size: 42px; font-weight: 800; color: white; line-height: 1; letter-spacing: -1px; }
.kpi-label { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.75); margin-top: 4px; }
.kpi-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
}
.kpi-bottom { display: flex; align-items: center; justify-content: space-between; }
.kpi-sub { font-size: 12px; color: rgba(255,255,255,0.65); }
.kpi-badge {
  font-size: 11px; font-weight: 600; padding: 3px 8px;
  background: rgba(255,255,255,0.2); border-radius: 6px; color: white;
}

/* Content rows */
.content-row {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}
@media (max-width: 960px) { .content-row { flex-direction: column; } .content-row > * { flex: 1 1 auto !important; } }

.content-card {
  background: white;
  border-radius: 16px;
  border: 1px solid rgba(0,0,0,0.07);
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

/* Card header */
.card-header {
  display: flex; align-items: center; gap: 12px;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.card-header-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.card-title { font-size: 14px; font-weight: 700; color: #0F172A; }
.card-sub   { font-size: 11px; color: #94A3B8; margin-top: 1px; }
.card-badge {
  margin-left: auto; font-size: 13px; font-weight: 700;
  padding: 4px 10px; border-radius: 8px;
}
.card-link {
  margin-left: auto; background: none; border: none; cursor: pointer;
  font-size: 12px; font-weight: 600; color: #2563EB;
  padding: 6px 10px; border-radius: 8px; transition: background 0.15s;
}
.card-link:hover { background: #EFF6FF; }

.card-body { padding: 12px 16px; }
.pa-0 { padding: 0 !important; }

/* List rows */
.list-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 6px; border-radius: 10px;
  cursor: pointer; transition: background 0.12s;
}
.list-row:hover { background: #F8FAFC; }

.list-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.list-text { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.list-primary   { font-size: 13px; font-weight: 600; color: #0F172A; }
.list-secondary { font-size: 11px; color: #94A3B8; }

/* Fuel */
.fuel-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
}
@media (max-width: 700px) { .fuel-grid { grid-template-columns: repeat(2, 1fr); } }

.fuel-item { border-radius: 12px; padding: 12px; }
.fuel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.fuel-org  { font-size: 11px; font-weight: 700; color: #334155; }
.fuel-pct  { font-size: 12px; font-weight: 800; }
.fuel-bar-track {
  height: 6px; border-radius: 99px;
  background: rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 6px;
}
.fuel-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; }
.fuel-amount { font-size: 10px; color: #64748B; }

/* Waybill table */
.waybill-table { width: 100%; border-collapse: collapse; }
.waybill-table th {
  font-size: 11px; font-weight: 700; color: #94A3B8;
  text-transform: uppercase; letter-spacing: 0.5px;
  padding: 10px 16px; text-align: left;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  background: #FAFAFA;
}
.waybill-row { cursor: pointer; transition: background 0.12s; }
.waybill-row:hover { background: #F8FAFC; }
.waybill-row td {
  font-size: 13px; color: #334155;
  padding: 11px 16px;
  border-bottom: 1px solid rgba(0,0,0,0.04);
}
.waybill-row:last-child td { border-bottom: none; }
.waybill-num { font-weight: 700; color: #2563EB; }

.status-pill {
  font-size: 11px; font-weight: 600;
  padding: 3px 9px; border-radius: 6px; white-space: nowrap;
}

.broken-badge {
  display: flex; align-items: center; gap: 4px;
  font-size: 12px; font-weight: 700; color: #DC2626;
  background: #FEE2E2; padding: 4px 10px; border-radius: 8px;
  flex-shrink: 0; white-space: nowrap;
}

/* Empty state */
.empty-state {
  text-align: center; padding: 32px 16px;
  display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.empty-state p { font-size: 13px; color: #94A3B8; margin: 0; }
</style>
