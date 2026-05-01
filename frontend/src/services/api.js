import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

export const authApi = {
  login: (data) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  me: () => api.get('/auth/me'),
}

export const dashboardApi = {
  stats: () => api.get('/dashboard/stats'),
}

export const organizationApi = {
  list: () => api.get('/organizations'),
  create: (data) => api.post('/organizations', data),
  update: (id, data) => api.put(`/organizations/${id}`, data),
  delete: (id) => api.delete(`/organizations/${id}`),
}

export const vehicleApi = {
  list: (params) => api.get('/vehicles', { params }),
  create: (data) => api.post('/vehicles', data),
  show: (id) => api.get(`/vehicles/${id}`),
  update: (id, data) => api.put(`/vehicles/${id}`, data),
  delete: (id) => api.delete(`/vehicles/${id}`),
  updateStatus: (id, status) => api.put(`/vehicles/${id}/status`, { status }),
}

export const driverApi = {
  list: (params) => api.get('/drivers', { params }),
  create: (data) => api.post('/drivers', data),
  show: (id) => api.get(`/drivers/${id}`),
  update: (id, data) => api.put(`/drivers/${id}`, data),
  delete: (id) => api.delete(`/drivers/${id}`),
  assign: (id, vehicleId) => api.post(`/drivers/${id}/assign`, { vehicle_id: vehicleId }),
}

export const waybillApi = {
  list: (params) => api.get('/waybills', { params }),
  create: (data) => api.post('/waybills', data),
  show: (id) => api.get(`/waybills/${id}`),
  update: (id, data) => api.put(`/waybills/${id}`, data),
  delete: (id) => api.delete(`/waybills/${id}`),
  mechanicCheck: (id, data) => api.post(`/waybills/${id}/mechanic-check`, data),
  doctorCheck: (id, data) => api.post(`/waybills/${id}/doctor-check`, data),
  approve: (id) => api.post(`/waybills/${id}/approve`),
  depart: (id) => api.post(`/waybills/${id}/depart`),
  complete: (id, data) => api.post(`/waybills/${id}/complete`, data),
  saveRoute: (id, data) => api.post(`/waybills/${id}/route`, data),
  submit: (id) => api.post(`/waybills/${id}/submit`),
}

export const fuelApi = {
  monitoring: () => api.get('/fuel/monitoring'),
  stations: () => api.get('/fuel/stations'),
  createStation: (data) => api.post('/fuel/stations', data),
  updateStation: (id, data) => api.put(`/fuel/stations/${id}`, data),
  addTransaction: (stationId, data) => api.post(`/fuel/stations/${stationId}/transaction`, data),
  transactions: (params) => api.get('/fuel/transactions', { params }),
}

export const fuelNormApi = {
  list: () => api.get('/fuel-norms'),
  create: (data) => api.post('/fuel-norms', data),
  update: (id, data) => api.put(`/fuel-norms/${id}`, data),
  delete: (id) => api.delete(`/fuel-norms/${id}`),
}

export const userApi = {
  list: (params) => api.get('/users', { params }),
  create: (data) => api.post('/users', data),
  show: (id) => api.get(`/users/${id}`),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id) => api.delete(`/users/${id}`),
  toggleActive: (id) => api.post(`/users/${id}/toggle-active`),
}

export const reportApi = {
  waybills: (params) => api.get('/reports/waybills', { params }),
  fuel: (params) => api.get('/reports/fuel', { params }),
  vehicles: (params) => api.get('/reports/vehicles', { params }),
  drivers: (params) => api.get('/reports/drivers', { params }),
  fuelConsumption: (params) => api.get('/reports/fuel-consumption', { params }),
}
