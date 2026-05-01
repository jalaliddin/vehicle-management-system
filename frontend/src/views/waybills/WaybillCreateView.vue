<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { waybillApi, vehicleApi, driverApi, organizationApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const saving = ref(false)
const errors = ref({})
const serverError = ref('')

const vehicles = ref([])
const drivers = ref([])
const organizations = ref([])

const isHq = computed(() => auth.isHqDispatcher)

const form = ref({
  organization_id: auth.user?.organization_id || null,
  vehicle_id: null,
  driver_id: null,
  vehicle_type: 'car',
  tabel_number: '',
  created_date: new Date().toISOString().split('T')[0],
  valid_until: new Date(Date.now() + 86400000).toISOString().split('T')[0],
  trip_count: 1,
  destination: '',
  destination_organization: '',
  planned_km: '',
  fuel_issued: '',
  has_ac: false,
  notes: '',
})

const vehicleTypes = [
  { title: 'Yengil avtomobil', value: 'car' },
  { title: 'Yuk avtomobili', value: 'truck' },
  { title: 'Avtobus', value: 'bus' },
  { title: 'Maxsus texnika', value: 'special' },
]

// Filter vehicles/drivers by selected org
const filteredVehicles = computed(() => {
  if (!form.value.organization_id) return vehicles.value
  return vehicles.value.filter(v => v.organization_id === form.value.organization_id)
})

const filteredDrivers = computed(() => {
  if (!form.value.organization_id) return drivers.value
  return drivers.value.filter(d => d.organization_id === form.value.organization_id)
})

// Reset vehicle/driver when org changes
watch(() => form.value.organization_id, () => {
  form.value.vehicle_id = null
  form.value.driver_id = null
})

const selectedVehicle = computed(() =>
  vehicles.value.find(v => v.id === form.value.vehicle_id)
)

const estimatedFuel = computed(() => {
  const km = parseFloat(form.value.planned_km) || 0
  const norm = form.value.has_ac
    ? (selectedVehicle.value?.fuel_norm_ac || selectedVehicle.value?.fuel_norm)
    : selectedVehicle.value?.fuel_norm
  return km > 0 && norm ? ((km * norm) / 100).toFixed(1) : null
})

onMounted(async () => {
  const [vRes, dRes, oRes] = await Promise.all([
    vehicleApi.list(),
    driverApi.list(),
    organizationApi.list(),
  ])
  vehicles.value = vRes.data.filter(v => v.status === 'active')
  drivers.value = dRes.data.filter(d => d.status === 'active')
  organizations.value = oRes.data
})

function validate() {
  const e = {}
  if (isHq.value && !form.value.organization_id) e.organization_id = 'Tashkilotni tanlang'
  if (!form.value.vehicle_id)     e.vehicle_id   = 'Avtomobilni tanlang'
  if (!form.value.driver_id)      e.driver_id    = 'Haydovchini tanlang'
  if (!form.value.destination?.trim()) e.destination = "Yo'nalishni kiriting"
  if (!form.value.created_date)   e.created_date = 'Sanani kiriting'
  if (!form.value.valid_until)    e.valid_until  = 'Muddatni kiriting'
  errors.value = e
  return Object.keys(e).length === 0
}

async function create() {
  if (!validate()) return
  saving.value = true
  serverError.value = ''
  try {
    const payload = { ...form.value }
    if (!payload.planned_km) delete payload.planned_km
    if (!payload.fuel_issued) delete payload.fuel_issued
    if (!payload.tabel_number) delete payload.tabel_number
    if (!payload.destination_organization) delete payload.destination_organization

    await waybillApi.create(payload)
    router.push('/waybills')
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, v[0]])
      )
      serverError.value = "Formda xatolar mavjud, tekshiring"
    } else {
      serverError.value = data?.message || 'Xatolik yuz berdi'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="page-wrap">
    <!-- Page header -->
    <div class="page-header">
      <button class="back-btn" @click="router.back()">
        <v-icon size="18">mdi-arrow-left</v-icon>
        Orqaga
      </button>
      <div>
        <h1 class="page-title">Yangi yo'l varaqasi</h1>
        <p class="page-sub">Barcha maydonlarni to'ldiring</p>
      </div>
    </div>

    <!-- Server error -->
    <div v-if="serverError" class="server-error">
      <v-icon size="18" color="#DC2626">mdi-alert-circle</v-icon>
      {{ serverError }}
    </div>

    <div class="form-grid">

      <!-- LEFT: Main form -->
      <div class="form-main">

        <!-- Block 1: Transport -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="form-card-icon" style="background:#DBEAFE;">
              <v-icon color="#2563EB" size="18">mdi-car</v-icon>
            </div>
            Transport ma'lumotlari
          </div>

          <!-- Organization (hq only) -->
          <div v-if="isHq" class="field-row">
            <div class="field-wrap" :class="{ error: errors.organization_id }">
              <label>Tashkilot <span class="req">*</span></label>
              <v-select
                v-model="form.organization_id"
                :items="organizations"
                item-title="name"
                item-value="id"
                placeholder="Tashkilotni tanlang"
                prepend-inner-icon="mdi-domain"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
              <span v-if="errors.organization_id" class="err-msg">{{ errors.organization_id }}</span>
            </div>
          </div>

          <div class="field-row two-col">
            <!-- Vehicle -->
            <div class="field-wrap" :class="{ error: errors.vehicle_id }">
              <label>Avtomobil <span class="req">*</span></label>
              <v-select
                v-model="form.vehicle_id"
                :items="filteredVehicles"
                item-value="id"
                placeholder="Davlat raqamini tanlang"
                prepend-inner-icon="mdi-car"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
                :disabled="isHq && !form.organization_id"
              >
                <template #selection="{ item }">
                  <span>{{ item.raw.state_number }} — {{ item.raw.model }}</span>
                </template>
                <template #item="{ props, item }">
                  <v-list-item v-bind="props" :title="item.raw.state_number" :subtitle="`${item.raw.model} · ${item.raw.fuel_type}`" />
                </template>
              </v-select>
              <span v-if="errors.vehicle_id" class="err-msg">{{ errors.vehicle_id }}</span>
            </div>

            <!-- Driver -->
            <div class="field-wrap" :class="{ error: errors.driver_id }">
              <label>Haydovchi <span class="req">*</span></label>
              <v-select
                v-model="form.driver_id"
                :items="filteredDrivers"
                item-value="id"
                placeholder="Haydovchini tanlang"
                prepend-inner-icon="mdi-account-tie"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
                :disabled="isHq && !form.organization_id"
              >
                <template #selection="{ item }">
                  <span>{{ item.raw.full_name }}</span>
                </template>
                <template #item="{ props, item }">
                  <v-list-item v-bind="props" :title="item.raw.full_name" :subtitle="`Kat: ${item.raw.license_category} · ${item.raw.work_experience} yil staj`" />
                </template>
              </v-select>
              <span v-if="errors.driver_id" class="err-msg">{{ errors.driver_id }}</span>
            </div>
          </div>

          <div class="field-row two-col">
            <!-- Vehicle type -->
            <div class="field-wrap">
              <label>Transport turi</label>
              <v-select
                v-model="form.vehicle_type"
                :items="vehicleTypes"
                item-title="title"
                item-value="value"
                prepend-inner-icon="mdi-car-settings"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
            </div>

            <!-- Tabel -->
            <div class="field-wrap">
              <label>Tabel raqami</label>
              <v-text-field
                v-model="form.tabel_number"
                placeholder="Ixtiyoriy"
                prepend-inner-icon="mdi-identifier"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
            </div>
          </div>

          <!-- AC switch -->
          <div class="field-row">
            <div class="switch-row">
              <div>
                <div class="switch-label">Konditsioner bor</div>
                <div class="switch-sub">Yoqilg'i me'yoriga AC sarfi qo'shiladi</div>
              </div>
              <v-switch v-model="form.has_ac" color="primary" inset hide-details density="compact" />
            </div>
          </div>
        </div>

        <!-- Block 2: Route -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="form-card-icon" style="background:#D1FAE5;">
              <v-icon color="#059669" size="18">mdi-map-marker-path</v-icon>
            </div>
            Yo'nalish
          </div>

          <div class="field-row two-col">
            <div class="field-wrap" :class="{ error: errors.destination }">
              <label>Manzil / Yo'nalish <span class="req">*</span></label>
              <v-text-field
                v-model="form.destination"
                placeholder="Masalan: Toshkent → Urganch"
                prepend-inner-icon="mdi-map-marker"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
              <span v-if="errors.destination" class="err-msg">{{ errors.destination }}</span>
            </div>

            <div class="field-wrap">
              <label>Qaysi tashkilotga</label>
              <v-select
                v-model="form.destination_organization"
                :items="organizations"
                item-title="name"
                item-value="name"
                placeholder="Tashkilotni tanlang"
                prepend-inner-icon="mdi-office-building"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
                clearable
              />
            </div>
          </div>

          <div class="field-row two-col">
            <div class="field-wrap">
              <label>Rejalashtirilgan masofa (km)</label>
              <v-text-field
                v-model="form.planned_km"
                type="number"
                placeholder="0"
                prepend-inner-icon="mdi-road-variant"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
                min="0"
              />
            </div>

            <div class="field-wrap">
              <label>Safarlar soni</label>
              <v-text-field
                v-model="form.trip_count"
                type="number"
                placeholder="1"
                prepend-inner-icon="mdi-repeat"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
                min="1"
              />
            </div>
          </div>
        </div>

        <!-- Block 3: Dates & Fuel -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="form-card-icon" style="background:#FEF3C7;">
              <v-icon color="#D97706" size="18">mdi-calendar-clock</v-icon>
            </div>
            Sana va yoqilg'i
          </div>

          <div class="field-row two-col">
            <div class="field-wrap" :class="{ error: errors.created_date }">
              <label>Varaq sanasi <span class="req">*</span></label>
              <v-text-field
                v-model="form.created_date"
                type="date"
                prepend-inner-icon="mdi-calendar"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
              <span v-if="errors.created_date" class="err-msg">{{ errors.created_date }}</span>
            </div>

            <div class="field-wrap" :class="{ error: errors.valid_until }">
              <label>Amal qilish muddati <span class="req">*</span></label>
              <v-text-field
                v-model="form.valid_until"
                type="date"
                prepend-inner-icon="mdi-calendar-end"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
              <span v-if="errors.valid_until" class="err-msg">{{ errors.valid_until }}</span>
            </div>
          </div>

          <div class="field-row two-col">
            <div class="field-wrap">
              <label>Beriladigan yoqilg'i (L)</label>
              <v-text-field
                v-model="form.fuel_issued"
                type="number"
                :placeholder="estimatedFuel ? `Tavsiya: ${estimatedFuel} L` : '0'"
                prepend-inner-icon="mdi-gas-station"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
                min="0"
              />
            </div>

            <div class="field-wrap">
              <label>Izoh</label>
              <v-text-field
                v-model="form.notes"
                placeholder="Ixtiyoriy"
                prepend-inner-icon="mdi-note-text"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details
              />
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Summary -->
      <div class="form-side">
        <div class="summary-card">
          <div class="summary-title">Xulosa</div>

          <div class="summary-section">
            <div class="summary-row">
              <span class="summary-key">Transport</span>
              <span class="summary-val">{{ selectedVehicle?.state_number || '—' }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Model</span>
              <span class="summary-val">{{ selectedVehicle?.model || '—' }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Haydovchi</span>
              <span class="summary-val">{{ drivers.find(d => d.id === form.driver_id)?.full_name || '—' }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Yo'nalish</span>
              <span class="summary-val">{{ form.destination || '—' }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-key">Masofa</span>
              <span class="summary-val">{{ form.planned_km ? `${form.planned_km} km` : '—' }}</span>
            </div>
          </div>

          <!-- Estimated fuel -->
          <div v-if="estimatedFuel" class="fuel-estimate">
            <div class="fuel-est-label">Taxminiy yoqilg'i sarfi</div>
            <div class="fuel-est-value">{{ estimatedFuel }} <span>litr</span></div>
            <div class="fuel-est-sub">
              {{ form.has_ac ? 'AC bilan' : 'AC siz' }} ·
              {{ selectedVehicle?.fuel_norm }} L/100km
            </div>
          </div>

          <button
            class="create-btn"
            :disabled="saving"
            @click="create"
          >
            <v-progress-circular v-if="saving" size="18" width="2" indeterminate color="white" />
            <template v-else>
              <v-icon size="18">mdi-check</v-icon>
              Varaq yaratish
            </template>
          </button>

          <button class="cancel-btn" @click="router.back()">
            Bekor qilish
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-wrap {
  padding: 24px;
  max-width: 1100px;
  margin: 0 auto;
  font-family: 'Inter', system-ui, sans-serif;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
}

.back-btn {
  display: flex; align-items: center; gap: 6px;
  background: white; border: 1px solid rgba(0,0,0,0.1);
  padding: 8px 14px; border-radius: 10px;
  font-size: 13px; font-weight: 500; color: #374151;
  cursor: pointer; transition: background 0.15s; flex-shrink: 0;
}
.back-btn:hover { background: #F8FAFC; }

.page-title { font-size: 22px; font-weight: 800; color: #0F172A; margin: 0; }
.page-sub   { font-size: 13px; color: #94A3B8; margin: 2px 0 0; }

.server-error {
  display: flex; align-items: center; gap: 8px;
  background: #FEF2F2; border: 1px solid #FECACA;
  color: #DC2626; font-size: 13px; font-weight: 500;
  padding: 12px 16px; border-radius: 12px;
  margin-bottom: 20px;
}

/* Layout */
.form-grid {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.form-main { flex: 1; display: flex; flex-direction: column; gap: 16px; }
.form-side  { width: 280px; flex-shrink: 0; position: sticky; top: 80px; }

@media (max-width: 860px) {
  .form-grid { flex-direction: column; }
  .form-side { width: 100%; position: static; }
}

/* Form card */
.form-card {
  background: white;
  border: 1px solid rgba(0,0,0,0.07);
  border-radius: 16px;
  overflow: hidden;
}

.form-card-title {
  display: flex; align-items: center; gap: 10px;
  padding: 16px 20px;
  font-size: 14px; font-weight: 700; color: #0F172A;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  background: #FAFAFA;
}

.form-card-icon {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
}

/* Fields */
.field-row { padding: 12px 20px; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 600px) { .two-col { grid-template-columns: 1fr; } }

.field-wrap { display: flex; flex-direction: column; gap: 5px; }
.field-wrap label {
  font-size: 12px; font-weight: 600; color: #374151; letter-spacing: 0.1px;
}
.field-wrap.error :deep(.v-field) { --v-field-border-color: #EF4444 !important; }

.req { color: #EF4444; }
.err-msg { font-size: 11px; color: #EF4444; margin-top: 2px; }

/* Switch row */
.switch-row {
  display: flex; align-items: center; justify-content: space-between;
  background: #F8FAFC; border: 1px solid rgba(0,0,0,0.07);
  border-radius: 12px; padding: 12px 16px;
}
.switch-label { font-size: 13px; font-weight: 600; color: #0F172A; }
.switch-sub   { font-size: 11px; color: #94A3B8; margin-top: 2px; }

/* Summary card */
.summary-card {
  background: white;
  border: 1px solid rgba(0,0,0,0.07);
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.summary-title {
  font-size: 14px; font-weight: 700; color: #0F172A;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(0,0,0,0.07);
}

.summary-section { display: flex; flex-direction: column; gap: 8px; }
.summary-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.summary-key { font-size: 12px; color: #94A3B8; flex-shrink: 0; }
.summary-val { font-size: 12px; font-weight: 600; color: #0F172A; text-align: right; }

/* Fuel estimate */
.fuel-estimate {
  background: linear-gradient(135deg, #EFF6FF, #F5F3FF);
  border: 1px solid rgba(37,99,235,0.12);
  border-radius: 12px; padding: 14px;
  text-align: center;
}
.fuel-est-label { font-size: 11px; color: #7C3AED; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.fuel-est-value { font-size: 32px; font-weight: 800; color: #2563EB; line-height: 1; }
.fuel-est-value span { font-size: 14px; font-weight: 500; color: #64748B; }
.fuel-est-sub { font-size: 11px; color: #94A3B8; margin-top: 4px; }

/* Buttons */
.create-btn {
  width: 100%; padding: 13px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: white; font-size: 14px; font-weight: 700;
  border: none; border-radius: 12px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: opacity 0.15s, transform 0.15s;
  box-shadow: 0 4px 14px rgba(37,99,235,0.3);
}
.create-btn:hover:not(:disabled) { opacity: 0.92; transform: translateY(-1px); }
.create-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.cancel-btn {
  width: 100%; padding: 11px;
  background: none; border: 1px solid rgba(0,0,0,0.1);
  color: #64748B; font-size: 13px; font-weight: 500;
  border-radius: 12px; cursor: pointer; transition: background 0.15s;
}
.cancel-btn:hover { background: #F8FAFC; }
</style>
