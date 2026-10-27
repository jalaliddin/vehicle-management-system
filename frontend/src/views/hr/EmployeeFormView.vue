<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { departmentApi, employeeApi, organizationApi, positionApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { employmentTypes, errorMessage, genders, today } from '@/utils/hr'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const isEdit = computed(() => !!route.params.id)
const saving = ref(false)
const errors = ref({})
const failure = ref('')

const organizations = ref([])
const departments = ref([])
const positions = ref([])

const form = ref({
  organization_id: null, last_name: '', first_name: '', middle_name: '', pinfl: '', passport: '',
  birth_date: null, gender: null, phone: '', address: '', education: '', personnel_number: '',
  employment_type: 'staff', clothing_size: '', shoe_size: '', annual_leave_days: 21, notes: '',
})
const hire = ref({ effective_date: today(), order_number: '', order_date: today(), department_id: null, position_id: null })

const options = (map) => Object.entries(map).map(([value, title]) => ({ value, title }))
const err = (field) => errors.value[field] || []

watch(() => form.value.organization_id, async (id) => {
  if (isEdit.value) return
  hire.value.department_id = null
  departments.value = id ? (await departmentApi.list({ organization_id: id })).data : []
})

async function save() {
  saving.value = true
  errors.value = {}
  failure.value = ''
  try {
    const card = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]))
    const res = isEdit.value
      ? await employeeApi.update(route.params.id, card)
      : await employeeApi.create({ ...card, hire: hire.value })
    router.push(`/employees/${res.data.id}`)
  } catch (e) {
    errors.value = e.response?.data?.errors || {}
    failure.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [orgs, pos] = await Promise.all([organizationApi.list(), positionApi.list()])
  organizations.value = auth.assignableOrganizations(orgs.data)
  positions.value = pos.data

  if (isEdit.value) {
    const employee = (await employeeApi.show(route.params.id)).data
    Object.keys(form.value).forEach((key) => { form.value[key] = employee[key] ?? form.value[key] })
  } else {
    form.value.organization_id = auth.user?.organization_id ?? organizations.value[0]?.id ?? null
  }
})
</script>

<template>
  <v-container fluid class="pa-6" style="max-width: 1100px;">
    <v-row class="mb-4" align="center">
      <v-col cols="auto">
        <v-btn icon variant="text" @click="router.back()"><v-icon>mdi-arrow-left</v-icon></v-btn>
      </v-col>
      <v-col>
        <h2 class="text-h6 font-weight-bold">{{ isEdit ? 'Xodim kartochkasini tahrirlash' : 'Yangi xodim' }}</h2>
      </v-col>
    </v-row>

    <v-alert v-if="failure" type="error" variant="tonal" rounded="lg" class="mb-4" density="compact">{{ failure }}</v-alert>

    <v-card rounded="xl" class="border mb-4">
      <v-card-title class="pa-5 pb-0 text-subtitle-1 font-weight-bold">Shaxsiy ma'lumotlar</v-card-title>
      <v-card-text class="pa-5">
        <v-row dense>
          <v-col cols="12" md="4"><v-text-field v-model="form.last_name" label="Familiya *" variant="outlined" rounded="lg" :error-messages="err('last_name')" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.first_name" label="Ism *" variant="outlined" rounded="lg" :error-messages="err('first_name')" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.middle_name" label="Otasining ismi" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.pinfl" label="JShShIR *" maxlength="14" counter variant="outlined" rounded="lg" :error-messages="err('pinfl')" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.passport" label="Pasport (seriya va raqam)" variant="outlined" rounded="lg" :error-messages="err('passport')" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.birth_date" type="date" label="Tug'ilgan sana" variant="outlined" rounded="lg" :error-messages="err('birth_date')" /></v-col>
          <v-col cols="12" md="4"><v-select v-model="form.gender" :items="options(genders)" label="Jinsi" variant="outlined" rounded="lg" clearable /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.phone" label="Telefon" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="form.education" label="Ma'lumoti" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12"><v-text-field v-model="form.address" label="Manzil" variant="outlined" rounded="lg" /></v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card rounded="xl" class="border mb-4">
      <v-card-title class="pa-5 pb-0 text-subtitle-1 font-weight-bold">Ish ma'lumotlari</v-card-title>
      <v-card-text class="pa-5">
        <v-row dense>
          <v-col v-if="!isEdit" cols="12" md="6">
            <v-select v-model="form.organization_id" :items="organizations" item-title="name" item-value="id" label="Tashkilot *" variant="outlined" rounded="lg" :error-messages="err('organization_id')" />
          </v-col>
          <v-col cols="12" md="3"><v-text-field v-model="form.personnel_number" label="Tabel raqami" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12" md="3"><v-select v-model="form.employment_type" :items="options(employmentTypes)" label="Bandlik turi" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12" md="3"><v-text-field v-model.number="form.annual_leave_days" type="number" label="Yillik ta'til (kun)" variant="outlined" rounded="lg" :error-messages="err('annual_leave_days')" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model="form.clothing_size" label="Kiyim o'lchami" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="6" md="3"><v-text-field v-model="form.shoe_size" label="Poyabzal o'lchami" variant="outlined" rounded="lg" /></v-col>
          <v-col cols="12"><v-textarea v-model="form.notes" label="Izoh" rows="2" variant="outlined" rounded="lg" /></v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card v-if="!isEdit" rounded="xl" class="border mb-4">
      <v-card-title class="pa-5 pb-0 text-subtitle-1 font-weight-bold">Ishga qabul buyrug'i</v-card-title>
      <v-card-text class="pa-5">
        <v-row dense>
          <v-col cols="12" md="4"><v-text-field v-model="hire.effective_date" type="date" label="Qabul sanasi *" variant="outlined" rounded="lg" :error-messages="err('hire.effective_date')" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="hire.order_number" label="Buyruq raqami *" variant="outlined" rounded="lg" :error-messages="err('hire.order_number')" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="hire.order_date" type="date" label="Buyruq sanasi *" variant="outlined" rounded="lg" :error-messages="err('hire.order_date')" /></v-col>
          <v-col cols="12" md="6">
            <v-select v-model="hire.department_id" :items="departments" item-title="name" item-value="id" label="Bo'lim" variant="outlined" rounded="lg" clearable :error-messages="err('department_id')" />
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete v-model="hire.position_id" :items="positions" item-title="name" item-value="id" label="Lavozim *" variant="outlined" rounded="lg" :error-messages="err('hire.position_id')" />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div class="d-flex">
      <v-spacer />
      <v-btn variant="text" class="mr-2" @click="router.back()">Bekor</v-btn>
      <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
    </div>
  </v-container>
</template>
