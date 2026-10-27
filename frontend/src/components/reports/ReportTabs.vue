<script setup>
// Tabs of reports driven by config: parameters, a table, CSV export and print.
// Each report: { key, title, params, fetch, columns: [{ key, title, value? }], rows?(data), summary?(data), rowClass?(row) }
import { ref, computed, watch, onMounted } from 'vue'
import { organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { exportCsv, today } from '@/utils/hr'

const props = defineProps({
  title: { type: String, required: true },
  reports: { type: Array, required: true },
})

const auth = useAuthStore()
const tab = ref(props.reports[0]?.key)
const current = computed(() => props.reports.find(r => r.key === tab.value))
const params = ref({})
const organizations = ref([])
const organizationId = ref(null)
const rows = ref([])
const summary = ref('')
const loading = ref(false)

const headers = computed(() => current.value.columns.map(c => ({ title: c.title, key: c.key, sortable: false })))
const cell = (column, row) => (column.value ? column.value(row) : row[column.key]) ?? '—'
const rowProps = ({ item }) => ({ class: current.value.rowClass?.(item) ?? '' })

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
      <v-col><h2 class="text-h6 font-weight-bold">{{ title }}</h2></v-col>
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
        <v-data-table :headers="headers" :items="rows" :loading="loading" :row-props="rowProps" density="compact" :items-per-page="-1" hide-default-footer>
          <template v-for="column in current.columns" :key="column.key" #[`item.${column.key}`]="{ item }">{{ cell(column, item) }}</template>
          <template #no-data>Ma'lumot yo'q</template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<style scoped>
.print-title { display: none; font-size: 18px; font-weight: 700; margin-bottom: 8px; }
:deep(.row-warning) { background: #FEF2F2; }
@media print {
  .no-print { display: none !important; }
  .print-title { display: block; }
}
</style>
