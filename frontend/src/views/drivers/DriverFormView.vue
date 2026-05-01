<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { driverApi, organizationApi, vehicleApi } from '@/services/api'

const router = useRouter()
const route = useRoute()
const isEdit = !!route.params.id
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const serverError = ref('')
const organizations = ref([])
const vehicles = ref([])

const form = ref({
  organization_id: null,
  full_name: '',
  license_number: '',
  license_category: 'B',
  vehicle_id: null,
  work_experience: 0,
  pinfl: '',
  phone: '',
  license_expiry: '',
  employment_type: 'staff',
  status: 'active',
  notes: '',
})

const employmentTypes = [
  { title: 'Shtat', value: 'staff' },
  { title: 'Shartnoma', value: 'contract' },
  { title: 'Yarim stavka', value: 'part_time' },
]

const statusItems = [
  { title: 'Faol', value: 'active' },
  { title: 'Faol emas', value: 'inactive' },
  { title: 'Kasal', value: 'sick_leave' },
  { title: 'Ta\'til', value: 'vacation' },
]

const statusColors = { active: '#059669', inactive: '#94A3B8', sick_leave: '#DC2626', vacation: '#D97706' }

const categories = ['A', 'B', 'C', 'D', 'E', 'BC', 'CD']

function validate() {
  const e = {}
  if (!form.value.organization_id) e.organization_id = 'Tashkilotni tanlang'
  if (!form.value.full_name?.trim()) e.full_name = 'F.I.Sh ni kiriting'
  if (!form.value.license_number?.trim()) e.license_number = 'Guvohnoma raqamini kiriting'
  if (!form.value.pinfl?.trim()) e.pinfl = 'PINFL ni kiriting'
  else if (form.value.pinfl.length !== 14) e.pinfl = 'PINFL 14 ta raqam bo\'lishi kerak'
  errors.value = e
  return Object.keys(e).length === 0
}

async function load() {
  const [orgsRes, vehRes] = await Promise.all([organizationApi.list(), vehicleApi.list()])
  organizations.value = orgsRes.data
  vehicles.value = vehRes.data.filter(v => v.status === 'active')
  if (isEdit) {
    loading.value = true
    try {
      const res = await driverApi.show(route.params.id)
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
    if (isEdit) await driverApi.update(route.params.id, form.value)
    else await driverApi.create(form.value)
    router.push('/drivers')
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
        <h1 class="page-title">{{ isEdit ? 'Haydovchini tahrirlash' : "Yangi haydovchi qo'shish" }}</h1>
        <p class="page-sub">{{ isEdit ? "Ma'lumotlarni yangilang" : "Haydovchini ro'yxatdan o'tkazing" }}</p>
      </div>
    </div>

    <div v-if="serverError" class="server-error">
      <v-icon size="18" color="#DC2626">mdi-alert-circle</v-icon> {{ serverError }}
    </div>

    <v-skeleton-loader v-if="loading" type="article" rounded="xl" />

    <div v-else class="form-grid">
      <div class="form-main">

        <!-- Shaxsiy -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="icon-box" style="background:#EDE9FE;"><v-icon color="#7C3AED" size="18">mdi-account-tie</v-icon></div>
            Shaxsiy ma'lumotlar
          </div>
          <div class="fields-body">
            <div class="field-row two-col">
              <div class="field-item" :class="{ 'has-error': errors.organization_id }">
                <label>Tashkilot <span class="req">*</span></label>
                <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id"
                  placeholder="Tashkilotni tanlang" prepend-inner-icon="mdi-domain"
                  variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.organization_id" class="err">{{ errors.organization_id }}</span>
              </div>
              <div class="field-item" :class="{ 'has-error': errors.full_name }">
                <label>F.I.Sh (to'liq ism) <span class="req">*</span></label>
                <v-text-field v-model="form.full_name" placeholder="Karimov Jasur Baxtiyorovich"
                  prepend-inner-icon="mdi-account" variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.full_name" class="err">{{ errors.full_name }}</span>
              </div>
            </div>

            <div class="field-row two-col">
              <div class="field-item" :class="{ 'has-error': errors.pinfl }">
                <label>JSHSHIR (PINFL) <span class="req">*</span></label>
                <v-text-field v-model="form.pinfl" placeholder="14 ta raqam" maxlength="14"
                  prepend-inner-icon="mdi-card-account-details" variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.pinfl" class="err">{{ errors.pinfl }}</span>
              </div>
              <div class="field-item">
                <label>Telefon raqami</label>
                <v-text-field v-model="form.phone" placeholder="+998 90 123 45 67"
                  prepend-inner-icon="mdi-phone" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
            </div>
          </div>
        </div>

        <!-- Guvohnoma -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="icon-box" style="background:#DBEAFE;"><v-icon color="#2563EB" size="18">mdi-card-text</v-icon></div>
            Haydovchilik guvohnomasi
          </div>
          <div class="fields-body">
            <div class="field-row three-col">
              <div class="field-item" :class="{ 'has-error': errors.license_number }">
                <label>Guvohnoma raqami <span class="req">*</span></label>
                <v-text-field v-model="form.license_number" placeholder="AA 1234567"
                  prepend-inner-icon="mdi-identifier" variant="outlined" density="comfortable" rounded="lg" hide-details />
                <span v-if="errors.license_number" class="err">{{ errors.license_number }}</span>
              </div>
              <div class="field-item">
                <label>Kategoriya</label>
                <v-select v-model="form.license_category" :items="categories"
                  prepend-inner-icon="mdi-steering" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Muddati tugash sanasi</label>
                <v-text-field v-model="form.license_expiry" type="date"
                  prepend-inner-icon="mdi-calendar" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
            </div>
          </div>
        </div>

        <!-- Ish ma'lumotlari -->
        <div class="form-card">
          <div class="form-card-title">
            <div class="icon-box" style="background:#D1FAE5;"><v-icon color="#059669" size="18">mdi-briefcase</v-icon></div>
            Ish ma'lumotlari
          </div>
          <div class="fields-body">
            <div class="field-row three-col">
              <div class="field-item">
                <label>Mehnat staji (yil)</label>
                <v-text-field v-model="form.work_experience" type="number" placeholder="0" min="0"
                  prepend-inner-icon="mdi-clock-time-eight" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Bandlik turi</label>
                <v-select v-model="form.employment_type" :items="employmentTypes" item-title="title" item-value="value"
                  prepend-inner-icon="mdi-briefcase-outline" variant="outlined" density="comfortable" rounded="lg" hide-details />
              </div>
              <div class="field-item">
                <label>Biriktirilgan avtomobil</label>
                <v-select v-model="form.vehicle_id" :items="vehicles" item-title="state_number" item-value="id"
                  placeholder="Ixtiyoriy" prepend-inner-icon="mdi-car"
                  variant="outlined" density="comfortable" rounded="lg" hide-details clearable />
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
            <div class="srow"><span>Ism</span><span>{{ form.full_name || '—' }}</span></div>
            <div class="srow"><span>Kategoriya</span><span>{{ form.license_category }}</span></div>
            <div class="srow"><span>Staj</span><span>{{ form.work_experience }} yil</span></div>
            <div class="srow"><span>Tashkilot</span><span>{{ organizations.find(o => o.id === form.organization_id)?.short_name || '—' }}</span></div>
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
.field-row {}
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
.divider { height: 1px; background: rgba(0,0,0,0.07); }
.summary-info { display: flex; flex-direction: column; gap: 8px; }
.srow { display: flex; justify-content: space-between; font-size: 12px; }
.srow span:first-child { color: #94A3B8; }
.srow span:last-child { font-weight: 600; color: #0F172A; text-align: right; }
.submit-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #2563EB, #7C3AED); color: white; font-size: 14px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(37,99,235,0.3); transition: opacity 0.15s; }
.submit-btn:hover:not(:disabled) { opacity: 0.9; }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.cancel-btn { width: 100%; padding: 10px; background: none; border: 1px solid rgba(0,0,0,0.1); color: #64748B; font-size: 13px; font-weight: 500; border-radius: 12px; cursor: pointer; transition: background 0.15s; }
.cancel-btn:hover { background: #F8FAFC; }
</style>
