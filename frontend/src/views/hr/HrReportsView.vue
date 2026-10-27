<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { hrReportApi, organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { absenceTypes, briefingTypes, dismissalReasons, eventTypes, exportCsv, label, ppeStatuses, today } from '@/utils/hr'

const auth = useAuthStore()
const yearStart = `${new Date().getFullYear()}-01-01`

// Each report: which permission it needs, its parameters, and how to turn the response into table rows.
const reports = [
  {
    key: 'headcount', title: 'Xodimlar soni', permission: 'employees.view', params: { date: today() },
    fetch: hrReportApi.headcount,
    columns: [{ key: 'organization', title: 'Tashkilot' }, { key: 'department', title: "Bo'lim", value: r => r.department || "Bo'limsiz" }, { key: 'count', title: 'Xodimlar' }],
  },
  {
    key: 'movement', title: 'Kadrlar harakati', permission: 'employees.view', params: { from: yearStart, to: today() },
    fetch: hrReportApi.movement,
    rows: (data) => [...data.hired, ...data.transferred, ...data.dismissed].sort((a, b) => a.effective_date.localeCompare(b.effective_date)),
    columns: [
      { key: 'effective_date', title: 'Sana' }, { key: 'type', title: 'Buyruq', value: r => label(eventTypes, r.type) },
      { key: 'order_number', title: 'Raqami' }, { key: 'employee_name', title: 'Xodim' }, { key: 'organization', title: 'Tashkilot' },
      { key: 'department', title: "Bo'lim" }, { key: 'position', title: 'Lavozim' },
      { key: 'dismissal_reason', title: 'Asos', value: r => r.dismissal_reason ?? '' },
    ],
    summary: (data) => `Qabul: ${data.counts.hired} · O'tkazish: ${data.counts.transferred} · Bo'shatish: ${data.counts.dismissed}`,
  },
  {
    key: 'absent', title: "Ishda yo'qlar", permission: 'employees.view', params: { date: today() },
    fetch: hrReportApi.absent,
    columns: [
      { key: 'employee_name', title: 'Xodim' }, { key: 'department', title: "Bo'lim" }, { key: 'position', title: 'Lavozim' },
      { key: 'type', title: 'Sababi', value: r => label(absenceTypes, r.type) }, { key: 'start_date', title: 'Dan' }, { key: 'end_date', title: 'Gacha' },
    ],
  },
  {
    key: 'leave-schedule', title: "Ta'til jadvali", permission: 'employees.view', params: { year: new Date().getFullYear() },
    fetch: hrReportApi.leaveSchedule,
    columns: [
      { key: 'employee_name', title: 'Xodim' }, { key: 'department', title: "Bo'lim" }, { key: 'entitlement', title: 'Huquq (kun)' },
      { key: 'planned', title: 'Rejadagi davrlar', value: r => r.planned.map(p => `${p.start_date} – ${p.end_date}`).join(', ') },
      { key: 'planned_days', title: 'Reja (kun)' }, { key: 'actual_days', title: 'Haqiqatda (kun)' },
    ],
  },
  {
    key: 'briefing-journal', title: "Yo'riqnomalar jurnali", permission: 'ohs.view', params: { from: yearStart, to: today() },
    fetch: hrReportApi.briefingJournal,
    columns: [
      { key: 'conducted_on', title: 'Sana' }, { key: 'type', title: 'Turi', value: r => label(briefingTypes, r.type) }, { key: 'topic', title: 'Mavzu' },
      { key: 'instructor', title: "O'tkazgan" }, { key: 'participants', title: 'Qatnashchilar', value: r => r.participants.map(p => p.full_name).join(', ') },
    ],
  },
  {
    key: 'ppe-issues', title: 'SHHV berilishi', permission: 'ohs.view', params: { from: yearStart, to: today() },
    fetch: hrReportApi.ppeIssues,
    columns: [
      { key: 'issued_on', title: 'Sana' }, { key: 'employee_name', title: 'Xodim' }, { key: 'department', title: "Bo'lim" },
      { key: 'ppe_item', title: 'Buyum' }, { key: 'quantity', title: 'Soni' }, { key: 'size', title: "O'lcham" },
      { key: 'due_on', title: 'Almashtirish' }, { key: 'status', title: 'Holati', value: r => label(ppeStatuses, r.status) },
    ],
  },
  {
    key: 'ppe-demand', title: 'SHHV ehtiyoji', permission: 'ohs.view', params: { months: 3 },
    fetch: hrReportApi.ppeDemand,
    columns: [{ key: 'ppe_item', title: 'Buyum' }, { key: 'size', title: "O'lcham", value: r => r.size || '—' }, { key: 'quantity', title: 'Soni' }, { key: 'unit', title: "O'lchov" }],
  },
].filter(r => auth.can(r.permission))

const tab = ref(reports[0]?.key)
const current = computed(() => reports.find(r => r.key === tab.value))
const params = ref({})
const organizations = ref([])
const organizationId = ref(null)
const rows = ref([])
const summary = ref('')
const loading = ref(false)

const headers = computed(() => current.value.columns.map(c => ({ title: c.title, key: c.key, sortable: false })))
const cell = (column, row) => (column.value ? column.value(row) : row[column.key]) ?? '—'

async function load() {
  if (!current.value) return
  loading.value = true
  try {
    const query = { ...params.value, ...(organizationId.value && { organization_id: organizationId.value }) }
    const data = (await current.value.fetch(query)).data
    rows.value = current.value.rows ? current.value.rows(data) : data
    summary.value = current.value.summary ? current.value.summary(data) : ''
  } finally {
    loading.value = false
  }
}

function printReport() {
  window.print()
}

function exportReport() {
  exportCsv(rows.value, current.value.columns, `${current.value.key}-${today()}.csv`)
}

watch(tab, () => {
  params.value = { ...current.value.params }
  rows.value = []
  load()
}, { immediate: true })

onMounted(async () => {
  if (auth.canAccessAllOrganizations) organizations.value = (await organizationApi.list()).data
})
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4 no-print" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Kadrlar va mehnat muhofazasi hisobotlari</h2></v-col>
    </v-row>

    <v-tabs v-model="tab" color="primary" show-arrows class="mb-4 no-print">
      <v-tab v-for="r in reports" :key="r.key" :value="r.key">{{ r.title }}</v-tab>
    </v-tabs>

    <v-card v-if="current" rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense align="center" class="mb-2 no-print">
          <v-col v-if="'date' in params" cols="6" md="2"><v-text-field v-model="params.date" type="date" label="Sana" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <template v-if="'from' in params">
            <v-col cols="6" md="2"><v-text-field v-model="params.from" type="date" label="Dan" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
            <v-col cols="6" md="2"><v-text-field v-model="params.to" type="date" label="Gacha" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          </template>
          <v-col v-if="'year' in params" cols="6" md="2"><v-text-field v-model.number="params.year" type="number" label="Yil" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <v-col v-if="'months' in params" cols="6" md="2">
            <v-select v-model="params.months" :items="[3, 6, 12]" label="Oy" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
          <v-col v-if="organizations.length" cols="12" md="3">
            <v-select v-model="organizationId" :items="organizations" item-title="short_name" item-value="id" label="Tashkilot" variant="outlined" density="compact" rounded="lg" hide-details clearable />
          </v-col>
          <v-col cols="auto"><v-btn color="primary" variant="tonal" rounded="lg" @click="load">Ko'rsatish</v-btn></v-col>
          <v-spacer />
          <v-col cols="auto" class="d-flex ga-2">
            <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-microsoft-excel" @click="exportReport">CSV</v-btn>
            <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-printer" @click="printReport">Chop etish</v-btn>
          </v-col>
        </v-row>

        <div class="print-title">{{ current.title }}</div>
        <div v-if="summary" class="text-body-2 mb-2">{{ summary }}</div>
        <v-data-table :headers="headers" :items="rows" :loading="loading" density="compact" :items-per-page="-1" hide-default-footer>
          <template v-for="column in current.columns" :key="column.key" #[`item.${column.key}`]="{ item }">{{ cell(column, item) }}</template>
          <template #no-data>Ma'lumot yo'q</template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<style scoped>
.print-title { display: none; font-size: 18px; font-weight: 700; margin-bottom: 8px; }
@media print {
  .no-print { display: none !important; }
  .print-title { display: block; }
}
</style>
