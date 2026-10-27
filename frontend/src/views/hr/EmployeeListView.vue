<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { departmentApi, employeeApi, organizationApi, positionApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { employeeStatuses, label } from '@/utils/hr'

const router = useRouter()
const auth = useAuthStore()

const employees = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)

const organizations = ref([])
const departments = ref([])
const positions = ref([])
const filters = ref({ organization_id: null, department_id: null, position_id: null, status: 'active', search: '' })
const statusOptions = [{ value: null, title: 'Barchasi' }, ...Object.entries(employeeStatuses).map(([value, title]) => ({ value, title }))]

const headers = [
  { title: 'F.I.Sh', key: 'full_name', sortable: false },
  { title: 'Tabel', key: 'personnel_number', sortable: false, width: 90 },
  { title: 'JShShIR', key: 'pinfl', sortable: false, width: 150 },
  { title: 'Tashkilot', key: 'organization.short_name', sortable: false },
  { title: "Bo'lim", key: 'department.name', sortable: false },
  { title: 'Lavozim', key: 'position.name', sortable: false },
  { title: 'Qabul qilingan', key: 'hire_date', sortable: false, width: 130 },
  { title: 'Holati', key: 'status', sortable: false, width: 120 },
]

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries({ ...filters.value, page: page.value }).filter(([, v]) => v !== null && v !== ''))
    const res = await employeeApi.list(params)
    employees.value = res.data.data
    total.value = res.data.meta.total
  } finally {
    loading.value = false
  }
}

let searchTimer
watch(() => filters.value.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 350)
})
watch(() => [filters.value.organization_id, filters.value.department_id, filters.value.position_id, filters.value.status], () => {
  page.value = 1
  load()
})
watch(() => filters.value.organization_id, async (id) => {
  filters.value.department_id = null
  departments.value = id ? (await departmentApi.list({ organization_id: id })).data : []
})

onMounted(async () => {
  const [orgs, pos] = await Promise.all([organizationApi.list(), positionApi.list()])
  organizations.value = auth.assignableOrganizations(orgs.data)
  positions.value = pos.data
  if (!auth.canAccessAllOrganizations) filters.value.organization_id = auth.user?.organization_id ?? null
  load()
})
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Xodimlar</h2>
      </v-col>
      <v-col v-if="auth.can('employees.manage')" cols="auto" class="d-flex ga-2">
        <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-file-upload" @click="router.push('/employees/import')">Import</v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="router.push('/employees/create')">Yangi xodim</v-btn>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense class="mb-2">
          <v-col cols="12" md="3">
            <v-text-field v-model="filters.search" label="F.I.Sh, JShShIR yoki tabel" prepend-inner-icon="mdi-magnify" variant="outlined" density="compact" rounded="lg" hide-details clearable />
          </v-col>
          <v-col v-if="auth.canAccessAllOrganizations" cols="12" md="2">
            <v-select v-model="filters.organization_id" :items="organizations" item-title="short_name" item-value="id" label="Tashkilot" variant="outlined" density="compact" rounded="lg" hide-details clearable />
          </v-col>
          <v-col cols="12" md="2">
            <v-select v-model="filters.department_id" :items="departments" item-title="name" item-value="id" label="Bo'lim" variant="outlined" density="compact" rounded="lg" hide-details clearable :disabled="!filters.organization_id" />
          </v-col>
          <v-col cols="12" md="3">
            <v-autocomplete v-model="filters.position_id" :items="positions" item-title="name" item-value="id" label="Lavozim" variant="outlined" density="compact" rounded="lg" hide-details clearable />
          </v-col>
          <v-col cols="12" md="2">
            <v-select v-model="filters.status" :items="statusOptions" label="Holati" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
        </v-row>

        <v-data-table-server
          v-model:page="page"
          :headers="headers"
          :items="employees"
          :items-length="total"
          :items-per-page="50"
          :items-per-page-options="[50]"
          :loading="loading"
          hover
          @update:page="load"
          @click:row="(_, { item }) => router.push(`/employees/${item.id}`)"
        >
          <template #item.status="{ item }">
            <v-chip size="small" :color="item.status === 'active' ? 'success' : 'grey'" variant="tonal">{{ label(employeeStatuses, item.status) }}</v-chip>
          </template>
          <template #item.hire_date="{ item }">{{ item.hire_date || '—' }}</template>
        </v-data-table-server>
      </v-card-text>
    </v-card>
  </v-container>
</template>
