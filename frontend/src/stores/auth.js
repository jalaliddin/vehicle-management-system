import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || null)

  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.role === 'superadmin')
  const isHqDispatcher = computed(() => ['superadmin', 'hq_dispatcher'].includes(user.value?.role))
  const isMechanic = computed(() => user.value?.role === 'mechanic')
  const isDoctor = computed(() => user.value?.role === 'doctor')

  async function login(email, password) {
    const res = await authApi.login({ email, password })
    token.value = res.data.token
    user.value = res.data.user
    localStorage.setItem('token', res.data.token)
    localStorage.setItem('user', JSON.stringify(res.data.user))
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    }
  }

  async function fetchMe() {
    const res = await authApi.me()
    user.value = res.data
    localStorage.setItem('user', JSON.stringify(res.data))
  }

  return { user, token, isAuthenticated, isSuperAdmin, isHqDispatcher, isMechanic, isDoctor, login, logout, fetchMe }
})
