<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { hrAlertApi } from '@/services/api'
import { alertCategories } from '@/utils/hr'

const router = useRouter()
const summary = ref(null)

const icons = { medical: 'mdi-heart-pulse', briefing: 'mdi-clipboard-check', ppe: 'mdi-hard-hat', license: 'mdi-card-account-details' }

onMounted(async () => {
  try {
    summary.value = (await hrAlertApi.summary()).data
  } catch {
    summary.value = null
  }
})
</script>

<template>
  <div v-if="summary" class="alerts-card">
    <div class="alerts-header">
      <div class="alerts-header-icon"><v-icon color="#DC2626" size="18">mdi-alert</v-icon></div>
      <div>
        <div class="alerts-title">Mehnat muhofazasi ogohlantirishlari</div>
        <div class="alerts-sub">Muddati o'tgan va 30 kun ichida tugaydigan</div>
      </div>
    </div>
    <div class="alerts-grid">
      <button
        v-for="(counts, category) in summary"
        :key="category"
        class="alert-tile"
        @click="router.push({ path: '/ohs/alerts', query: { category } })"
      >
        <v-icon size="20" color="#475569">{{ icons[category] }}</v-icon>
        <span class="tile-name">{{ alertCategories[category] }}</span>
        <span class="tile-counts">
          <span class="count overdue" :class="{ zero: !counts.overdue }">{{ counts.overdue }}</span>
          <span class="count soon" :class="{ zero: !counts.due_soon }">{{ counts.due_soon }}</span>
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.alerts-card { background: white; border: 1px solid #E2E8F0; border-radius: 16px; padding: 18px 20px; margin-bottom: 20px; }
.alerts-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.alerts-header-icon { width: 36px; height: 36px; border-radius: 10px; background: #FEE2E2; display: flex; align-items: center; justify-content: center; }
.alerts-title { font-weight: 700; font-size: 14px; color: #0F172A; }
.alerts-sub { font-size: 12px; color: #64748B; }
.alerts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.alert-tile { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid #E2E8F0; border-radius: 12px; background: #F8FAFC; cursor: pointer; text-align: left; }
.alert-tile:hover { border-color: #CBD5E1; background: white; }
.tile-name { flex: 1; font-size: 13px; font-weight: 600; color: #334155; }
.tile-counts { display: flex; gap: 6px; }
.count { min-width: 28px; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; text-align: center; }
.count.overdue { background: #FEE2E2; color: #B91C1C; }
.count.soon { background: #FEF3C7; color: #B45309; }
.count.zero { background: #F1F5F9; color: #94A3B8; }
</style>
