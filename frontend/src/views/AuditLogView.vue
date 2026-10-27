<script setup>
import { ref, watch, onMounted } from 'vue'
import { auditLogApi } from '@/services/api'
import { auditEvents, label } from '@/utils/hr'

const entries = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)
const filters = ref({ event: null, employee_id: null, user_id: null, from: null, to: null })
const eventOptions = Object.entries(auditEvents).map(([value, title]) => ({ value, title }))
const expanded = ref([])

const headers = [
  { title: 'Vaqt', key: 'created_at', width: 170 },
  { title: 'Foydalanuvchi', key: 'user.name', width: 180 },
  { title: 'Amal', key: 'event', width: 150 },
  { title: 'Obyekt', key: 'auditable_type' },
  { title: 'ID', key: 'auditable_id', width: 80 },
  { title: 'IP', key: 'ip_address', width: 130 },
  { title: '', key: 'data-table-expand', width: 50 },
]

const subject = (type) => type?.split('\\').pop()
const pretty = (values) => (values ? JSON.stringify(values, null, 2) : '—')

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries({ ...filters.value, page: page.value }).filter(([, v]) => v))
    const res = await auditLogApi.list(params)
    entries.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

watch(filters, () => { page.value = 1; load() }, { deep: true })
onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Amallar jurnali</h2></v-col>
    </v-row>
    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense class="mb-2">
          <v-col cols="12" md="3"><v-select v-model="filters.event" :items="eventOptions" label="Amal" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.employee_id" type="number" label="Xodim ID" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.user_id" type="number" label="Foydalanuvchi ID" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.from" type="date" label="Dan" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.to" type="date" label="Gacha" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
        </v-row>
        <v-data-table-server
          v-model:page="page" v-model:expanded="expanded" :headers="headers" :items="entries" :items-length="total"
          :items-per-page="50" :items-per-page-options="[50]" :loading="loading" show-expand item-value="id" density="compact" @update:page="load"
        >
          <template #item.created_at="{ item }">{{ new Date(item.created_at).toLocaleString('uz-UZ') }}</template>
          <template #item.user.name="{ item }">{{ item.user?.name || 'Tizim' }}</template>
          <template #item.event="{ item }">{{ label(auditEvents, item.event) }}</template>
          <template #item.auditable_type="{ item }">{{ subject(item.auditable_type) }}</template>
          <template #expanded-row="{ columns, item }">
            <tr>
              <td :colspan="columns.length" class="pa-3">
                <v-row dense>
                  <v-col cols="6"><div class="text-caption">Oldin</div><pre class="audit-json">{{ pretty(item.old_values) }}</pre></v-col>
                  <v-col cols="6"><div class="text-caption">Keyin</div><pre class="audit-json">{{ pretty(item.new_values) }}</pre></v-col>
                </v-row>
              </td>
            </tr>
          </template>
        </v-data-table-server>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<style scoped>
.audit-json { font-size: 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px; white-space: pre-wrap; word-break: break-word; }
</style>
