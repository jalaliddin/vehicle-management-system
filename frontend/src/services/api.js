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
  clearance: (id) => api.get(`/drivers/${id}/clearance`),
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
  fuelerCandidates: (stationId) => api.get(`/fuel/stations/${stationId}/fueler-candidates`),
  assignFuelers: (stationId, userIds) => api.put(`/fuel/stations/${stationId}/fuelers`, { user_ids: userIds }),
  transactions: (params) => api.get('/fuel/transactions', { params }),
  currentShift: (stationId) => api.get(`/fuel/stations/${stationId}/current-shift`),
  openShift: (stationId, openingMeasured) => api.post(`/fuel/stations/${stationId}/shifts`, { opening_measured: openingMeasured }),
  closeShift: (shiftId, data) => api.post(`/fuel/shifts/${shiftId}/close`, data),
  shifts: (params) => api.get('/fuel/shifts', { params }),
  shift: (shiftId) => api.get(`/fuel/shifts/${shiftId}`),
  vehicles: (stationId, search) => api.get(`/fuel/stations/${stationId}/vehicles`, { params: { search } }),
  receive: (stationId, data) => api.post(`/fuel/stations/${stationId}/receipts`, data),
  refuel: (stationId, data) => api.post(`/fuel/stations/${stationId}/refuels`, data),
  cancel: (transactionId, reason) => api.post(`/fuel/transactions/${transactionId}/cancel`, { reason }),
  adjust: (stationId, data) => api.post(`/fuel/stations/${stationId}/adjustments`, data),
}

export const fuelSupplierApi = {
  list: () => api.get('/fuel/suppliers'),
  create: (data) => api.post('/fuel/suppliers', data),
  update: (id, data) => api.put(`/fuel/suppliers/${id}`, data),
  delete: (id) => api.delete(`/fuel/suppliers/${id}`),
}

export const fuelReportApi = {
  turnover: (params) => api.get('/fuel/reports/turnover', { params }),
  refuels: (params) => api.get('/fuel/reports/refuels', { params }),
  consumption: (params) => api.get('/fuel/reports/consumption', { params }),
  discrepancies: (params) => api.get('/fuel/reports/discrepancies', { params }),
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

export const roleApi = {
  list: () => api.get('/roles'),
  permissions: () => api.get('/permissions'),
  create: (data) => api.post('/roles', data),
  update: (id, data) => api.put(`/roles/${id}`, data),
  delete: (id) => api.delete(`/roles/${id}`),
}

export const reportApi = {
  waybills: (params) => api.get('/reports/waybills', { params }),
  fuel: (params) => api.get('/reports/fuel', { params }),
  vehicles: (params) => api.get('/reports/vehicles', { params }),
  drivers: (params) => api.get('/reports/drivers', { params }),
  fuelConsumption: (params) => api.get('/reports/fuel-consumption', { params }),
}

export const departmentApi = {
  list: (params) => api.get('/departments', { params }),
  create: (data) => api.post('/departments', data),
  update: (id, data) => api.put(`/departments/${id}`, data),
  delete: (id) => api.delete(`/departments/${id}`),
}

export const positionApi = {
  list: () => api.get('/positions'),
  create: (data) => api.post('/positions', data),
  update: (id, data) => api.put(`/positions/${id}`, data),
  delete: (id) => api.delete(`/positions/${id}`),
  ppeNorms: (id) => api.get(`/positions/${id}/ppe-norms`),
  updatePpeNorms: (id, norms) => api.put(`/positions/${id}/ppe-norms`, { norms }),
}

export const ppeItemApi = {
  list: () => api.get('/ppe-items'),
  create: (data) => api.post('/ppe-items', data),
  update: (id, data) => api.put(`/ppe-items/${id}`, data),
  delete: (id) => api.delete(`/ppe-items/${id}`),
}

export const employeeApi = {
  list: (params) => api.get('/employees', { params }),
  create: (data) => api.post('/employees', data),
  show: (id) => api.get(`/employees/${id}`),
  update: (id, data) => api.put(`/employees/${id}`, data),
  delete: (id) => api.delete(`/employees/${id}`),
  photo: (id) => api.get(`/employees/${id}/photo`, { responseType: 'blob' }),
  uploadPhoto: (id, file) => {
    const form = new FormData()
    form.append('photo', file)
    return api.post(`/employees/${id}/photo`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
  history: (id, params) => api.get(`/employees/${id}/history`, { params }),
  events: (id) => api.get(`/employees/${id}/events`),
  addEvent: (id, data) => api.post(`/employees/${id}/events`, data),
  cancelEvent: (eventId, reason) => api.post(`/employment-events/${eventId}/cancel`, { reason }),
  absences: (id, params) => api.get(`/employees/${id}/absences`, { params }),
  addAbsence: (id, data) => api.post(`/employees/${id}/absences`, data),
  updateAbsence: (absenceId, data) => api.put(`/absences/${absenceId}`, data),
  deleteAbsence: (absenceId) => api.delete(`/absences/${absenceId}`),
  leaveBalance: (id, year) => api.get(`/employees/${id}/leave-balance`, { params: { year } }),
  medicalExams: (id) => api.get(`/employees/${id}/medical-exams`),
  addMedicalExam: (id, form) => api.post(`/employees/${id}/medical-exams`, form, { headers: { 'Content-Type': 'multipart/form-data' } }),
  // PUT cannot carry multipart files in PHP, so updates are sent as POST with _method=PUT.
  updateMedicalExam: (examId, form) => {
    form.append('_method', 'PUT')
    return api.post(`/medical-exams/${examId}`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
  deleteMedicalExam: (examId) => api.delete(`/medical-exams/${examId}`),
  medicalDocument: (examId) => api.get(`/medical-exams/${examId}/document`, { responseType: 'blob' }),
  ppe: (id) => api.get(`/employees/${id}/ppe`),
  issuePpe: (id, data) => api.post(`/employees/${id}/ppe-issues`, data),
  closePpeIssue: (issueId, data) => api.post(`/ppe-issues/${issueId}/close`, data),
  importTemplate: () => api.get('/employees/import-template', { responseType: 'blob' }),
  import: (file, dryRun) => {
    const form = new FormData()
    form.append('file', file)
    form.append('dry_run', dryRun ? '1' : '0')
    return api.post('/employees/import', form, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
}

export const medicalExamApi = {
  list: (params) => api.get('/medical-exams', { params }),
}

export const briefingApi = {
  list: (params) => api.get('/safety-briefings', { params }),
  show: (id) => api.get(`/safety-briefings/${id}`),
  create: (data) => api.post('/safety-briefings', data),
  update: (id, data) => api.put(`/safety-briefings/${id}`, data),
  delete: (id) => api.delete(`/safety-briefings/${id}`),
}

export const hrAlertApi = {
  list: (params) => api.get('/hr/alerts', { params }),
  summary: () => api.get('/hr/alerts/summary'),
}

export const hrReportApi = {
  headcount: (params) => api.get('/hr/reports/headcount', { params }),
  movement: (params) => api.get('/hr/reports/movement', { params }),
  absent: (params) => api.get('/hr/reports/absent', { params }),
  leaveSchedule: (params) => api.get('/hr/reports/leave-schedule', { params }),
  briefingJournal: (params) => api.get('/hr/reports/briefing-journal', { params }),
  ppeIssues: (params) => api.get('/hr/reports/ppe-issues', { params }),
  ppeDemand: (params) => api.get('/hr/reports/ppe-demand', { params }),
}

export const auditLogApi = {
  list: (params) => api.get('/audit-logs', { params }),
}
