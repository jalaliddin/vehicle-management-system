<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { employeeApi } from '@/services/api'
import { errorMessage, saveBlob } from '@/utils/hr'

const router = useRouter()

const file = ref(null)
const checking = ref(false)
const importing = ref(false)
const check = ref(null)
const imported = ref(null)
const failure = ref('')

const errorHeaders = [
  { title: 'Qator', key: 'row', width: 90 },
  { title: 'Ustun', key: 'field', width: 200 },
  { title: 'Xato', key: 'message' },
]

const selectedFile = () => (Array.isArray(file.value) ? file.value[0] : file.value)

async function downloadTemplate() {
  saveBlob(await employeeApi.importTemplate(), 'xodimlar-shablon.xlsx')
}

function reset() {
  check.value = null
  imported.value = null
  failure.value = ''
}

async function runCheck() {
  reset()
  checking.value = true
  try {
    check.value = (await employeeApi.import(selectedFile(), true)).data
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    checking.value = false
  }
}

async function runImport() {
  importing.value = true
  failure.value = ''
  try {
    imported.value = (await employeeApi.import(selectedFile(), false)).data.imported
    check.value = null
  } catch (e) {
    if (Array.isArray(e.response?.data?.errors)) check.value = { valid_rows: 0, errors: e.response.data.errors }
    failure.value = errorMessage(e)
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <v-container fluid class="pa-6" style="max-width: 1000px;">
    <v-row class="mb-4" align="center">
      <v-col cols="auto">
        <v-btn icon variant="text" @click="router.push('/employees')"><v-icon>mdi-arrow-left</v-icon></v-btn>
      </v-col>
      <v-col>
        <h2 class="text-h6 font-weight-bold">Xodimlarni import qilish</h2>
      </v-col>
    </v-row>

    <v-card rounded="xl" class="border mb-4">
      <v-card-text class="pa-5">
        <ol class="mb-4 pl-5 text-body-2">
          <li>Shablonni yuklab oling va to'ldiring. Tashkilot — kodi bo'yicha, bo'lim va lavozim — tizimdagi nomi bo'yicha.</li>
          <li>Sanalar: YYYY-MM-DD yoki DD.MM.YYYY. Jinsi: erkak / ayol. Bandlik turi: shtat / shartnoma / yarim_stavka.</li>
          <li>Faylni tekshiring. Xatolar bo'lmasa, import qiling — barcha qatorlar birdaniga saqlanadi.</li>
        </ol>
        <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-download" class="mb-4" @click="downloadTemplate">Shablonni yuklab olish</v-btn>

        <v-file-input v-model="file" accept=".xlsx,.csv" label="Fayl (.xlsx yoki .csv)" variant="outlined" rounded="lg" prepend-icon="" prepend-inner-icon="mdi-paperclip" @update:model-value="reset" />

        <div class="d-flex ga-2">
          <v-btn color="primary" variant="tonal" rounded="lg" :disabled="!selectedFile()" :loading="checking" @click="runCheck">Tekshirish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :disabled="!check || check.errors.length > 0 || !check.valid_rows" :loading="importing" @click="runImport">
            Import qilish
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" class="mb-4" density="compact">{{ failure }}</v-alert>

    <v-alert v-if="imported !== null" type="success" variant="tonal" rounded="lg" class="mb-4">
      {{ imported }} ta xodim qo'shildi.
      <v-btn variant="text" size="small" @click="router.push('/employees')">Ro'yxatga o'tish</v-btn>
    </v-alert>

    <v-card v-if="check" rounded="xl" class="border">
      <v-card-text class="pa-5">
        <div class="mb-3">
          <v-chip color="success" variant="tonal" class="mr-2">To'g'ri qatorlar: {{ check.valid_rows }}</v-chip>
          <v-chip :color="check.errors.length ? 'error' : 'grey'" variant="tonal">Xatolar: {{ check.errors.length }}</v-chip>
        </div>
        <v-data-table v-if="check.errors.length" :headers="errorHeaders" :items="check.errors" density="compact" />
      </v-card-text>
    </v-card>
  </v-container>
</template>
