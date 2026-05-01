<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportApi, organizationApi } from '@/services/api'

const tab = ref('waybills')
const loading = ref(false)
const report = ref(null)
const organizations = ref([])
const filters = ref({ from: '', to: '', org_id: '', status: '' })

const TABS = [
  { key: 'waybills',        label: "Yo'l varaqalari", icon: 'mdi-file-document' },
  { key: 'fuel',            label: "Yoqilg'i",         icon: 'mdi-gas-station' },
  { key: 'vehicles',        label: "Avtomobillar",     icon: 'mdi-car' },
  { key: 'drivers',         label: "Haydovchilar",     icon: 'mdi-account-tie' },
  { key: 'fuel-consumption',label: "Yoqilg'i sarfi",  icon: 'mdi-chart-line' },
]

const WAYBILL_STATUSES = [
  { value: '', label: 'Barchasi' },
  { value: 'draft', label: 'Yaratilgan' },
  { value: 'approved', label: 'Tasdiqlangan' },
  { value: 'in_progress', label: "Yo'lda" },
  { value: 'completed', label: 'Bajarildi' },
  { value: 'cancelled', label: 'Bekor qilindi' },
]

onMounted(async () => {
  organizations.value = (await organizationApi.list()).data
})

const currentTab = computed(() => TABS.find(t => t.key === tab.value))

async function loadReport() {
  loading.value = true
  report.value = null
  try {
    const params = { ...filters.value }
    const apis = {
      waybills:         reportApi.waybills,
      fuel:             reportApi.fuel,
      vehicles:         reportApi.vehicles,
      drivers:          reportApi.drivers,
      'fuel-consumption': reportApi.fuelConsumption,
    }
    const res = await apis[tab.value](params)
    report.value = res.data
  } finally {
    loading.value = false
  }
}

function switchTab(key) {
  tab.value = key
  report.value = null
}

// Summary items (non-object top-level fields)
const summaryItems = computed(() => {
  if (!report.value) return []
  return Object.entries(report.value)
    .filter(([, v]) => typeof v !== 'object')
    .map(([k, v]) => ({ key: k, value: v }))
})

// Table items
const tableItems = computed(() => report.value?.items || [])
const tableKeys = computed(() => {
  if (!tableItems.value.length) return []
  return Object.keys(tableItems.value[0]).filter(k => typeof tableItems.value[0][k] !== 'object')
})

const LABEL_MAP = {
  total: 'Jami',
  completed: 'Bajarildi',
  in_progress: "Yo'lda",
  cancelled: 'Bekor',
  total_fuel: "Jami yoqilg'i (L)",
  total_issued: "Berilgan (L)",
  total_consumed: "Sarflangan (L)",
  total_vehicles: 'Jami avtomobil',
  active: 'Aktiv',
  broken: 'Nosoz',
  maintenance: 'Ta\'mirda',
  total_drivers: 'Jami haydovchi',
  avg_consumption: "O'rtacha sarfi",
  waybill_number: "Varaqa raqami",
  status: 'Holat',
  vehicle: 'Avtomobil',
  driver: 'Haydovchi',
  destination: "Yo'nalish",
  planned_km: 'Rej. km',
  actual_km: 'Haq. km',
  fuel_issued: "Berilgan (L)",
  fuel_consumed: "Sarflangan (L)",
  created_date: 'Sana',
  state_number: 'Davlat raqami',
  model: 'Model',
  type: 'Tur',
  fuel_type: "Yoqilg'i",
  full_name: 'Ism',
  phone: 'Telefon',
  license_category: 'Kategoriya',
  organization: 'Tashkilot',
  period: 'Davr',
  norm: 'Norma (L/100km)',
  actual: "Haqiqiy (L/100km)",
  diff: "Farq (L)",
}
function colLabel(k) { return LABEL_MAP[k] || k }

const STATUS_COLORS = {
  completed: { bg: '#F0FDF4', color: '#10B981' },
  approved:  { bg: '#EFF6FF', color: '#2563EB' },
  in_progress: { bg: '#FFFBEB', color: '#F59E0B' },
  cancelled: { bg: '#FEF2F2', color: '#EF4444' },
  draft:     { bg: '#F8FAFC', color: '#64748B' },
}
function statusStyle(v) {
  const s = STATUS_COLORS[v]
  if (!s) return ''
  return `background:${s.bg}; color:${s.color}; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700;`
}
function isStatus(k) { return k === 'status' }

// KPI gradient colors
const KPI_GRADIENTS = [
  'linear-gradient(135deg,#2563EB,#3B82F6)',
  'linear-gradient(135deg,#7C3AED,#A78BFA)',
  'linear-gradient(135deg,#059669,#34D399)',
  'linear-gradient(135deg,#D97706,#FBBF24)',
  'linear-gradient(135deg,#DC2626,#F87171)',
  'linear-gradient(135deg,#0F172A,#1E3A5F)',
]

function exportCsv() {
  if (!tableItems.value.length) return
  const keys = tableKeys.value
  const rows = [keys.map(colLabel).join(',')]
  tableItems.value.forEach(row => {
    rows.push(keys.map(k => `"${row[k] ?? ''}"`).join(','))
  })
  const blob = new Blob(['﻿' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `hisobot-${tab.value}-${new Date().toISOString().slice(0,10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function printReport() { window.print() }
</script>

<template>
  <div class="page">

    <!-- Header -->
    <div class="page-header no-print">
      <div>
        <h1 class="page-title">Hisobotlar</h1>
        <p class="page-sub">Tashkilot bo'yicha tahlil va statistika</p>
      </div>
      <div v-if="report" class="export-btns">
        <button class="export-btn btn-csv" @click="exportCsv">
          <v-icon size="15" color="#059669">mdi-microsoft-excel</v-icon>
          CSV yuklab olish
        </button>
        <button class="export-btn btn-print" @click="printReport">
          <v-icon size="15" color="white">mdi-printer</v-icon>
          Chop etish
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar no-print">
      <button
        v-for="t in TABS"
        :key="t.key"
        class="tab-btn"
        :class="{ 'tab-active': tab === t.key }"
        @click="switchTab(t.key)"
      >
        <v-icon size="16">{{ t.icon }}</v-icon>
        {{ t.label }}
      </button>
    </div>

    <!-- Filters -->
    <div class="filter-card no-print">
      <div class="filter-grid">
        <div class="filter-field">
          <label>Boshlanish sanasi</label>
          <div class="input-wrap">
            <v-icon size="15" color="#94A3B8">mdi-calendar-start</v-icon>
            <input v-model="filters.from" type="date" />
          </div>
        </div>
        <div class="filter-field">
          <label>Tugash sanasi</label>
          <div class="input-wrap">
            <v-icon size="15" color="#94A3B8">mdi-calendar-end</v-icon>
            <input v-model="filters.to" type="date" />
          </div>
        </div>
        <div class="filter-field">
          <label>Tashkilot</label>
          <div class="input-wrap">
            <v-icon size="15" color="#94A3B8">mdi-office-building</v-icon>
            <select v-model="filters.org_id">
              <option value="">Barchasi</option>
              <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
          </div>
        </div>
        <div v-if="tab === 'waybills'" class="filter-field">
          <label>Holat</label>
          <div class="input-wrap">
            <v-icon size="15" color="#94A3B8">mdi-filter</v-icon>
            <select v-model="filters.status">
              <option v-for="s in WAYBILL_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
        </div>
        <div class="filter-btn-wrap">
          <button class="search-btn" :disabled="loading" @click="loadReport">
            <v-progress-circular v-if="loading" size="16" width="2" indeterminate color="white" />
            <v-icon v-else size="16" color="white">mdi-magnify</v-icon>
            Hisobot olish
          </button>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!report && !loading" class="empty-state">
      <div class="empty-icon">
        <v-icon size="48" color="#CBD5E1">{{ currentTab?.icon || 'mdi-chart-bar' }}</v-icon>
      </div>
      <p>Hisobot olish uchun filtrlarni to'ldiring va tugmani bosing</p>
    </div>

    <!-- Results -->
    <template v-if="report">

      <!-- Print header (only visible on print) -->
      <div class="print-header print-only">
        <img src="/logo.svg" alt="UTG" class="print-logo" />
        <div class="print-title">{{ currentTab?.label }} hisoboti</div>
        <div class="print-date">{{ new Date().toLocaleDateString('uz-UZ') }}</div>
      </div>

      <!-- KPI cards -->
      <div v-if="summaryItems.length" class="kpi-grid">
        <div
          v-for="(item, idx) in summaryItems"
          :key="item.key"
          class="kpi-card"
          :style="`background: ${KPI_GRADIENTS[idx % KPI_GRADIENTS.length]}`"
        >
          <div class="kpi-val">
            {{ typeof item.value === 'number' ? Number(item.value).toLocaleString() : item.value }}
          </div>
          <div class="kpi-key">{{ colLabel(item.key) }}</div>
        </div>
      </div>

      <!-- Table -->
      <div v-if="tableItems.length" class="table-card">
        <div class="table-meta">
          <span class="table-count">{{ tableItems.length }} ta yozuv</span>
        </div>
        <div class="table-scroll">
          <table class="report-table">
            <thead>
              <tr>
                <th>#</th>
                <th v-for="k in tableKeys" :key="k">{{ colLabel(k) }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in tableItems" :key="i">
                <td class="row-num">{{ i + 1 }}</td>
                <td v-for="k in tableKeys" :key="k">
                  <span v-if="isStatus(k) && row[k]" :style="statusStyle(row[k])">
                    {{ row[k] }}
                  </span>
                  <span v-else>{{ row[k] ?? '—' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- No items -->
      <div v-else-if="!summaryItems.length" class="empty-state">
        <v-icon size="40" color="#CBD5E1">mdi-database-off</v-icon>
        <p>Ma'lumot topilmadi</p>
      </div>

    </template>

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
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 16px; margin-bottom: 20px; flex-wrap: wrap;
}
.page-title { font-size: 22px; font-weight: 800; color: #0F172A; margin: 0; }
.page-sub   { font-size: 13px; color: #94A3B8; margin: 4px 0 0; }

.export-btns { display: flex; gap: 8px; }
.export-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: 10px; border: none;
  font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
}
.btn-csv   { background: white; color: #059669; border: 1.5px solid #BBF7D0; }
.btn-csv:hover { background: #F0FDF4; }
.btn-print { background: linear-gradient(135deg,#0F172A,#1E3A5F); color: white; }
.btn-print:hover { opacity: 0.9; }

/* Tabs */
.tabs-bar {
  display: flex; gap: 4px; margin-bottom: 16px;
  background: white; border: 1px solid rgba(0,0,0,0.08);
  border-radius: 14px; padding: 6px; flex-wrap: wrap;
}
.tab-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 16px; border: none; border-radius: 10px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  font-family: inherit; color: #64748B; background: transparent;
  transition: all 0.15s;
}
.tab-btn:hover { background: #F8FAFC; color: #0F172A; }
.tab-active { background: #0F172A !important; color: white !important; }

/* Filters */
.filter-card {
  background: white; border: 1px solid rgba(0,0,0,0.08);
  border-radius: 16px; padding: 20px; margin-bottom: 20px;
}
.filter-grid {
  display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
}
.filter-field { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 150px; }
.filter-field label { font-size: 12px; font-weight: 600; color: #374151; }
.input-wrap {
  display: flex; align-items: center; gap: 8px;
  border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 9px 12px;
  background: #FAFAFA;
}
.input-wrap:focus-within { border-color: #2563EB; background: white; }
.input-wrap input, .input-wrap select {
  flex: 1; border: none; background: none; outline: none;
  font-size: 13px; color: #0F172A; font-family: inherit;
}
.input-wrap select { cursor: pointer; }

.filter-btn-wrap { display: flex; align-items: flex-end; }
.search-btn {
  display: flex; align-items: center; gap: 6px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: white; border: none; border-radius: 10px;
  padding: 10px 20px; font-size: 13px; font-weight: 700;
  cursor: pointer; font-family: inherit; white-space: nowrap;
  box-shadow: 0 4px 12px rgba(37,99,235,0.3);
}
.search-btn:hover:not(:disabled) { opacity: 0.9; }
.search-btn:disabled { opacity: 0.7; cursor: not-allowed; }

/* Empty state */
.empty-state {
  text-align: center; padding: 60px 20px;
  display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.empty-icon {
  width: 80px; height: 80px; background: #F8FAFC;
  border-radius: 20px; display: flex; align-items: center; justify-content: center;
}
.empty-state p { font-size: 14px; color: #94A3B8; margin: 0; max-width: 280px; text-align: center; line-height: 1.5; }

/* KPI cards */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px; margin-bottom: 20px;
}
.kpi-card {
  border-radius: 16px; padding: 20px;
  display: flex; flex-direction: column; gap: 4px;
}
.kpi-val { font-size: 28px; font-weight: 800; color: white; line-height: 1; }
.kpi-key { font-size: 11px; color: rgba(255,255,255,0.7); font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }

/* Table */
.table-card {
  background: white; border: 1px solid rgba(0,0,0,0.08);
  border-radius: 16px; overflow: hidden;
}
.table-meta {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  display: flex; justify-content: space-between; align-items: center;
}
.table-count { font-size: 13px; font-weight: 700; color: #374151; }
.table-scroll { overflow-x: auto; }

.report-table {
  width: 100%; border-collapse: collapse; font-size: 13px;
}
.report-table thead tr {
  background: #F8FAFC;
}
.report-table th {
  padding: 11px 16px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748B;
  text-transform: uppercase; letter-spacing: 0.3px;
  border-bottom: 1px solid rgba(0,0,0,0.06); white-space: nowrap;
}
.report-table th:first-child { color: #94A3B8; width: 40px; }
.report-table td {
  padding: 11px 16px; border-bottom: 1px solid rgba(0,0,0,0.04);
  color: #0F172A; white-space: nowrap;
}
.report-table tbody tr:last-child td { border-bottom: none; }
.report-table tbody tr:hover td { background: #F8FAFC; }
.row-num { color: #94A3B8 !important; font-size: 12px !important; }

/* ===================== PRINT ===================== */
.print-only { display: none; }

@media print {
  .no-print { display: none !important; }
  .print-only { display: flex !important; }
  .page { padding: 0 !important; }

  .print-header {
    display: flex; align-items: center; gap: 16px;
    padding: 16px 0; border-bottom: 2px solid #0F172A; margin-bottom: 20px;
  }
  .print-logo { height: 40px; width: auto; }
  .print-title { font-size: 18px; font-weight: 800; color: #0F172A; flex: 1; }
  .print-date  { font-size: 12px; color: #64748B; }

  .kpi-grid {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .table-card { border: 1px solid #E2E8F0 !important; }
  .report-table thead tr {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .report-table tbody tr:nth-child(even) td {
    background: #F8FAFC;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}

@media (max-width: 640px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; }
  .filter-grid { flex-direction: column; }
}
</style>
