// Uzbek labels for HR and occupational-safety codes returned by the API.

export const employmentTypes = { staff: 'Shtat', contract: 'Shartnoma', part_time: 'Yarim stavka' }
export const employeeStatuses = { active: 'Ishlaydi', dismissed: "Bo'shatilgan" }
export const genders = { male: 'Erkak', female: 'Ayol' }

export const eventTypes = { hire: 'Ishga qabul', transfer: "O'tkazish", dismissal: "Ishdan bo'shatish" }
export const dismissalReasons = {
  own_wish: 'Xodimning tashabbusi bilan',
  agreement: "Tomonlarning kelishuviga ko'ra",
  contract_end: 'Mehnat shartnomasi muddati tugashi',
  employer_initiative: 'Ish beruvchining tashabbusi bilan',
  retirement: 'Pensiyaga chiqish',
  other: 'Boshqa',
}

export const absenceTypes = {
  annual_leave: "Yillik ta'til",
  unpaid_leave: "Haq to'lanmaydigan ta'til",
  study_leave: "O'quv ta'tili",
  maternity_leave: "Homiladorlik va tug'ish ta'tili",
  sick_leave: 'Kasallik varaqasi',
  business_trip: 'Xizmat safari',
  absenteeism: 'Sababsiz kelmaslik',
  other: 'Boshqa',
}

export const examTypes = { preliminary: 'Dastlabki', periodic: 'Davriy', extraordinary: 'Navbatdan tashqari' }
export const examResults = { fit: 'Yaroqli', fit_with_restrictions: 'Cheklovlar bilan yaroqli', unfit: 'Yaroqsiz' }
export const examStatuses = { valid: 'Amalda', due_soon: 'Muddati yaqin', overdue: "Muddati o'tgan", unfit: 'Yaroqsiz' }
export const examStatusColors = { valid: 'success', due_soon: 'warning', overdue: 'error', unfit: 'error' }

export const briefingTypes = {
  introductory: 'Kirish',
  primary: 'Birlamchi',
  repeated: 'Takroriy',
  unscheduled: 'Navbatdan tashqari',
  targeted: 'Maqsadli',
}

export const ppeKinds = { clothing: 'Maxsus kiyim', footwear: 'Maxsus poyabzal', ppe: 'SHHV' }
export const ppeStatuses = { in_use: 'Foydalanishda', replaced: 'Almashtirilgan', returned: 'Qaytarilgan', written_off: 'Hisobdan chiqarilgan' }

export const alertCategories = { medical: "Tibbiy ko'rik", briefing: "Yo'riqnoma", ppe: 'Maxsus kiyim va SHHV', license: 'Haydovchilik guvohnomasi' }
export const alertStates = { overdue: "Muddati o'tgan", due_soon: 'Muddati yaqin' }

export const auditEvents = {
  created: 'Yaratildi',
  updated: "O'zgartirildi",
  deleted: "O'chirildi",
  viewed: "Ko'rildi",
  cancelled: 'Bekor qilindi',
  participants_synced: "Qatnashchilar o'zgardi",
}

export const label = (map, code) => map[code] ?? code ?? '—'

export const today = () => new Date().toISOString().slice(0, 10)

// Download a blob response as a file.
export function saveBlob(response, fallbackName) {
  const disposition = response.headers?.['content-disposition'] || ''
  const match = disposition.match(/filename="?([^";]+)"?/)
  const url = URL.createObjectURL(response.data)
  const a = document.createElement('a')
  a.href = url
  a.download = match ? match[1] : fallbackName
  a.click()
  URL.revokeObjectURL(url)
}

// The first validation or business-rule message of a failed request.
export function errorMessage(err, fallback = 'Xatolik yuz berdi') {
  const data = err?.response?.data
  if (data?.errors && !Array.isArray(data.errors)) {
    return Object.values(data.errors).flat()[0] || data.message || fallback
  }
  return data?.message || fallback
}

// Export rows as a CSV file Excel opens with the right encoding.
// columns: [{ key, title, value?: (row) => any }]
export function exportCsv(rows, columns, filename) {
  const escape = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const lines = [columns.map(c => escape(c.title)).join(';')]
  for (const row of rows) {
    lines.push(columns.map(c => escape(c.value ? c.value(row) : row[c.key])).join(';'))
  }
  const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
