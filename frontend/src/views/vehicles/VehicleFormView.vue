<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { vehicleApi, organizationApi } from '@/services/api'

const router = useRouter()
const route = useRoute()
const isEdit = !!route.params.id
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const serverError = ref('')
const organizations = ref([])

const form = ref({
  organization_id: null,
  state_number: '',
  model: '',
  manufacture_year: new Date().getFullYear(),
  vehicle_type: 'car',
  fuel_type: 'benzin',
  fuel_norm: '',
  fuel_norm_ac: '',
  color: '',
  engine_power: '',
  seat_count: '',
  chassis_number: '',
  engine_number: '',
  tech_passport_number: '',
  status: 'active',
  notes: '',
})

const vehicleTypes = [
  { title: 'Yengil avtomobil', value: 'car' },
  { title: 'Yuk avtomobili', value: 'truck' },
  { title: 'Avtobus', value: 'bus' },
  { title: 'Maxsus texnika', value: 'special' },
]

const fuelTypes = [
  { title: 'Benzin', value: 'benzin' },
  { title: 'Dizel', value: 'dizel' },
  { title: 'Gaz (metan/propan)', value: 'gaz' },
]

const statusItems = [
  { title: 'Faol', value: 'active' },
  { title: 'Nosoz', value: 'broken' },
  { title: "Ta'mirda", value: 'maintenance' },
  { title: 'Faol emas', value: 'inactive' },
]

const statusColors = { active: '#059669', broken: '#DC2626', maintenance: '#D97706', inactive: '#94A3B8' }

function validate() {
  const e = {}
  if (!form.value.organization_id) e.organization_id = 'Tashkilotni tanlang'
  if (!form.value.state_number?.trim()) e.state_number = 'Davlat raqamini kiriting'
  if (!form.value.model?.trim()) e.model = 'Modelni kiriting'
  if (!form.value.manufacture_year) e.manufacture_year = 'Yilni kiriting'
  errors.value = e
  return Object.keys(e).length === 0
}

async function load() {
  organizations.value = (await organizationApi.list()).data
  if (isEdit) {
    loading.value = true
    try {
      const res = await vehicleApi.show(route.params.id)
      Object.assign(form.value, res.data)
    } finally {
      loading.value = false
    }
  }
}

async function save() {
  if (!validate()) return
  saving.value = true
  serverError.value = ''
  try {
    if (isEdit) await vehicleApi.update(route.params.id, form.value)
    else await vehicleApi.create(form.value)
    router.push('/vehicles')
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      errors.value = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, v[0]]))
      serverError.value = "Formda xatolar mavjud"
    } else {
      serverError.value = data?.message || 'Xatolik yuz berdi'
    }
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page-wrap">
    <div class="page-header">
      <button class="back-btn" @click="router.back()">
        <v-icon size="18">mdi-arrow-left</v-icon> Orqaga
      </button>
      <div>
        <h1 class="page-title">{{ isEdit ? 'Avtomobilni tahrirlash' : "Yangi avtomobil qo'shish" }}</h1>
        <p class="page-sub">{{ isEdit ? "Ma'lumotlarni yangilang" : "Yangi transport vositasini ro'yxatdan o'tkazing" }}</p>
      </div>
    </div>

    <div v-if="serverError" class="server-error">
      <v-icon size="18" color="#DC2626">mdi-alert-circle</v-icon> {{ serverError }}
    </div>

    <v-skeleton-loader v-if="loading" type="article" rounded="xl" />

    <div v-else class="form-grid">
      <div class="form-main">

        <!-- Asosiy ma'lumotlar -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="icon-box" style="background:#DBEAFE;"><v-icon color="#2563EB" size="18">mdi-car</v-icon></div>
            Asosiy ma'lumotlar
          </div>

          <div class="fields-body">
            <div class="field-row two-col">
              <div class="field-item" :class="{ 'has-error': errors.organization_id }">
                <label>Tashkilot <span class="req">*</span></label>
                <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id"
                  placeholder="Tashkilotni tanlang" prepend-inner-icon="mdi-domain" variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.organization_id" class="err">{{ errors.organization_id }}</span>
              </div>
              <div class="field-item" :class="{ 'has-error': errors.state_number }">
                <label>Davlat raqami <span class="req">*</span></label>
                <v-text-field v-model="form.state_number" placeholder="01 A 123 BC" prepend-inner-icon="mdi-card-text"
                  variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.state_number" class="err">{{ errors.state_number }}</span>
              </div>
            </div>

            <div class="field-row two-col">
              <div class="field-item" :class="{ 'has-error': errors.model }">
                <label>Model <span class="req">*</span></label>
                <v-text-field v-model="form.model" placeholder="Chevrolet Damas" prepend-inner-icon="mdi-car-info"
                  variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.model" class="err">{{ errors.model }}</span>
              </div>
              <div class="field-item" :class="{ 'has-error': errors.manufacture_year }">
                <label>Ishlab chiqarilgan yil <span class="req">*</span></label>
                <v-text-field v-model="form.manufacture_year" type="number" placeholder="2020"
                  prepend-inner-icon="mdi-calendar" variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.manufacture_year" class="err">{{ errors.manufacture_year }}</span>
              </div>
            </div>

            <div class="field-row three-col">
              <div class="field-item">
                <label>Transport turi</label>
                <v-select v-model="form.vehicle_type" :items="vehicleTypes" item-title="title" item-value="value"
                  prepend-inner-icon="mdi-car-settings" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Rang</label>
                <v-text-field v-model="form.color" placeholder="Oq" prepend-inner-icon="mdi-palette"
                  variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>O'rindiqlar soni</label>
                <v-text-field v-model="form.seat_count" type="number" placeholder="5"
                  prepend-inner-icon="mdi-seat" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
            </div>
          </div>
        </div>

        <!-- Yoqilg'i -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="icon-box" style="background:#FEF3C7;"><v-icon color="#D97706" size="18">mdi-fuel</v-icon></div>
            Yoqilg'i ma'lumotlari
          </div>
          <div class="fields-body">
            <div class="field-row three-col">
              <div class="field-item">
                <label>Yoqilg'i turi</label>
                <v-select v-model="form.fuel_type" :items="fuelTypes" item-title="title" item-value="value"
                  prepend-inner-icon="mdi-gas-station" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Me'yor (L/100km)</label>
                <v-text-field v-model="form.fuel_norm" type="number" placeholder="8.5"
                  prepend-inner-icon="mdi-gauge" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>AC bilan me'yor (L/100km)</label>
                <v-text-field v-model="form.fuel_norm_ac" type="number" placeholder="10.0"
                  prepend-inner-icon="mdi-air-conditioner" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
            </div>
          </div>
        </div>

        <!-- Texnik -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="icon-box" style="background:#D1FAE5;"><v-icon color="#059669" size="18">mdi-cog</v-icon></div>
            Texnik ma'lumotlar
          </div>
          <div class="fields-body">
            <div class="field-row three-col">
              <div class="field-item">
                <label>Shassi raqami</label>
                <v-text-field v-model="form.chassis_number" placeholder="Ixtiyoriy"
                  prepend-inner-icon="mdi-identifier" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Motor raqami</label>
                <v-text-field v-model="form.engine_number" placeholder="Ixtiyoriy"
                  prepend-inner-icon="mdi-engine" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Motor quvvati (ot kuchi)</label>
                <v-text-field v-model="form.engine_power" type="number" placeholder="75"
                  prepend-inner-icon="mdi-lightning-bolt" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
            </div>
            <div class="field-row">
              <div class="field-item">
                <label>Texnik pasport raqami</label>
                <v-text-field v-model="form.tech_passport_number" placeholder="Ixtiyoriy"
                  prepend-inner-icon="mdi-file-certificate" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
            </div>
            <div class="field-row">
              <div class="field-item">
                <label>Izoh</label>
                <v-textarea v-model="form.notes" placeholder="Qo'shimcha ma'lumot..." variant="outlined" rounded="lg" rows="2" hide-details />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Side -->
      <div class="form-side">
        <div class="summary-card">
          <div class="summary-title">Holat</div>
          <div class="status-selector">
            <button
              v-for="s in statusItems" :key="s.value"
              class="status-btn"
              :class="{ active: form.status === s.value }"
              :style="form.status === s.value ? `border-color:${statusColors[s.value]}; background:${statusColors[s.value]}18; color:${statusColors[s.value]}` : ''"
              @click="form.status = s.value"
            >
              {{ s.title }}
            </button>
          </div>

          <div class="divider" />

          <div class="summary-info">
            <div class="srow"><span>Tashkilot</span><span>{{ organizations.find(o => o.id === form.organization_id)?.short_name || '—' }}</span></div>
            <div class="srow"><span>Davlat raqami</span><span>{{ form.state_number || '—' }}</span></div>
            <div class="srow"><span>Model</span><span>{{ form.model || '—' }}</span></div>
            <div class="srow"><span>Yoqilg'i</span><span>{{ form.fuel_norm ? `${form.fuel_norm} L/100km` : '—' }}</span></div>
          </div>

          <div class="divider" />

          <button class="submit-btn" :disabled="saving" @click="save">
            <v-progress-circular v-if="saving" size="18" width="2" indeterminate color="white" />
            <template v-else>
              <v-icon size="16">{{ isEdit ? 'mdi-content-save' : 'mdi-plus' }}</v-icon>
              {{ isEdit ? 'Saqlash' : "Qo'shish" }}
            </template>
          </button>
          <button class="cancel-btn" @click="router.back()">Bekor qilish</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-wrap { padding: 24px; max-width: 1100px; margin: 0 auto; font-family: 'Inter', system-ui, sans-serif; }
.page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.back-btn { display: flex; align-items: center; gap: 6px; background: white; border: 1px solid rgba(0,0,0,0.1); padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; transition: background 0.15s; flex-shrink: 0; }
.back-btn:hover { background: #F8FAFC; }
.page-title { font-size: 22px; font-weight: 800; color: #0F172A; margin: 0; }
.page-sub { font-size: 13px; color: #94A3B8; margin: 2px 0 0; }
.server-error { display: flex; align-items: center; gap: 8px; background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; font-size: 13px; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
.form-grid { display: flex; gap: 20px; align-items: flex-start; }
.form-main { flex: 1; display: flex; flex-direction: column; gap: 16px; }
.form-side { width: 260px; flex-shrink: 0; position: sticky; top: 80px; }
@media (max-width: 860px) { .form-grid { flex-direction: column; } .form-side { width: 100%; position: static; } }
.form-card { background: white; border: 1px solid rgba(0,0,0,0.07); border-radius: 16px; overflow: hidden; }
.form-card-title { display: flex; align-items: center; gap: 10px; padding: 14px 20px; font-size: 14px; font-weight: 700; color: #0F172A; border-bottom: 1px solid rgba(0,0,0,0.06); background: #FAFAFA; }
.icon-box { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.fields-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; }
.field-row { }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
@media (max-width: 640px) { .two-col, .three-col { grid-template-columns: 1fr; } }
.field-item { display: flex; flex-direction: column; gap: 5px; }
.field-item label { font-size: 12px; font-weight: 600; color: #374151; }
.req { color: #EF4444; }
.err { font-size: 11px; color: #EF4444; }
.has-error :deep(.v-field) { --v-field-border-color: #EF4444 !important; }
.summary-card { background: white; border: 1px solid rgba(0,0,0,0.07); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 14px; }
.summary-title { font-size: 14px; font-weight: 700; color: #0F172A; }
.status-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.status-btn { padding: 8px; border: 1.5px solid rgba(0,0,0,0.1); border-radius: 9px; font-size: 12px; font-weight: 600; color: #64748B; cursor: pointer; background: none; transition: all 0.15s; }
.status-btn:hover { background: #F8FAFC; }
.status-btn.active { font-weight: 700; }
.divider { height: 1px; background: rgba(0,0,0,0.07); }
.summary-info { display: flex; flex-direction: column; gap: 8px; }
.srow { display: flex; justify-content: space-between; font-size: 12px; }
.srow span:first-child { color: #94A3B8; }
.srow span:last-child { font-weight: 600; color: #0F172A; }
.submit-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #2563EB, #7C3AED); color: white; font-size: 14px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(37,99,235,0.3); transition: opacity 0.15s; }
.submit-btn:hover:not(:disabled) { opacity: 0.9; }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.cancel-btn { width: 100%; padding: 10px; background: none; border: 1px solid rgba(0,0,0,0.1); color: #64748B; font-size: 13px; font-weight: 500; border-radius: 12px; cursor: pointer; transition: background 0.15s; }
.cancel-btn:hover { background: #F8FAFC; }
</style>
