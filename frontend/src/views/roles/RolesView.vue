<script setup>
import { ref, computed, onMounted } from 'vue'
import { roleApi } from '@/services/api'

const roles = ref([])
const catalog = ref([])
const loading = ref(true)
const saving = ref(false)
const selectedId = ref(null)
const draft = ref(null)
const errors = ref({})
const savedNotice = ref(false)
const deleteDialog = ref(false)

const SCOPE_PERMISSION = 'data.all_organizations'

onMounted(async () => {
  const [r, p] = await Promise.all([roleApi.list(), roleApi.permissions()])
  roles.value = r.data
  catalog.value = p.data
  loading.value = false
  if (roles.value.length) select(roles.value[0])
})

const selectedRole = computed(() => roles.value.find(r => r.id === selectedId.value) || null)
const isNew = computed(() => selectedId.value === 'new')
const isLocked = computed(() => !!selectedRole.value?.is_super_admin)
const permissionGroups = computed(() => catalog.value.filter(g => g.key !== 'data'))
const totalPermissions = computed(() => catalog.value.reduce((n, g) => n + g.permissions.length, 0))

const isDirty = computed(() => {
  if (!draft.value) return false
  if (isNew.value) return true
  const role = selectedRole.value
  return draft.value.name !== role.name
    || (draft.value.description || '') !== (role.description || '')
    || [...draft.value.permissions].sort().join() !== [...role.permissions].sort().join()
})

const deleteBlockedReason = computed(() => {
  const role = selectedRole.value
  if (!role) return ''
  if (role.is_system) return "Tizim rollarini o'chirib bo'lmaydi."
  if (role.users_count > 0) return `Avval ${role.users_count} ta foydalanuvchini boshqa rolga o'tkazing.`
  return ''
})

function select(role) {
  selectedId.value = role.id
  draft.value = { name: role.name, description: role.description || '', permissions: [...role.permissions] }
  errors.value = {}
}

function startNew() {
  selectedId.value = 'new'
  draft.value = { name: '', description: '', permissions: [] }
  errors.value = {}
}

function has(key) {
  return draft.value.permissions.includes(key)
}

function toggle(key) {
  if (isLocked.value) return
  const list = draft.value.permissions
  draft.value.permissions = has(key) ? list.filter(p => p !== key) : [...list, key]
}

function groupCount(group) {
  return group.permissions.filter(p => has(p.key)).length
}

function toggleGroup(group) {
  if (isLocked.value) return
  const keys = group.permissions.map(p => p.key)
  const allOn = groupCount(group) === keys.length
  const rest = draft.value.permissions.filter(p => !keys.includes(p))
  draft.value.permissions = allOn ? rest : [...rest, ...keys]
}

async function reload(selectId) {
  roles.value = (await roleApi.list()).data
  const role = roles.value.find(r => r.id === selectId) || roles.value[0]
  if (role) select(role)
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    const payload = {
      name: draft.value.name.trim(),
      description: draft.value.description.trim() || null,
      permissions: draft.value.permissions,
    }
    const res = isNew.value
      ? await roleApi.create(payload)
      : await roleApi.update(selectedId.value, payload)
    await reload(res.data.id)
    savedNotice.value = true
    setTimeout(() => { savedNotice.value = false }, 2000)
  } catch (e) {
    if (e.response?.data?.errors) {
      Object.entries(e.response.data.errors).forEach(([k, v]) => { errors.value[k] = v[0] })
    } else {
      errors.value.general = e.response?.data?.message || 'Saqlab bo\'lmadi. Qaytadan urinib ko\'ring.'
    }
  } finally {
    saving.value = false
  }
}

function discard() {
  if (isNew.value) {
    if (roles.value.length) select(roles.value[0])
    else draft.value = null
  } else {
    select(selectedRole.value)
  }
}

async function remove() {
  try {
    await roleApi.delete(selectedId.value)
    deleteDialog.value = false
    await reload()
  } catch (e) {
    deleteDialog.value = false
    errors.value.general = e.response?.data?.message || "O'chirib bo'lmadi."
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Rollar va huquqlar</h1>
        <p class="page-sub">Har bir rol tizimda qaysi amallarni bajara olishini belgilang</p>
      </div>
      <button class="create-btn" @click="startNew">
        <v-icon size="16" color="white">mdi-plus</v-icon>
        Yangi rol
      </button>
    </div>

    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 4" :key="i" class="skeleton-row" />
    </div>

    <div v-else class="layout">
      <!-- Role list -->
      <nav class="role-list" aria-label="Rollar">
        <button
          v-if="isNew"
          class="role-item role-item-active"
          type="button"
        >
          <span class="role-item-name">{{ draft.name || 'Yangi rol' }}</span>
          <span class="role-item-meta">Saqlanmagan</span>
        </button>
        <button
          v-for="role in roles"
          :key="role.id"
          class="role-item"
          :class="{ 'role-item-active': role.id === selectedId }"
          type="button"
          @click="select(role)"
        >
          <span class="role-item-name">
            {{ role.name }}
            <v-icon v-if="role.is_super_admin" size="14" color="#DC2626">mdi-shield-crown</v-icon>
          </span>
          <span class="role-item-meta">
            {{ role.users_count }} foydalanuvchi,
            {{ role.is_super_admin ? 'barcha huquqlar' : `${role.permissions.length} / ${totalPermissions} huquq` }}
          </span>
        </button>
      </nav>

      <!-- Editor -->
      <section v-if="draft" class="editor">
        <div v-if="isLocked" class="notice notice-locked">
          <v-icon size="18" color="#B91C1C">mdi-lock</v-icon>
          Super Admin barcha huquqlarga ega. Tizimdan chiqib qolmaslik uchun bu rolni o'zgartirib bo'lmaydi.
        </div>
        <div v-if="errors.general" class="notice notice-error" role="alert">
          <v-icon size="18" color="#B91C1C">mdi-alert-circle</v-icon>
          {{ errors.general }}
        </div>

        <div class="fields">
          <div class="field">
            <label for="role-name">Nomi <span class="req">*</span></label>
            <div class="input-wrap" :class="{ 'input-error': errors.name }">
              <input id="role-name" v-model="draft.name" :disabled="isLocked" placeholder="Masalan: Yoqilg'i operatori" />
            </div>
            <span v-if="errors.name" class="err-msg">{{ errors.name }}</span>
          </div>
          <div class="field">
            <label for="role-desc">Tavsif</label>
            <div class="input-wrap">
              <input id="role-desc" v-model="draft.description" :disabled="isLocked" placeholder="Bu rol kimlar uchun" />
            </div>
          </div>
        </div>

        <!-- Data scope -->
        <div class="scope" :class="{ 'scope-on': has(SCOPE_PERMISSION) }">
          <div class="scope-text">
            <div class="scope-title">Barcha tashkilotlar ma'lumotlari</div>
            <div class="scope-sub">
              {{ has(SCOPE_PERMISSION)
                ? "Ushbu rol barcha tashkilotlar ma'lumotlari bilan ishlaydi."
                : "Ushbu rol faqat foydalanuvchining o'z tashkiloti ma'lumotlari bilan ishlaydi." }}
            </div>
          </div>
          <button
            type="button"
            class="toggle-switch"
            :class="{ 'toggle-on': has(SCOPE_PERMISSION) }"
            :disabled="isLocked"
            role="switch"
            :aria-checked="has(SCOPE_PERMISSION)"
            aria-label="Barcha tashkilotlar ma'lumotlari"
            @click="toggle(SCOPE_PERMISSION)"
          >
            <div class="toggle-thumb" />
          </button>
        </div>

        <!-- Permission groups -->
        <div class="groups">
          <fieldset v-for="group in permissionGroups" :key="group.key" class="group" :disabled="isLocked">
            <legend class="group-head">
              <span class="group-title">{{ group.label }}</span>
              <span class="group-count">{{ groupCount(group) }} / {{ group.permissions.length }}</span>
              <button v-if="!isLocked" type="button" class="group-all" @click="toggleGroup(group)">
                {{ groupCount(group) === group.permissions.length ? 'Hammasini olib tashlash' : 'Hammasini tanlash' }}
              </button>
            </legend>
            <label v-for="p in group.permissions" :key="p.key" class="perm" :class="{ 'perm-on': has(p.key) }">
              <input type="checkbox" :checked="has(p.key)" @change="toggle(p.key)" />
              <span>{{ p.label }}</span>
            </label>
          </fieldset>
        </div>

        <!-- Footer -->
        <div v-if="!isLocked" class="editor-footer">
          <div class="footer-left">
            <button
              v-if="!isNew"
              type="button"
              class="btn-text-danger"
              :disabled="!!deleteBlockedReason"
              :title="deleteBlockedReason"
              @click="deleteDialog = true"
            >
              <v-icon size="15">mdi-delete</v-icon>
              Rolni o'chirish
            </button>
            <span v-if="!isNew && deleteBlockedReason" class="footer-hint">{{ deleteBlockedReason }}</span>
          </div>
          <span v-if="savedNotice" class="saved" role="status">
            <v-icon size="15" color="#10B981">mdi-check</v-icon> Saqlandi
          </span>
          <button type="button" class="btn-cancel" :disabled="!isDirty" @click="discard">Bekor qilish</button>
          <button type="button" class="btn-save" :disabled="!isDirty || saving" @click="save">
            <v-progress-circular v-if="saving" size="16" width="2" indeterminate color="white" />
            <span v-else>{{ isNew ? 'Rolni yaratish' : "O'zgarishlarni saqlash" }}</span>
          </button>
        </div>
      </section>
    </div>

    <!-- Delete dialog -->
    <div v-if="deleteDialog" class="modal-overlay" @click.self="deleteDialog = false">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="delete-title">
        <div class="modal-header">
          <div class="modal-icon"><v-icon color="#EF4444" size="20">mdi-delete-alert</v-icon></div>
          <div id="delete-title" class="modal-title">"{{ selectedRole?.name }}" rolini o'chirasizmi?</div>
        </div>
        <div class="modal-body">
          <p class="delete-warn">Rol va unga berilgan huquqlar butunlay o'chiriladi.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="deleteDialog = false">Bekor qilish</button>
          <button type="button" class="btn-delete" @click="remove">Rolni o'chirish</button>
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

.skeleton-list { display: flex; flex-direction: column; gap: 8px; }
.skeleton-row {
  height: 60px; background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
  background-size: 200% 100%; border-radius: 12px;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
@media (prefers-reduced-motion: reduce) { .skeleton-row { animation: none; } }

/* Two panes: role list beside the editor, stacked on narrow screens */
.layout {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
@media (max-width: 900px) {
  .layout { grid-template-columns: minmax(0, 1fr); }
}

.role-list {
  background: white; border: 1px solid rgba(0,0,0,0.08);
  border-radius: 16px; padding: 6px;
  display: flex; flex-direction: column; gap: 2px;
  position: sticky; top: 16px;
}
.role-item {
  display: flex; flex-direction: column; align-items: flex-start; gap: 2px;
  padding: 10px 12px; border-radius: 10px; border: none;
  background: none; cursor: pointer; text-align: left; font-family: inherit;
  border-left: 3px solid transparent;
}
.role-item:hover { background: #F8FAFC; }
.role-item:focus-visible, .group-all:focus-visible, .btn-text-danger:focus-visible {
  outline: 2px solid #2563EB; outline-offset: 2px;
}
.role-item-active { background: #EFF6FF; border-left-color: #2563EB; }
.role-item-active:hover { background: #EFF6FF; }
.role-item-name {
  display: flex; align-items: center; gap: 6px;
  font-size: 14px; font-weight: 700; color: #0F172A;
}
.role-item-meta { font-size: 12px; color: #94A3B8; }

.editor {
  background: white; border: 1px solid rgba(0,0,0,0.08);
  border-radius: 16px; padding: 20px;
  display: flex; flex-direction: column; gap: 18px;
}

.notice {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 10px 12px; border-radius: 10px; font-size: 13px; line-height: 1.5;
}
.notice-locked { background: #FEF2F2; color: #7F1D1D; }
.notice-error  { background: #FEF2F2; color: #B91C1C; }

.fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 12px; }
@media (max-width: 640px) { .fields { grid-template-columns: minmax(0, 1fr); } }
.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: 12px; font-weight: 600; color: #374151; }
.req { color: #EF4444; }
.input-wrap {
  display: flex; align-items: center; gap: 8px;
  border: 1.5px solid #E2E8F0; border-radius: 10px;
  padding: 10px 12px; background: #FAFAFA;
}
.input-wrap:focus-within { border-color: #2563EB; background: white; }
.input-error { border-color: #EF4444 !important; }
.input-wrap input {
  flex: 1; border: none; outline: none; background: none; min-width: 0;
  font-size: 13px; color: #0F172A; font-family: inherit;
}
.input-wrap input:disabled { color: #64748B; }
.err-msg { font-size: 11px; color: #EF4444; font-weight: 500; }

/* The one setting that changes what every other permission covers */
.scope {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 14px 16px; border-radius: 12px;
  border: 1.5px solid #E2E8F0; background: #F8FAFC;
}
.scope-on { border-color: #A78BFA; background: #F5F3FF; }
.scope-title { font-size: 14px; font-weight: 700; color: #0F172A; }
.scope-sub { font-size: 12px; color: #64748B; margin-top: 2px; }

.toggle-switch {
  width: 44px; height: 24px; border-radius: 99px;
  background: #CBD5E1; border: none; cursor: pointer;
  position: relative; transition: background 0.2s; flex-shrink: 0;
}
.toggle-switch:focus-visible { outline: 2px solid #2563EB; outline-offset: 2px; }
.toggle-switch:disabled { cursor: not-allowed; opacity: 0.6; }
.toggle-switch.toggle-on { background: #7C3AED; }
.toggle-thumb {
  width: 18px; height: 18px; border-radius: 50%;
  background: white; position: absolute; top: 3px; left: 3px;
  transition: left 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.toggle-on .toggle-thumb { left: 23px; }
@media (prefers-reduced-motion: reduce) { .toggle-switch, .toggle-thumb { transition: none; } }

.groups {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
}
.group {
  border: 1px solid #E2E8F0; border-radius: 12px;
  padding: 4px 12px 10px; margin: 0; min-width: 0;
}
.group-head {
  display: flex; align-items: center; gap: 8px; width: 100%;
  padding: 8px 0 6px;
}
.group-title { font-size: 13px; font-weight: 700; color: #0F172A; }
.group-count {
  font-size: 11px; font-weight: 600; color: #64748B;
  background: #F1F5F9; padding: 1px 7px; border-radius: 99px;
}
.group-all {
  margin-left: auto; background: none; border: none; cursor: pointer;
  font-size: 12px; font-weight: 600; color: #2563EB; font-family: inherit;
  padding: 2px 4px; border-radius: 6px;
}
.group-all:hover { text-decoration: underline; }

.perm {
  display: flex; align-items: center; gap: 9px;
  padding: 6px 4px; border-radius: 8px; cursor: pointer;
  font-size: 13px; color: #475569;
}
.perm:hover { background: #F8FAFC; }
.perm-on { color: #0F172A; font-weight: 500; }
.perm input { width: 16px; height: 16px; accent-color: #2563EB; cursor: pointer; flex-shrink: 0; }
.group:disabled .perm { cursor: default; }
.group:disabled .perm input { cursor: default; }

.editor-footer {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.06);
}
.footer-left { display: flex; align-items: center; gap: 10px; margin-right: auto; flex-wrap: wrap; }
.footer-hint { font-size: 12px; color: #94A3B8; }
.btn-text-danger {
  display: flex; align-items: center; gap: 5px;
  background: none; border: none; cursor: pointer; font-family: inherit;
  font-size: 13px; font-weight: 600; color: #EF4444;
  padding: 8px 10px; border-radius: 8px;
}
.btn-text-danger:hover:not(:disabled) { background: #FEF2F2; }
.btn-text-danger:disabled { color: #CBD5E1; cursor: not-allowed; }
.saved { display: flex; align-items: center; gap: 4px; font-size: 13px; color: #10B981; font-weight: 600; }

.btn-cancel {
  padding: 10px 18px; border-radius: 10px;
  border: 1px solid #E2E8F0; background: white;
  font-size: 13px; font-weight: 600; color: #64748B;
  cursor: pointer; font-family: inherit;
}
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-save {
  padding: 10px 22px; border-radius: 10px; border: none;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  font-size: 13px; font-weight: 700; color: white;
  cursor: pointer; font-family: inherit; min-width: 90px;
  display: flex; align-items: center; justify-content: center;
}
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-delete {
  padding: 10px 22px; border-radius: 10px; border: none;
  background: #EF4444; font-size: 13px; font-weight: 700; color: white;
  cursor: pointer; font-family: inherit;
}

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999; padding: 16px;
}
.modal-box {
  background: white; border-radius: 20px; width: 100%; max-width: 420px;
  box-shadow: 0 24px 64px rgba(0,0,0,0.18); overflow: hidden;
}
.modal-header { display: flex; align-items: center; gap: 12px; padding: 20px 20px 12px; }
.modal-icon {
  width: 42px; height: 42px; border-radius: 12px; background: #FEF2F2;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-title { font-size: 16px; font-weight: 800; color: #0F172A; }
.modal-body { padding: 0 20px 20px; }
.delete-warn { font-size: 13px; color: #64748B; line-height: 1.6; margin: 0; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 16px 20px; background: #F8FAFC; border-top: 1px solid rgba(0,0,0,0.06);
}
</style>
