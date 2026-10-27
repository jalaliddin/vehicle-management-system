<script setup>
import { ref, computed, watch } from 'vue'
import { fuelApi, fuelSupplierApi } from '@/services/api'
import { errorMessage, today } from '@/utils/hr'
import { unitOf } from '@/utils/fuel'

const props = defineProps({ modelValue: Boolean, station: { type: Object, required: true } })
const emit = defineEmits(['update:modelValue', 'saved'])

const show = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })
const suppliers = ref([])
const form = ref({})
const saving = ref(false)
const failure = ref('')

watch(show, async (open) => {
  if (!open) return
  failure.value = ''
  form.value = { fuel_supplier_id: null, document_number: '', document_date: today(), document_amount: null, amount: null, price_per_liter: null }
  if (!suppliers.value.length) suppliers.value = (await fuelSupplierApi.list()).data.filter(s => s.is_active)
})

async function save() {
  saving.value = true
  failure.value = ''
  try {
    await fuelApi.receive(props.station.id, { ...form.value, amount: form.value.amount ?? form.value.document_amount })
    show.value = false
    emit('saved')
  } catch (e) {
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog v-model="show" max-width="520" :fullscreen="$vuetify.display.xs">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-2 d-flex align-center">
        Yoqilg'i qabul qilish
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="show = false" />
      </v-card-title>
      <v-card-text>
        <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" density="compact" class="mb-3">{{ failure }}</v-alert>
        <v-select v-model="form.fuel_supplier_id" :items="suppliers" item-title="name" item-value="id" label="Yetkazib beruvchi" variant="outlined" rounded="lg" />
        <v-row dense>
          <v-col cols="6"><v-text-field v-model="form.document_number" label="Yo'l xati raqami" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="6"><v-text-field v-model="form.document_date" type="date" label="Sanasi" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="6"><v-text-field v-model="form.document_amount" type="number" inputmode="decimal" :label="`Hujjatda (${unitOf(station.fuel_type)})`" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="6"><v-text-field v-model="form.amount" type="number" inputmode="decimal" :label="`Qabul qilindi (${unitOf(station.fuel_type)})`" variant="outlined" rounded="lg" :placeholder="form.document_amount || ''" /></v-col>
          <v-col cols="12"><v-text-field v-model="form.price_per_liter" type="number" inputmode="decimal" label="Narxi (so'm, ixtiyoriy)" variant="outlined" rounded="lg" /></v-col>
        </v-row>
        <div v-if="form.document_amount && form.amount && Number(form.document_amount) !== Number(form.amount)" class="text-warning text-body-2">
          Farq: {{ (Number(form.amount) - Number(form.document_amount)).toFixed(2) }} {{ unitOf(station.fuel_type) }}
        </div>
      </v-card-text>
      <v-card-actions class="pa-5 pt-0">
        <v-btn block size="x-large" color="primary" variant="flat" rounded="lg" :loading="saving"
          :disabled="!form.fuel_supplier_id || !form.document_number || !form.document_amount" @click="save">Saqlash</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
