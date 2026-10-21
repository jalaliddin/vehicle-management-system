import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || null)
  // Whether the cached user has been re-fetched this session, so role
  // changes made by an admin apply without logging out.
  const refreshed = ref(false)

  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.role === 'superadmin')
  const permissions = computed(() => new Set(user.value?.permissions || []))
  const canAccessAllOrganizations = computed(() => permissions.value.has('data.all_organizations'))

  function can(permission) {
    return permissions.value.has(permission)
  }

  function canAny(list) {
    return list.some(can)
  }

  // The organizations the user may assign records to.
  function assignableOrganizations(organizations) {
    if (canAccessAllOrganizations.value) return organizations
    return organizations.filter(o => o.id === user.value?.organization_id)
  }

  function setUser(data) {
    user.value = data
    refreshed.value = true
    localStorage.setItem('user', JSON.stringify(data))
  }

  async function login(email, password) {
    const res = await authApi.login({ email, password })
    token.value = res.data.token
    localStorage.setItem('token', res.data.token)
    setUser(res.data.user)
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      token.value = null
      user.value = null
      refreshed.value = false
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    }
  }

  async function fetchMe() {
    const res = await authApi.me()
    setUser(res.data)
  }

  return {
    user, token, refreshed, isAuthenticated, isSuperAdmin, permissions, canAccessAllOrganizations,
    can, canAny, assignableOrganizations, login, logout, fetchMe,
  }
})
