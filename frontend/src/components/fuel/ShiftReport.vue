<script setup>
// Printable shift report: balances, operations and totals of one shift.
import { quantity, transactionKinds } from '@/utils/fuel'
import { label } from '@/utils/hr'

defineProps({
  report: { type: Object, required: true },
  unit: { type: String, default: 'L' },
})

const time = (value) => (value ? new Date(value).toLocaleString('uz-UZ') : '—')
const subject = (op) => op.vehicle?.state_number || op.supplier?.name || op.document_number || '—'
</script>

<template>
  <div class="print-area">
    <div class="report-title">Smena hisoboti — {{ report.shift.station?.name }}</div>
    <div class="report-meta">
      <div>Ochildi: {{ time(report.shift.opened_at) }} ({{ report.shift.opened_by?.name }})</div>
      <div>Yopildi: {{ time(report.shift.closed_at) }} <span v-if="report.shift.closed_by">({{ report.shift.closed_by.name }})</span></div>
    </div>

    <table class="report-table">
      <tbody>
        <tr><td>Boshlang'ich qoldiq (hisob)</td><td>{{ quantity(report.shift.opening_balance) }} {{ unit }}</td></tr>
        <tr><td>Boshlang'ich o'lchov</td><td>{{ quantity(report.shift.opening_measured) }} {{ unit }}</td></tr>
        <tr><td>Qabul qilindi</td><td>{{ quantity(report.totals.receipts) }} {{ unit }}</td></tr>
        <tr><td>Quyildi</td><td>{{ quantity(report.totals.refuels) }} {{ unit }}</td></tr>
        <template v-if="report.shift.status === 'closed'">
          <tr><td>Yakuniy qoldiq (hisob)</td><td>{{ quantity(report.shift.closing_balance) }} {{ unit }}</td></tr>
          <tr><td>Yakuniy o'lchov</td><td>{{ quantity(report.shift.closing_measured) }} {{ unit }}</td></tr>
          <tr><td>Farq</td><td>{{ quantity(report.shift.discrepancy) }} {{ unit }}</td></tr>
          <tr v-if="report.shift.discrepancy_note"><td>Farq izohi</td><td>{{ report.shift.discrepancy_note }}</td></tr>
        </template>
      </tbody>
    </table>

    <div class="table-scroll">
      <table class="report-table ops">
        <thead>
          <tr><th>Vaqt</th><th>Amal</th><th>Avtomobil / yetkazib beruvchi</th><th>Spidometr</th><th>Miqdor</th></tr>
        </thead>
        <tbody>
          <tr v-for="op in report.operations" :key="op.id" :class="{ cancelled: op.cancelled_at }">
            <td>{{ time(op.created_at) }}</td>
            <td>{{ label(transactionKinds, op.kind) }}<span v-if="op.cancelled_at"> (bekor)</span></td>
            <td>{{ subject(op) }}</td>
            <td>{{ op.odometer ?? '—' }}</td>
            <td>{{ op.type === 'out' ? '−' : '+' }}{{ quantity(op.amount) }}</td>
          </tr>
          <tr v-if="!report.operations.length"><td colspan="5">Amallar yo'q</td></tr>
        </tbody>
      </table>
    </div>

    <div class="signatures">
      <div>Quyuvchi: ____________________</div>
      <div>Qabul qildi: ____________________</div>
    </div>
  </div>
</template>

<style>
.print-area .report-title { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
.print-area .report-meta { font-size: 13px; color: #475569; margin-bottom: 12px; }
.print-area .report-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 14px; }
.print-area .report-table td, .print-area .report-table th { border: 1px solid #E2E8F0; padding: 6px 8px; text-align: left; }
.print-area .report-table th { background: #F8FAFC; }
.print-area .cancelled td { color: #94A3B8; text-decoration: line-through; }
.print-area .table-scroll { overflow-x: auto; }
@media (max-width: 600px) {
  .print-area .report-table { font-size: 12px; }
  .print-area .report-table td, .print-area .report-table th { padding: 4px 5px; }
}
.print-area .signatures { display: flex; justify-content: space-between; font-size: 13px; margin-top: 24px; }
@media print {
  body * { visibility: hidden; }
  .print-area, .print-area * { visibility: visible; }
  .print-area { position: absolute; left: 0; top: 0; width: 100%; }
  .print-area .table-scroll { overflow: visible; }
}
</style>
