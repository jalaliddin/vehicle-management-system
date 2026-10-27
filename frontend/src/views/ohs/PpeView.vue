<script setup>
import { ref, onMounted } from 'vue'
import { hrReportApi, ppeItemApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, exportCsv, label, ppeKinds, ppeStatuses, today } from '@/utils/hr'
import { useSnack } from '@/utils/useSnack'

const auth = useAuthStore()
const { snack, notify } = useSnack()

const tab = ref('catalog')
const items = ref([])
const saving = ref(false)
const dialog = ref(false)
const form = ref({})
const kindOptions = Object.entries(ppeKinds).map(([value, title]) => ({ value, title }))

const itemHeaders = [
  { title: 'Nomi', key: 'name' },
  { title: 'Turi', key: 'kind', width: 170 },
  { title: "O'lchov", key: 'unit', width: 100 },
  { title: 'Kiyish muddati', key: 'default_wear_months', width: 160 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 110, align: 'center' },
]

async function loadItems() {
  items.value = (await ppeItemApi.list()).data
}

function openItem(item = null) {
  form.value = item ? { ...item } : { id: null, name: '', kind: 'clothing', unit: 'dona', default_wear_months: null }
  dialog.value = true
}

async function saveItem() {
  saving.value = true
  try {
    const { id, name, kind, unit, default_wear_months } = form.value
    const data = { name, kind, unit, default_wear_months: default_wear_months || null }
    if (id) await ppeItemApi.update(id, data)
    else await ppeItemApi.create(data)
    dialog.value = false
    await loadItems()
    notify('Saqlandi')
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function removeItem(item) {
  if (!confirm(`"${item.name}" o'chirilsinmi?`)) return
  try {
    await ppeItemApi.delete(item.id)
    await loadItems()
  } catch (e) {
    notify(errorMessage(e), 'error')
  }
}

const period = ref({ from: today().slice(0, 8) + '01', to: today() })
const issues = ref([])
const issuesLoading = ref(false)
const issueHeaders = [
  { title: 'Sana', key: 'issued_on', width: 110 },
  { title: 'Xodim', key: 'employee_name' },
  { title: "Bo'lim", key: 'department' },
  { title: 'Buyum', key: 'ppe_item' },
  { title: 'Soni', key: 'quantity', width: 80 },
  { title: "O'lcham", key: 'size', width: 90 },
  { title: 'Almashtirish', key: 'due_on', width: 120 },
  { title: 'Holati', key: 'status', width: 150 },
]

async function loadIssues() {
  issuesLoading.value = true
  try {
    issues.value = (await hrReportApi.ppeIssues(period.value)).data
  } finally {
    issuesLoading.value = false
  }
}

function exportIssues() {
  exportCsv(issues.value, issueHeaders.map(h => h.key === 'status'
    ? { ...h, value: (r) => label(ppeStatuses, r.status) }
    : h), `shhv-berilgan-${period.value.from}-${period.value.to}.csv`)
}

onMounted(() => {
  loadItems()
  loadIssues()
})
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Maxsus kiyim va SHHV</h2>
      </v-col>
    </v-row>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="catalog">Katalog</v-tab>
      <v-tab value="issued">Berilganlar</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="catalog">
        <v-card rounded="xl" class="border">
          <v-card-text class="pa-4">
            <div v-if="auth.can('ohs.ppe.manage')" class="d-flex mb-2">
              <v-spacer />
              <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="openItem()">Qo'shish</v-btn>
            </div>
            <v-data-table :headers="itemHeaders" :items="items" hover>
              <template #item.kind="{ item }">{{ label(ppeKinds, item.kind) }}</template>
              <template #item.default_wear_months="{ item }">{{ item.default_wear_months ? `${item.default_wear_months} oy` : '—' }}</template>
              <template #item.actions="{ item }">
                <template v-if="auth.can('ohs.ppe.manage')">
                  <v-btn icon size="small" variant="text" color="primary" @click="openItem(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
                  <v-btn icon size="small" variant="text" color="error" @click="removeItem(item)"><v-icon size="18">mdi-delete</v-icon></v-btn>
                </template>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-window-item>

      <v-window-item value="issued">
        <v-card rounded="xl" class="border">
          <v-card-text class="pa-4">
            <v-row align="center" class="mb-2">
              <v-col cols="6" md="3"><v-text-field v-model="period.from" type="date" label="Dan" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
              <v-col cols="6" md="3"><v-text-field v-model="period.to" type="date" label="Gacha" variant="outlined" density="compact" rounded="lg" hide-details /></v-col>
              <v-col cols="auto"><v-btn color="primary" variant="tonal" rounded="lg" @click="loadIssues">Ko'rsatish</v-btn></v-col>
              <v-spacer />
              <v-col cols="auto"><v-btn variant="outlined" rounded="lg" prepend-icon="mdi-microsoft-excel" @click="exportIssues">CSV yuklab olish</v-btn></v-col>
            </v-row>
            <v-data-table :headers="issueHeaders" :items="issues" :loading="issuesLoading" hover>
              <template #item.status="{ item }">{{ label(ppeStatuses, item.status) }}</template>
              <template #item.due_on="{ item }">{{ item.due_on || '—' }}</template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-window-item>
    </v-window>

    <v-dialog v-model="dialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ form.id ? 'Tahrirlash' : 'Yangi buyum' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" label="Nomi" variant="outlined" rounded="lg" />
          <v-select v-model="form.kind" :items="kindOptions" label="Turi" variant="outlined" rounded="lg" />
          <v-text-field v-model="form.unit" label="O'lchov birligi" variant="outlined" rounded="lg" />
          <v-text-field
            v-model.number="form.default_wear_months" type="number" min="1" label="Kiyish muddati (oy)"
            hint="Lavozim me'yori bo'lmaganda ishlatiladi" persistent-hint variant="outlined" rounded="lg"
          />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!form.name" @click="saveItem">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="4000">{{ snack.text }}</v-snackbar>
  </v-container>
</template>
