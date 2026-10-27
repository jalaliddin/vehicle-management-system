<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { hrAlertApi, organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { alertCategories, alertStates, exportCsv, label } from '@/utils/hr'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const alerts = ref([])
const loading = ref(false)
const organizations = ref([])
const filters = ref({ category: route.query.category ?? null, state: route.query.state ?? null, organization_id: null })
const categoryOptions = Object.entries(alertCategories).map(([value, title]) => ({ value, title }))
const stateOptions = Object.entries(alertStates).map(([value, title]) => ({ value, title }))

const headers = [
  { title: 'Xodim', key: 'employee_name' },
  { title: "Bo'lim", key: 'department' },
  { title: 'Lavozim', key: 'position' },
  { title: 'Turi', key: 'category', width: 190 },
  { title: 'Tafsilot', key: 'detail' },
  { title: 'Muddat', key: 'due_date', width: 120 },
  { title: 'Qoldi (kun)', key: 'days_left', width: 110 },
  { title: 'Holati', key: 'state', width: 150 },
]

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v))
    alerts.value = (await hrAlertApi.list(params)).data
    router.replace({ query: { ...(filters.value.category && { category: filters.value.category }), ...(filters.value.state && { state: filters.value.state }) } })
  } finally {
    loading.value = false
  }
}

function exportAlerts() {
  exportCsv(alerts.value, headers.map(h => ({
    ...h,
    value: h.key === 'category' ? (r) => label(alertCategories, r.category) : h.key === 'state' ? (r) => label(alertStates, r.state) : undefined,
  })), `ogohlantirishlar-${new Date().toISOString().slice(0, 10)}.csv`)
}

watch(filters, load, { deep: true })
onMounted(async () => {
  if (auth.canAccessAllOrganizations) organizations.value = (await organizationApi.list()).data
  load()
})
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Ogohlantirishlar</h2></v-col>
      <v-col cols="auto"><v-btn variant="outlined" rounded="lg" prepend-icon="mdi-microsoft-excel" @click="exportAlerts">CSV yuklab olish</v-btn></v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense class="mb-2">
          <v-col cols="12" md="3"><v-select v-model="filters.category" :items="categoryOptions" label="Turi" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="12" md="3"><v-select v-model="filters.state" :items="stateOptions" label="Holati" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col v-if="organizations.length" cols="12" md="3">
            <v-select v-model="filters.organization_id" :items="organizations" item-title="short_name" item-value="id" label="Tashkilot" variant="outlined" density="compact" rounded="lg" hide-details clearable />
          </v-col>
        </v-row>
        <v-data-table :headers="headers" :items="alerts" :loading="loading" hover @click:row="(_, { item }) => item.employee_id && router.push(`/employees/${item.employee_id}`)">
          <template #no-data>Ogohlantirishlar yo'q</template>
          <template #item.category="{ item }">{{ label(alertCategories, item.category) }}</template>
          <template #item.due_date="{ item }">{{ item.due_date || '—' }}</template>
          <template #item.days_left="{ item }">{{ item.days_left ?? '—' }}</template>
          <template #item.state="{ item }">
            <v-chip size="small" variant="tonal" :color="item.state === 'overdue' ? 'error' : 'warning'">{{ label(alertStates, item.state) }}</v-chip>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>
