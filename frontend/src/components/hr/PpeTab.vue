<script setup>
import { ref, computed, onMounted } from 'vue'
import { employeeApi, ppeItemApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, label, ppeStatuses, today } from '@/utils/hr'

const props = defineProps({ employee: { type: Object, required: true } })
const auth = useAuthStore()

const card = ref({ issues: [], norms: [] })
const items = ref([])
const loading = ref(true)
const saving = ref(false)
const failure = ref('')
const issueDialog = ref(false)
const closeDialog = ref(false)
const form = ref({})
const closing = ref({})
const canManage = computed(() => auth.can('ohs.ppe.manage'))

const normHeaders = [
  { title: 'Buyum', key: 'ppe_item.name' },
  { title: "Me'yor", key: 'quantity', width: 90 },
  { title: 'Muddati', key: 'wear_months', width: 100 },
  { title: 'Berilgan', key: 'issued', width: 130 },
  { title: 'Almashtirish', key: 'due', width: 130 },
  { title: '', key: 'actions', sortable: false, width: 110, align: 'end' },
]
const issueHeaders = [
  { title: 'Buyum', key: 'ppe_item.name' },
  { title: 'Soni', key: 'quantity', width: 70 },
  { title: "O'lcham", key: 'size', width: 90 },
  { title: 'Berilgan', key: 'issued_on', width: 120 },
  { title: 'Almashtirish', key: 'due_on', width: 120 },
  { title: 'Holati', key: 'status', width: 150 },
  { title: '', key: 'actions', sortable: false, width: 70, align: 'end' },
]

const isOverdue = (date) => date && date.slice(0, 10) < today()
const sizeFor = (item) => ({ clothing: props.employee.clothing_size, footwear: props.employee.shoe_size }[item?.kind] ?? '')

async function load() {
  loading.value = true
  try {
    card.value = (await employeeApi.ppe(props.employee.id)).data
  } finally {
    loading.value = false
  }
}

async function openIssue(norm = null) {
  failure.value = ''
  if (!items.value.length) items.value = (await ppeItemApi.list()).data
  const item = norm?.ppe_item ?? null
  form.value = { ppe_item_id: item?.id ?? null, quantity: norm?.quantity ?? 1, size: sizeFor(item), issued_on: today(), notes: '' }
  issueDialog.value = true
}

async function saveIssue() {
  saving.value = true
  failure.value = ''
  try {
    await employeeApi.issuePpe(props.employee.id, { ...form.value, size: form.value.size || null })
    issueDialog.value = false
    await load()
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

function openClose(issue) {
  failure.value = ''
  closing.value = { id: issue.id, name: issue.ppe_item?.name, status: 'returned', closed_on: today() }
  closeDialog.value = true
}

async function saveClose() {
  saving.value = true
  failure.value = ''
  try {
    await employeeApi.closePpeIssue(closing.value.id, { status: closing.value.status, closed_on: closing.value.closed_on })
    closeDialog.value = false
    await load()
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-2">
      <div class="text-subtitle-2 font-weight-bold">Lavozim me'yorlari</div>
      <v-spacer />
      <v-btn v-if="canManage" color="primary" variant="flat" rounded="lg" prepend-icon="mdi-plus" @click="openIssue()">Berish</v-btn>
    </div>
    <v-data-table :headers="normHeaders" :items="card.norms" :loading="loading" density="compact" hide-default-footer class="mb-6">
      <template #no-data>Lavozim uchun me'yor belgilanmagan</template>
      <template #item.wear_months="{ item }">{{ item.wear_months }} oy</template>
      <template #item.issued="{ item }">
        <span v-if="item.current_issue">{{ item.current_issue.issued_on.slice(0, 10) }}</span>
        <v-chip v-else size="small" color="error" variant="tonal">Berilmagan</v-chip>
      </template>
      <template #item.due="{ item }">
        <span :class="{ 'text-error font-weight-bold': isOverdue(item.current_issue?.due_on) }">{{ item.current_issue?.due_on?.slice(0, 10) || '—' }}</span>
      </template>
      <template #item.actions="{ item }">
        <v-btn v-if="canManage" size="small" variant="text" color="primary" @click="openIssue(item)">{{ item.current_issue ? 'Almashtirish' : 'Berish' }}</v-btn>
      </template>
    </v-data-table>

    <div class="text-subtitle-2 font-weight-bold mb-2">Berilganlar tarixi</div>
    <v-data-table :headers="issueHeaders" :items="card.issues" :loading="loading" density="compact">
      <template #item.size="{ item }">{{ item.size || '—' }}</template>
      <template #item.issued_on="{ item }">{{ item.issued_on.slice(0, 10) }}</template>
      <template #item.due_on="{ item }">
        <span :class="{ 'text-error font-weight-bold': item.status === 'in_use' && isOverdue(item.due_on) }">{{ item.due_on?.slice(0, 10) || '—' }}</span>
      </template>
      <template #item.status="{ item }">{{ label(ppeStatuses, item.status) }}</template>
      <template #item.actions="{ item }">
        <v-btn v-if="canManage && item.status === 'in_use'" icon size="small" variant="text" title="Qaytarish / hisobdan chiqarish" @click="openClose(item)">
          <v-icon size="18">mdi-keyboard-return</v-icon>
        </v-btn>
      </template>
    </v-data-table>

    <v-dialog v-model="issueDialog" max-width="480">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">Maxsus kiyim yoki SHHV berish</v-card-title>
        <v-card-text>
          <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
          <v-autocomplete
            v-model="form.ppe_item_id" :items="items" item-title="name" item-value="id" label="Buyum" variant="outlined" rounded="lg"
            @update:model-value="(id) => { form.size = sizeFor(items.find(i => i.id === id)) }"
          />
          <v-row dense>
            <v-col cols="6"><v-text-field v-model.number="form.quantity" type="number" min="1" label="Soni" variant="outlined" rounded="lg" /></v-col>
            <v-col cols="6"><v-text-field v-model="form.size" label="O'lcham" variant="outlined" rounded="lg" /></v-col>
          </v-row>
          <v-text-field v-model="form.issued_on" type="date" label="Berilgan sana" variant="outlined" rounded="lg" />
          <v-textarea v-model="form.notes" label="Izoh" rows="2" variant="outlined" rounded="lg" />
          <div class="text-caption text-medium-emphasis">Shu buyumdan foydalanishdagisi bo'lsa, u "almashtirilgan" deb yopiladi.</div>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="issueDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!form.ppe_item_id" @click="saveIssue">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="closeDialog" max-width="420">
      <v-card rounded="xl">
        <v-card-title class="pa-5 pb-2">{{ closing.name }}</v-card-title>
        <v-card-text>
          <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
          <v-radio-group v-model="closing.status">
            <v-radio value="returned" label="Qaytarildi" />
            <v-radio value="written_off" label="Hisobdan chiqarildi" />
          </v-radio-group>
          <v-text-field v-model="closing.closed_on" type="date" label="Sana" variant="outlined" rounded="lg" />
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="closeDialog = false">Bekor</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="saveClose">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
