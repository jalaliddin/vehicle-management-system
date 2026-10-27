<script setup>
import { hrReportApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { absenceTypes, briefingTypes, eventTypes, label, ppeStatuses, today } from '@/utils/hr'
import ReportTabs from '@/components/reports/ReportTabs.vue'

const auth = useAuthStore()
const yearStart = `${new Date().getFullYear()}-01-01`

// Each report: which permission it needs, its parameters, and how to turn the response into table rows.
const reports = [
  {
    key: 'headcount', title: 'Xodimlar soni', permission: 'employees.view', params: { date: today() },
    fetch: hrReportApi.headcount,
    columns: [{ key: 'organization', title: 'Tashkilot' }, { key: 'department', title: "Bo'lim", value: r => r.department || "Bo'limsiz" }, { key: 'count', title: 'Xodimlar' }],
  },
  {
    key: 'movement', title: 'Kadrlar harakati', permission: 'employees.view', params: { from: yearStart, to: today() },
    fetch: hrReportApi.movement,
    rows: (data) => [...data.hired, ...data.transferred, ...data.dismissed].sort((a, b) => a.effective_date.localeCompare(b.effective_date)),
    columns: [
      { key: 'effective_date', title: 'Sana' }, { key: 'type', title: 'Buyruq', value: r => label(eventTypes, r.type) },
      { key: 'order_number', title: 'Raqami' }, { key: 'employee_name', title: 'Xodim' }, { key: 'organization', title: 'Tashkilot' },
      { key: 'department', title: "Bo'lim" }, { key: 'position', title: 'Lavozim' },
      { key: 'dismissal_reason', title: 'Asos', value: r => r.dismissal_reason ?? '' },
    ],
    summary: (data) => `Qabul: ${data.counts.hired} · O'tkazish: ${data.counts.transferred} · Bo'shatish: ${data.counts.dismissed}`,
  },
  {
    key: 'absent', title: "Ishda yo'qlar", permission: 'employees.view', params: { date: today() },
    fetch: hrReportApi.absent,
    columns: [
      { key: 'employee_name', title: 'Xodim' }, { key: 'department', title: "Bo'lim" }, { key: 'position', title: 'Lavozim' },
      { key: 'type', title: 'Sababi', value: r => label(absenceTypes, r.type) }, { key: 'start_date', title: 'Dan' }, { key: 'end_date', title: 'Gacha' },
    ],
  },
  {
    key: 'leave-schedule', title: "Ta'til jadvali", permission: 'employees.view', params: { year: new Date().getFullYear() },
    fetch: hrReportApi.leaveSchedule,
    columns: [
      { key: 'employee_name', title: 'Xodim' }, { key: 'department', title: "Bo'lim" }, { key: 'entitlement', title: 'Huquq (kun)' },
      { key: 'planned', title: 'Rejadagi davrlar', value: r => r.planned.map(p => `${p.start_date} – ${p.end_date}`).join(', ') },
      { key: 'planned_days', title: 'Reja (kun)' }, { key: 'actual_days', title: 'Haqiqatda (kun)' },
    ],
  },
  {
    key: 'briefing-journal', title: "Yo'riqnomalar jurnali", permission: 'ohs.view', params: { from: yearStart, to: today() },
    fetch: hrReportApi.briefingJournal,
    columns: [
      { key: 'conducted_on', title: 'Sana' }, { key: 'type', title: 'Turi', value: r => label(briefingTypes, r.type) }, { key: 'topic', title: 'Mavzu' },
      { key: 'instructor', title: "O'tkazgan" }, { key: 'participants', title: 'Qatnashchilar', value: r => r.participants.map(p => p.full_name).join(', ') },
    ],
  },
  {
    key: 'ppe-issues', title: 'SHHV berilishi', permission: 'ohs.view', params: { from: yearStart, to: today() },
    fetch: hrReportApi.ppeIssues,
    columns: [
      { key: 'issued_on', title: 'Sana' }, { key: 'employee_name', title: 'Xodim' }, { key: 'department', title: "Bo'lim" },
      { key: 'ppe_item', title: 'Buyum' }, { key: 'quantity', title: 'Soni' }, { key: 'size', title: "O'lcham" },
      { key: 'due_on', title: 'Almashtirish' }, { key: 'status', title: 'Holati', value: r => label(ppeStatuses, r.status) },
    ],
  },
  {
    key: 'ppe-demand', title: 'SHHV ehtiyoji', permission: 'ohs.view', params: { months: 3 },
    fetch: hrReportApi.ppeDemand,
    columns: [{ key: 'ppe_item', title: 'Buyum' }, { key: 'size', title: "O'lcham", value: r => r.size || '—' }, { key: 'quantity', title: 'Soni' }, { key: 'unit', title: "O'lchov" }],
  },
].filter(r => auth.can(r.permission))
</script>

<template>
  <ReportTabs title="Kadrlar va mehnat muhofazasi hisobotlari" :reports="reports" />
</template>
