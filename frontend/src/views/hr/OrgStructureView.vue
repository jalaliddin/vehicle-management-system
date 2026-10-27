<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { departmentApi, organizationApi, positionApi, ppeItemApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/hr'
import { useSnack } from '@/utils/useSnack'

const auth = useAuthStore()
const { snack, notify } = useSnack()

const tab = ref('departments')
const organizations = ref([])
const organizationId = ref(null)
const departments = ref([])
const positions = ref([])
const ppeItems = ref([])
const loading = ref(false)
const saving = ref(false)

// Departments as a flat list in tree order, each with its depth.
const departmentTree = computed(() => {
  const children = (parentId) => departments.value.filter(d => (d.parent_id ?? null) === parentId)
  const walk = (parentId, depth) => children(parentId).flatMap(d => [{ ...d, depth }, ...walk(d.id, depth + 1)])
  return walk(null, 0)
})

const deptDialog = ref(false)
const deptForm = ref({})

function openDepartment(department = null, parent = null) {
  deptForm.value = department
    ? { id: department.id, name: department.name, code: department.code, parent_id: department.parent_id }
    : { id: null, name: '', code: '', parent_id: parent?.id ?? null }
  deptDialog.value = true
}

async function saveDepartment() {
  saving.value = true
  try {
    const { id, ...data } = deptForm.value
    if (id) await departmentApi.update(id, data)
    else await departmentApi.create({ ...data, organization_id: organizationId.value })
    deptDialog.value = false
    await loadDepartments()
    notify('Saqlandi')
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function removeDepartment(department) {
  if (!confirm(`"${department.name}" bo'limini o'chirasizmi?`)) return
  try {
    await departmentApi.delete(department.id)
    await loadDepartments()
    notify("O'chirildi")
  } catch (e) {
    notify(errorMessage(e), 'error')
  }
}

const positionHeaders = [
  { title: 'Lavozim', key: 'name' },
  { title: "Tibbiy ko'rik (oy)", key: 'medical_exam_months', width: 170 },
  { title: "Yo'riqnoma (oy)", key: 'briefing_months', width: 160 },
  { title: 'Amallar', key: 'actions', sortable: false, width: 150, align: 'center' },
]
const positionDialog = ref(false)
const positionForm = ref({})

function openPosition(position = null) {
  positionForm.value = position
    ? { ...position }
    : { id: null, name: '', medical_exam_months: null, briefing_months: null }
  positionDialog.value = true
}

async function savePosition() {
  saving.value = true
  try {
    const { id, name, medical_exam_months, briefing_months } = positionForm.value
    const data = { name, medical_exam_months: medical_exam_months || null, briefing_months: briefing_months || null }
    if (id) await positionApi.update(id, data)
    else await positionApi.create(data)
    positionDialog.value = false
    await loadPositions()
    notify('Saqlandi')
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function removePosition(position) {
  if (!confirm(`"${position.name}" lavozimini o'chirasizmi?`)) return
  try {
    await positionApi.delete(position.id)
    await loadPositions()
    notify("O'chirildi")
  } catch (e) {
    notify(errorMessage(e), 'error')
  }
}

const normsDialog = ref(false)
const normsPosition = ref(null)
const norms = ref([])

async function openNorms(position) {
  normsPosition.value = position
  const res = await positionApi.ppeNorms(position.id)
  norms.value = res.data.map(n => ({ ppe_item_id: n.ppe_item_id, quantity: n.quantity, wear_months: n.wear_months }))
  normsDialog.value = true
}

async function saveNorms() {
  saving.value = true
  try {
    await positionApi.updatePpeNorms(normsPosition.value.id, norms.value.filter(n => n.ppe_item_id))
    normsDialog.value = false
    notify("Me'yorlar saqlandi")
  } catch (e) {
    notify(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function loadDepartments() {
  if (!organizationId.value) return
  loading.value = true
  try {
    departments.value = (await departmentApi.list({ organization_id: organizationId.value })).data
  } finally {
    loading.value = false
  }
}

async function loadPositions() {
  positions.value = (await positionApi.list()).data
}

watch(organizationId, loadDepartments)

onMounted(async () => {
  const [orgs, items] = await Promise.all([organizationApi.list(), ppeItemApi.list(), loadPositions()])
  organizations.value = auth.assignableOrganizations(orgs.data)
  ppeItems.value = items.data
  organizationId.value = auth.user?.organization_id ?? organizations.value[0]?.id ?? null
})
</script>

<template>
  <v-container fluid class="pa-6">
    <v-row class="mb-4" align="center">
      <v-col>
        <h2 class="text-h6 font-weight-bold">Tashkiliy tuzilma</h2>
      </v-col>
    </v-row>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="departments">Bo'limlar</v-tab>
      <v-tab value="positions">Lavozimlar</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="departments">
        <v-card rounded="xl" class="border">
          <v-card-text class="pa-4">
            <v-row align="center" class="mb-2">
              <v-col cols="12" md="5">
                <v-select
                  v-model="organizationId" :items="organizations" item-title="name" item-value="id"
                  label="Tashkilot" variant="outlined" density="compact" rounded="lg" hide-details
                />
              </v-col>
              <v-spacer />
              <v-col cols="auto">
                <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" :disabled="!organizationId" @click="openDepartment()">
                  Bo'lim qo'shish
                </v-btn>
              </v-col>
            </v-row>

            <v-progress-linear v-if="loading" indeterminate color="primary" />
            <div v-else-if="!departmentTree.length" class="text-medium-emphasis pa-6 text-center">Bo'limlar hali qo'shilmagan</div>
            <v-list v-else density="compact">
              <v-list-item v-for="d in departmentTree" :key="d.id" :style="{ paddingLeft: `${16 + d.depth * 28}px` }">
                <template #prepend>
                  <v-icon size="18" color="#64748B">{{ d.depth ? 'mdi-subdirectory-arrow-right' : 'mdi-folder-outline' }}</v-icon>
                </template>
                <v-list-item-title>{{ d.name }} <span v-if="d.code" class="text-medium-emphasis">({{ d.code }})</span></v-list-item-title>
                <template #append>
                  <v-btn icon size="small" variant="text" title="Ichki bo'lim" @click="openDepartment(null, d)"><v-icon size="18">mdi-plus</v-icon></v-btn>
                  <v-btn icon size="small" variant="text" color="primary" @click="openDepartment(d)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
                  <v-btn icon size="small" variant="text" color="error" @click="removeDepartment(d)"><v-icon size="18">mdi-delete</v-icon></v-btn>
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-window-item>

      <v-window-item value="positions">
        <v-card rounded="xl" class="border">
          <v-card-text class="pa-4">
            <div class="d-flex mb-2">
              <v-spacer />
              <v-btn color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="openPosition()">Lavozim qo'shish</v-btn>
            </div>
            <v-data-table :headers="positionHeaders" :items="positions" hover>
              <template #item.medical_exam_months="{ item }">{{ item.medical_exam_months ? `har ${item.medical_exam_months} oyda` : '—' }}</template>
              <template #item.briefing_months="{ item }">{{ item.briefing_months ? `har ${item.briefing_months} oyda` : '—' }}</template>
              <template #item.actions="{ item }">
                <v-btn icon size="small" variant="text" title="SHHV me'yorlari" @click="openNorms(item)"><v-icon size="18">mdi-hard-hat</v-icon></v-btn>
                <v-btn icon size="small" variant="text" color="primary" @click="openPosition(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
                <v-btn icon size="small" variant="text" color="error" @click="removePosition(item)"><v-icon size="18">mdi-delete</v-icon></v-btn>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-window-item>
    </v-window>

    <v-dialog v-model="deptDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ deptForm.id ? "Bo'limni tahrirlash" : "Yangi bo'lim" }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="deptForm.name" label="Nomi" variant="outlined" rounded="lg" />
          <v-text-field v-model="deptForm.code" label="Kodi (ixtiyoriy)" variant="outlined" rounded="lg" />
          <v-select
            v-model="deptForm.parent_id" :items="departmentTree.filter(d => d.id !== deptForm.id)" item-title="name" item-value="id"
            label="Yuqori bo'lim" variant="outlined" rounded="lg" clearable
          />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="deptDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!deptForm.name" @click="saveDepartment">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="positionDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ positionForm.id ? 'Lavozimni tahrirlash' : 'Yangi lavozim' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="positionForm.name" label="Nomi" variant="outlined" rounded="lg" />
          <v-text-field
            v-model.number="positionForm.medical_exam_months" type="number" min="1" label="Davriy tibbiy ko'rik, har necha oyda"
            hint="Bo'sh — talab qilinmaydi" persistent-hint variant="outlined" rounded="lg" class="mb-3"
          />
          <v-text-field
            v-model.number="positionForm.briefing_months" type="number" min="1" label="Takroriy yo'riqnoma, har necha oyda"
            hint="Bo'sh — talab qilinmaydi" persistent-hint variant="outlined" rounded="lg"
          />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="positionDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!positionForm.name" @click="savePosition">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="normsDialog" max-width="680">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">SHHV me'yorlari: {{ normsPosition?.name }}</v-card-title>
        <v-card-text>
          <div v-if="!norms.length" class="text-medium-emphasis mb-3">Me'yor belgilanmagan</div>
          <v-row v-for="(norm, i) in norms" :key="i" dense align="center">
            <v-col cols="6">
              <v-select v-model="norm.ppe_item_id" :items="ppeItems" item-title="name" item-value="id" label="Buyum" variant="outlined" density="compact" rounded="lg" hide-details />
            </v-col>
            <v-col cols="2">
              <v-text-field v-model.number="norm.quantity" type="number" min="1" label="Soni" variant="outlined" density="compact" rounded="lg" hide-details />
            </v-col>
            <v-col cols="3">
              <v-text-field v-model.number="norm.wear_months" type="number" min="1" label="Muddati (oy)" variant="outlined" density="compact" rounded="lg" hide-details />
            </v-col>
            <v-col cols="1">
              <v-btn icon size="small" variant="text" color="error" @click="norms.splice(i, 1)"><v-icon size="18">mdi-close</v-icon></v-btn>
            </v-col>
          </v-row>
          <v-btn variant="tonal" rounded="lg" prepend-icon="mdi-plus" class="mt-3" @click="norms.push({ ppe_item_id: null, quantity: 1, wear_months: 12 })">
            Qator qo'shish
          </v-btn>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="normsDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="saveNorms">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="4000">{{ snack.text }}</v-snackbar>
  </v-container>
</template>
