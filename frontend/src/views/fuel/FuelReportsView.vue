<script setup>
import { fuelReportApi } from '@/services/api'
import { today } from '@/utils/hr'
import { quantity } from '@/utils/fuel'
import ReportTabs from '@/components/reports/ReportTabs.vue'

const monthStart = today().slice(0, 8) + '01'

const reports = [
  {
    key: 'turnover', title: 'Aylanma qaydnoma', params: { from: monthStart, to: today() },
    fetch: fuelReportApi.turnover,
    columns: [
      { key: 'station', title: 'Shahobcha' }, { key: 'unit', title: "O'lchov" },
      { key: 'opening', title: 'Boshlang\'ich qoldiq', value: r => quantity(r.opening) },
      { key: 'receipts', title: 'Qabul', value: r => quantity(r.receipts) },
      { key: 'refuels', title: 'Quyildi', value: r => quantity(r.refuels) },
      { key: 'adjustments_in', title: 'Inventarizatsiya +', value: r => quantity(r.adjustments_in) },
      { key: 'adjustments_out', title: 'Inventarizatsiya −', value: r => quantity(r.adjustments_out) },
      { key: 'closing', title: 'Yakuniy qoldiq', value: r => quantity(r.closing) },
    ],
  },
  {
    key: 'refuels', title: "Yoqilg'i quyish", params: { from: monthStart, to: today() },
    fetch: fuelReportApi.refuels,
    rows: data => data.rows,
    summary: data => `Jami: ${quantity(data.total)} · Avtomobillar: ${data.by_vehicle.length} · Haydovchilar: ${data.by_driver.filter(d => d.driver_id).length}`,
    rowClass: r => (r.waybill_number ? '' : 'row-warning'),
    columns: [
      { key: 'created_at', title: 'Vaqt' }, { key: 'station', title: 'Shahobcha' }, { key: 'state_number', title: 'Avtomobil' },
      { key: 'driver', title: 'Haydovchi' }, { key: 'waybill_number', title: "Yo'l varaqasi", value: r => r.waybill_number || "yo'q" },
      { key: 'odometer', title: 'Spidometr' }, { key: 'amount', title: 'Miqdor', value: r => quantity(r.amount) },
    ],
  },
  {
    key: 'consumption', title: "Me'yorga nisbatan sarf", params: { from: monthStart, to: today() },
    fetch: fuelReportApi.consumption,
    summary: () => "Taxminiy hisob: to'liq bak bilan quyilgan deb olinadi",
    rowClass: r => (r.over_norm ? 'row-warning' : ''),
    columns: [
      { key: 'state_number', title: 'Avtomobil' }, { key: 'model', title: 'Model' },
      { key: 'km', title: 'Masofa (km)' }, { key: 'litres', title: "Yoqilg'i", value: r => quantity(r.litres) },
      { key: 'per_100km', title: '100 km ga' }, { key: 'norm', title: "Me'yor" },
      { key: 'deviation_percent', title: 'Farq %', value: r => (r.deviation_percent === null ? '—' : `${r.deviation_percent > 0 ? '+' : ''}${r.deviation_percent}%`) },
    ],
  },
  {
    key: 'discrepancies', title: 'Smena farqlari', params: { from: monthStart, to: today() },
    fetch: fuelReportApi.discrepancies,
    columns: [
      { key: 'closed_at', title: 'Yopildi' }, { key: 'station', title: 'Shahobcha' }, { key: 'closed_by', title: 'Quyuvchi' },
      { key: 'closing_balance', title: 'Hisob', value: r => quantity(r.closing_balance) },
      { key: 'closing_measured', title: "O'lchov", value: r => quantity(r.closing_measured) },
      { key: 'discrepancy', title: 'Farq', value: r => quantity(r.discrepancy) }, { key: 'note', title: 'Izoh' },
    ],
  },
]
</script>

<template>
  <ReportTabs title="Yoqilg'i hisobotlari" :reports="reports" />
</template>
