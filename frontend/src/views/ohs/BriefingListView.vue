<script setup>
import { ref, watch, onMounted } from 'vue'
import { briefingApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { briefingTypes, label } from '@/utils/hr'
import BriefingDialog from '@/components/hr/BriefingDialog.vue'

const auth = useAuthStore()
const briefings = ref([])
const total = ref(0)
const page = ref(1)
const loading = ref(false)
const filters = ref({ type: null, from: null, to: null })
const typeOptions = Object.entries(briefingTypes).map(([value, title]) => ({ value, title }))
const dialog = ref(false)
const editing = ref(null)
const canManage = () => auth.can('ohs.briefings.manage')

const headers = [
  { title: 'Sana', key: 'conducted_on', width: 120 },
  { title: 'Turi', key: 'type', width: 170 },
  { title: 'Mavzu', key: 'topic' },
  { title: 'Tashkilot', key: 'organization.short_name', width: 140 },
  { title: "O'tkazgan", key: 'instructor' },
  { title: 'Qatnashchilar', key: 'participants_count', width: 130 },
  { title: '', key: 'actions', sortable: false, width: 100, align: 'end' },
]

async function load() {
  loading.value = true
  try {
    const params = Object.fromEntries(Object.entries({ ...filters.value, page: page.value }).filter(([, v]) => v))
    const res = await briefingApi.list(params)
    briefings.value = res.data.data
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

async function edit(item) {
  editing.value = (await briefingApi.show(item.id)).data
  dialog.value = true
}

function create() {
  editing.value = null
  dialog.value = true
}

async function remove(item) {
  if (!confirm("Yo'riqnoma yozuvi o'chirilsinmi?")) return
  await briefingApi.delete(item.id)
  load()
}

watch(filters, () => { page.value = 1; load() }, { deep: true })
onMounted(load)
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col><h2 class="text-h6 font-weight-bold">Yo'riqnomalar jurnali</h2></v-col>
      <v-col v-if="canManage()" cols="auto">
        <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="create">Yo'riqnoma o'tkazish</v-btn>
      </v-col>
    </v-row>
    <v-card rounded="xl" class="border">
      <v-card-text class="pa-4">
        <v-row dense class="mb-2">
          <v-col cols="12" md="3"><v-select v-model="filters.type" :items="typeOptions" label="Turi" variant="outlined" density="compact" rounded="lg" hide-details clearable /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.from" type="date" label="Dan" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
          <v-col cols="6" md="2"><v-text-field v-model="filters.to" type="date" label="Gacha" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
        </v-row>
        <v-data-table-server
          v-model:page="page" :headers="headers" :items="briefings" :items-length="total" :items-per-page="50" :items-per-page-options="[50]"
          :loading="loading" hover @update:page="load"
        >
          <template #item.conducted_on="{ item }">{{ item.conducted_on.slice(0, 10) }}</template>
          <template #item.type="{ item }">{{ label(briefingTypes, item.type) }}</template>
          <template #item.instructor="{ item }">
            {{ item.instructor ? [item.instructor.last_name, item.instructor.first_name].join(' ') : item.instructor_name || '—' }}
          </template>
          <template #item.actions="{ item }">
            <template v-if="canManage()">
              <v-btn icon size="small" variant="text" color="primary" @click="edit(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
              <v-btn icon size="small" variant="text" color="error" @click="remove(item)"><v-icon size="18">mdi-delete</v-icon></v-btn>
            </template>
          </template>
        </v-data-table-server>
      </v-card-text>
    </v-card>

    <BriefingDialog v-model="dialog" :briefing="editing" @saved="load" />
  </v-container>
</template>
