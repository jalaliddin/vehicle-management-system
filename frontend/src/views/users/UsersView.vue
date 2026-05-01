<script setup>
import { ref, computed, onMounted } from 'vue'
import { userApi, organizationApi } from '@/services/api'

const users = ref([])
const organizations = ref([])
const loading = ref(true)
const saving = ref(false)
const search = ref('')
const filterOrg = ref('')
const filterRole = ref('')
const showForm = ref(false)
const editingUser = ref(null)
const deleteDialog = ref(false)
const deletingUser = ref(null)
const errors = ref({})

const ROLES = [
  { value: 'hq_dispatcher', label: 'Markaziy dispetcher', color: '#7C3AED', bg: '#F5F3FF', icon: 'mdi-office-building' },
  { value: 'dispatcher',    label: 'Dispetcher',           color: '#2563EB', bg: '#EFF6FF', icon: 'mdi-headset' },
  { value: 'mechanic',      label: 'Mexanik',              color: '#D97706', bg: '#FFFBEB', icon: 'mdi-wrench' },
  { value: 'doctor',        label: 'Shifokor',             color: '#059669', bg: '#F0FDF4', icon: 'mdi-stethoscope' },
  { value: 'viewer',        label: "Ko'ruvchi",            color: '#64748B', bg: '#F8FAFC', icon: 'mdi-eye' },
]

const emptyForm = () => ({
  name: '', email: '', password: '',
  role: 'dispatcher', organization_id: '', is_active: true,
})
const form = ref(emptyForm())

onMounted(async () => {
  const [u, o] = await Promise.all([userApi.list(), organizationApi.list()])
  users.value = u.data
  organizations.value = o.data
  loading.value = false
})

async function reload() {
  const res = await userApi.list()
  users.value = res.data
}

function roleMeta(role) {
  return ROLES.find(r => r.value === role) || { label: role, color: '#64748B', bg: '#F8FAFC', icon: 'mdi-account' }
}
function orgName(id) {
  return organizations.value.find(o => o.id === id)?.short_name || '—'
}

const filtered = computed(() => {
  let list = users.value
  if (filterOrg.value) list = list.filter(u => u.organization_id == filterOrg.value)
  if (filterRole.value) list = list.filter(u => u.role === filterRole.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(u =>
      u.name?.toLowerCase().includes(q) ||
      u.email?.toLowerCase().includes(q) ||
      u.organization?.short_name?.toLowerCase().includes(q)
    )
  }
  return list
})

// Group by organization for display
const byOrg = computed(() => {
  const map = {}
  filtered.value.forEach(u => {
    const key = u.organization_id || 0
    const label = u.organization?.name || 'Tashkilotsiz'
    if (!map[key]) map[key] = { label, items: [] }
    map[key].items.push(u)
  })
  return Object.values(map)
})

function openCreate() {
  editingUser.value = null
  form.value = emptyForm()
  errors.value = {}
  showForm.value = true
}

function openEdit(user) {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    organization_id: user.organization_id || '',
    is_active: user.is_active,
  }
  errors.value = {}
  showForm.value = true
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    const payload = { ...form.value }
    if (!payload.organization_id) delete payload.organization_id
    if (!payload.password) delete payload.password

    if (editingUser.value) {
      await userApi.update(editingUser.value.id, payload)
    } else {
      await userApi.create(payload)
    }
    showForm.value = false
    await reload()
  } catch (e) {
    if (e.response?.data?.errors) {
      Object.entries(e.response.data.errors).forEach(([k, v]) => {
        errors.value[k] = v[0]
      })
    }
  } finally {
    saving.value = false
  }
}

async function toggleActive(user) {
  await userApi.toggleActive(user.id)
  await reload()
}

function confirmDelete(user) {
  deletingUser.value = user
  deleteDialog.value = true
}

async function deleteUser() {
  await userApi.delete(deletingUser.value.id)
  deleteDialog.value = false
  await reload()
}
</script>

<template>
  <div class="page">

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Foydalanuvchilar</h1>
        <p class="page-sub">Tizim foydalanuvchilari va ruxsatlar boshqaruvi</p>
      </div>
      <button class="create-btn" @click="openCreate">
        <v-icon size="16" color="white">mdi-plus</v-icon>
        Yangi foydalanuvchi
      </button>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-wrap">
        <v-icon size="15" color="#94A3B8">mdi-magnify</v-icon>
        <input v-model="search" placeholder="Ism yoki email..." class="search-input" />
      </div>
      <div class="filter-wrap">
        <v-icon size="15" color="#94A3B8">mdi-office-building</v-icon>
        <select v-model="filterOrg" class="filter-select">
          <option value="">Barcha tashkilotlar</option>
          <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.short_name }}</option>
        </select>
      </div>
      <div class="filter-wrap">
        <v-icon size="15" color="#94A3B8">mdi-account-group</v-icon>
        <select v-model="filterRole" class="filter-select">
          <option value="">Barcha lavozimlar</option>
          <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
      </div>

      <!-- Stats chips -->
      <div class="stats-row">
        <div class="stat-chip">
          <span class="stat-n">{{ users.length }}</span>
          <span class="stat-l">Jami</span>
        </div>
        <div class="stat-chip stat-chip-green">
          <span class="stat-n">{{ users.filter(u => u.is_active).length }}</span>
          <span class="stat-l">Aktiv</span>
        </div>
        <div class="stat-chip stat-chip-red">
          <span class="stat-n">{{ users.filter(u => !u.is_active).length }}</span>
          <span class="stat-l">Bloklangan</span>
        </div>
      </div>
    </div>

    <!-- Skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 6" :key="i" class="skeleton-row" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="empty-state">
      <v-icon size="48" color="#CBD5E1">mdi-account-off</v-icon>
      <p>Foydalanuvchi topilmadi</p>
    </div>

    <!-- Users grouped by org -->
    <div v-else class="groups">
      <div v-for="group in byOrg" :key="group.label" class="org-group">
        <div class="org-label">
          <v-icon size="14" color="#64748B">mdi-office-building-outline</v-icon>
          {{ group.label }}
          <span class="org-count">{{ group.items.length }}</span>
        </div>

        <div class="users-grid">
          <div
            v-for="user in group.items"
            :key="user.id"
            class="user-card"
            :class="{ 'card-inactive': !user.is_active }"
          >
            <!-- Avatar + info -->
            <div class="card-top">
              <div class="user-avatar" :style="`background: linear-gradient(135deg, ${roleMeta(user.role).color}22, ${roleMeta(user.role).color}44)`">
                <v-icon size="22" :color="roleMeta(user.role).color">{{ roleMeta(user.role).icon }}</v-icon>
              </div>
              <div class="user-info">
                <div class="user-name">{{ user.name }}</div>
                <div class="user-email">{{ user.email }}</div>
              </div>
              <div class="status-dot-wrap">
                <div class="status-dot" :class="user.is_active ? 'dot-active' : 'dot-inactive'" />
              </div>
            </div>

            <!-- Role badge -->
            <div class="role-tag" :style="`background:${roleMeta(user.role).bg}; color:${roleMeta(user.role).color};`">
              <v-icon size="11" :color="roleMeta(user.role).color">{{ roleMeta(user.role).icon }}</v-icon>
              {{ roleMeta(user.role).label }}
            </div>

            <!-- Footer -->
            <div class="card-footer">
              <span class="card-date">{{ user.created_at }}</span>
              <div class="card-actions">
                <button class="icon-btn" title="Tahrirlash" @click="openEdit(user)">
                  <v-icon size="16" color="#64748B">mdi-pencil</v-icon>
                </button>
                <button
                  class="icon-btn"
                  :title="user.is_active ? 'Bloklash' : 'Faollashtirish'"
                  @click="toggleActive(user)"
                >
                  <v-icon size="16" :color="user.is_active ? '#F59E0B' : '#10B981'">
                    {{ user.is_active ? 'mdi-lock' : 'mdi-lock-open' }}
                  </v-icon>
                </button>
                <button class="icon-btn" title="O'chirish" @click="confirmDelete(user)">
                  <v-icon size="16" color="#EF4444">mdi-delete</v-icon>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Form Modal ===== -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal-box">
        <div class="modal-header">
          <div class="modal-icon">
            <v-icon color="#2563EB" size="20">{{ editingUser ? 'mdi-account-edit' : 'mdi-account-plus' }}</v-icon>
          </div>
          <div>
            <div class="modal-title">{{ editingUser ? 'Tahrirlash' : 'Yangi foydalanuvchi' }}</div>
            <div class="modal-sub">{{ editingUser ? editingUser.email : 'Yangi akkaunt yaratish' }}</div>
          </div>
          <button class="modal-close" @click="showForm = false">
            <v-icon size="18" color="#94A3B8">mdi-close</v-icon>
          </button>
        </div>

        <div class="modal-body">
          <!-- Name -->
          <div class="field">
            <label>Ism Familiya <span class="req">*</span></label>
            <div class="input-wrap" :class="{ 'input-error': errors.name }">
              <v-icon size="16" color="#94A3B8">mdi-account</v-icon>
              <input v-model="form.name" type="text" placeholder="Abdullayev Abdulla" />
            </div>
            <span v-if="errors.name" class="err-msg">{{ errors.name }}</span>
          </div>

          <!-- Email -->
          <div class="field">
            <label>Email <span class="req">*</span></label>
            <div class="input-wrap" :class="{ 'input-error': errors.email }">
              <v-icon size="16" color="#94A3B8">mdi-email</v-icon>
              <input v-model="form.email" type="email" placeholder="user@utg.uz" autocomplete="off" />
            </div>
            <span v-if="errors.email" class="err-msg">{{ errors.email }}</span>
          </div>

          <!-- Password -->
          <div class="field">
            <label>Parol {{ editingUser ? '(o\'zgartirmaslik uchun bo\'sh qoldiring)' : '*' }}</label>
            <div class="input-wrap" :class="{ 'input-error': errors.password }">
              <v-icon size="16" color="#94A3B8">mdi-lock</v-icon>
              <input v-model="form.password" type="password" placeholder="••••••" autocomplete="new-password" />
            </div>
            <span v-if="errors.password" class="err-msg">{{ errors.password }}</span>
          </div>

          <!-- Role -->
          <div class="field">
            <label>Lavozim <span class="req">*</span></label>
            <div class="role-picker">
              <button
                v-for="r in ROLES"
                :key="r.value"
                class="role-option"
                :class="{ 'role-selected': form.role === r.value }"
                :style="form.role === r.value ? `background:${r.bg}; border-color:${r.color}; color:${r.color};` : ''"
                @click="form.role = r.value"
                type="button"
              >
                <v-icon size="14" :color="form.role === r.value ? r.color : '#94A3B8'">{{ r.icon }}</v-icon>
                {{ r.label }}
              </button>
            </div>
          </div>

          <!-- Organization -->
          <div class="field">
            <label>Tashkilot</label>
            <div class="input-wrap">
              <v-icon size="16" color="#94A3B8">mdi-office-building</v-icon>
              <select v-model="form.organization_id">
                <option value="">— Tashkilotsiz —</option>
                <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
          </div>

          <!-- Active toggle -->
          <div class="field field-inline">
            <label>Aktiv holat</label>
            <button
              type="button"
              class="toggle-switch"
              :class="{ 'toggle-on': form.is_active }"
              @click="form.is_active = !form.is_active"
            >
              <div class="toggle-thumb" />
            </button>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="showForm = false">Bekor</button>
          <button class="btn-save" :disabled="saving" @click="save">
            <v-progress-circular v-if="saving" size="16" width="2" indeterminate color="white" />
            <span v-else>{{ editingUser ? 'Saqlash' : 'Yaratish' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ===== Delete Dialog ===== -->
    <div v-if="deleteDialog" class="modal-overlay" @click.self="deleteDialog = false">
      <div class="modal-box modal-sm">
        <div class="modal-header">
          <div class="modal-icon" style="background:#FEF2F2;">
            <v-icon color="#EF4444" size="20">mdi-delete-alert</v-icon>
          </div>
          <div>
            <div class="modal-title">O'chirishni tasdiqlang</div>
            <div class="modal-sub">{{ deletingUser?.name }}</div>
          </div>
          <button class="modal-close" @click="deleteDialog = false">
            <v-icon size="18" color="#94A3B8">mdi-close</v-icon>
          </button>
        </div>
        <div class="modal-body">
          <p class="delete-warn">
            <strong>{{ deletingUser?.name }}</strong> foydalanuvchisini o'chirsangiz, uning barcha tokenları ham o'chiriladi va tizimga kira olmaydi.
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="deleteDialog = false">Bekor</button>
          <button class="btn-delete" @click="deleteUser">O'chirish</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.page {
  padding: 24px;
  font-family: 'Inter', system-ui, sans-serif;
  min-height: 100%;
}

/* Header */
.page-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 16px; margin-bottom: 20px; flex-wrap: wrap;
}
.page-title { font-size: 22px; font-weight: 800; color: #0F172A; margin: 0; }
.page-sub   { font-size: 13px; color: #94A3B8; margin: 4px 0 0; }

.create-btn {
  display: flex; align-items: center; gap: 7px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: white; border: none; border-radius: 12px;
  padding: 10px 18px; font-size: 13px; font-weight: 700;
  cursor: pointer; font-family: inherit;
  box-shadow: 0 4px 14px rgba(37,99,235,0.3);
}
.create-btn:hover { opacity: 0.9; }

/* Filter bar */
.filter-bar {
  display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
  background: white; border: 1px solid rgba(0,0,0,0.08);
  border-radius: 14px; padding: 14px 16px; margin-bottom: 20px;
}
.search-wrap, .filter-wrap {
  display: flex; align-items: center; gap: 7px;
  border: 1.5px solid #E2E8F0; border-radius: 10px;
  padding: 8px 12px; background: #FAFAFA; flex: 1; min-width: 160px;
}
.search-wrap:focus-within, .filter-wrap:focus-within { border-color: #2563EB; background: white; }
.search-input, .filter-select {
  border: none; outline: none; background: none;
  font-size: 13px; color: #0F172A; width: 100%; font-family: inherit;
}
.filter-select { cursor: pointer; }
.stats-row { display: flex; gap: 8px; margin-left: auto; }
.stat-chip {
  display: flex; flex-direction: column; align-items: center;
  background: #F8FAFC; border: 1px solid rgba(0,0,0,0.07);
  border-radius: 10px; padding: 6px 14px;
}
.stat-chip-green { background: #F0FDF4; border-color: #BBF7D0; }
.stat-chip-red   { background: #FEF2F2; border-color: #FECACA; }
.stat-n { font-size: 18px; font-weight: 800; color: #0F172A; line-height: 1; }
.stat-l { font-size: 10px; color: #94A3B8; font-weight: 600; }
.stat-chip-green .stat-n { color: #10B981; }
.stat-chip-red   .stat-n { color: #EF4444; }

/* Skeleton */
.skeleton-list { display: flex; flex-direction: column; gap: 8px; }
.skeleton-row {
  height: 60px; background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%; border-radius: 12px;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* Empty */
.empty-state {
  text-align: center; padding: 60px 20px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.empty-state p { font-size: 14px; color: #94A3B8; margin: 0; }

/* Groups */
.groups { display: flex; flex-direction: column; gap: 20px; }
.org-group {}
.org-label {
  display: flex; align-items: center; gap: 7px;
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.6px; color: #64748B; margin-bottom: 10px;
  padding: 0 4px;
}
.org-count {
  background: #E2E8F0; color: #64748B; font-size: 10px; font-weight: 700;
  padding: 1px 6px; border-radius: 99px;
}

/* Users grid */
.users-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  gap: 12px;
}

/* User card */
.user-card {
  background: white;
  border: 1.5px solid rgba(0,0,0,0.07);
  border-radius: 16px;
  padding: 16px;
  display: flex; flex-direction: column; gap: 10px;
  transition: box-shadow 0.15s, transform 0.15s;
}
.user-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.07); transform: translateY(-1px); }
.card-inactive { opacity: 0.55; }

.card-top { display: flex; align-items: center; gap: 10px; }
.user-avatar {
  width: 42px; height: 42px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.user-info { flex: 1; min-width: 0; }
.user-name { font-size: 14px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-email { font-size: 12px; color: #94A3B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.status-dot-wrap { flex-shrink: 0; }
.status-dot {
  width: 9px; height: 9px; border-radius: 50%;
}
.dot-active   { background: #10B981; box-shadow: 0 0 0 3px rgba(16,185,129,0.2); }
.dot-inactive { background: #CBD5E1; }

.role-tag {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 700; padding: 4px 10px;
  border-radius: 8px; width: fit-content;
  text-transform: uppercase; letter-spacing: 0.3px;
}

.card-footer {
  display: flex; justify-content: space-between; align-items: center;
  padding-top: 8px; border-top: 1px solid rgba(0,0,0,0.05);
}
.card-date { font-size: 11px; color: #CBD5E1; }
.card-actions { display: flex; gap: 4px; }
.icon-btn {
  background: none; border: none; cursor: pointer;
  padding: 6px; border-radius: 8px; display: flex;
  transition: background 0.12s;
}
.icon-btn:hover { background: #F1F5F9; }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; padding: 16px;
}
.modal-box {
  background: white; border-radius: 20px; width: 100%; max-width: 500px;
  box-shadow: 0 24px 64px rgba(0,0,0,0.18); overflow: hidden;
  max-height: 90vh; display: flex; flex-direction: column;
}
.modal-sm { max-width: 400px; }
.modal-header {
  display: flex; align-items: center; gap: 12px;
  padding: 20px 20px 16px; flex-shrink: 0;
}
.modal-icon {
  width: 42px; height: 42px; border-radius: 12px;
  background: #EFF6FF;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-title { font-size: 16px; font-weight: 800; color: #0F172A; }
.modal-sub   { font-size: 12px; color: #94A3B8; margin-top: 1px; }
.modal-close {
  margin-left: auto; background: none; border: none; cursor: pointer;
  padding: 6px; border-radius: 8px; display: flex;
}
.modal-close:hover { background: #F1F5F9; }

.modal-body {
  padding: 4px 20px 20px; overflow-y: auto; flex: 1;
  display: flex; flex-direction: column; gap: 14px;
}

.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 12px; font-weight: 600; color: #374151; }
.req { color: #EF4444; }
.field-inline { flex-direction: row; align-items: center; justify-content: space-between; }

.input-wrap {
  display: flex; align-items: center; gap: 8px;
  border: 1.5px solid #E2E8F0; border-radius: 10px;
  padding: 10px 12px; background: #FAFAFA;
}
.input-wrap:focus-within { border-color: #2563EB; background: white; }
.input-error { border-color: #EF4444 !important; }
.input-wrap input, .input-wrap select {
  flex: 1; border: none; outline: none; background: none;
  font-size: 13px; color: #0F172A; font-family: inherit;
}
.err-msg { font-size: 11px; color: #EF4444; font-weight: 500; }

/* Role picker */
.role-picker { display: flex; flex-wrap: wrap; gap: 6px; }
.role-option {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 12px; border-radius: 8px;
  border: 1.5px solid #E2E8F0; background: white;
  font-size: 12px; font-weight: 600; color: #64748B;
  cursor: pointer; font-family: inherit; transition: all 0.12s;
}
.role-option:hover { background: #F8FAFC; border-color: #CBD5E1; }

/* Toggle switch */
.toggle-switch {
  width: 44px; height: 24px; border-radius: 99px;
  background: #E2E8F0; border: none; cursor: pointer;
  position: relative; transition: background 0.2s; flex-shrink: 0;
}
.toggle-switch.toggle-on { background: #10B981; }
.toggle-thumb {
  width: 18px; height: 18px; border-radius: 50%;
  background: white; position: absolute;
  top: 3px; left: 3px;
  transition: left 0.2s;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.toggle-on .toggle-thumb { left: 23px; }

.modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 16px 20px; background: #F8FAFC;
  border-top: 1px solid rgba(0,0,0,0.06); flex-shrink: 0;
}
.btn-cancel {
  padding: 10px 18px; border-radius: 10px;
  border: 1px solid #E2E8F0; background: white;
  font-size: 13px; font-weight: 600; color: #64748B;
  cursor: pointer; font-family: inherit;
}
.btn-save {
  padding: 10px 22px; border-radius: 10px; border: none;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  font-size: 13px; font-weight: 700; color: white;
  cursor: pointer; font-family: inherit; min-width: 90px;
  display: flex; align-items: center; justify-content: center;
}
.btn-save:disabled { opacity: 0.7; cursor: not-allowed; }
.btn-delete {
  padding: 10px 22px; border-radius: 10px; border: none;
  background: #EF4444; font-size: 13px; font-weight: 700; color: white;
  cursor: pointer; font-family: inherit;
}
.delete-warn { font-size: 13px; color: #64748B; line-height: 1.6; margin: 0; }
</style>
