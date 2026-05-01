<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { driverApi } from '@/services/api'

const router = useRouter()
const route = useRoute()
const driver = ref(null)
const loading = ref(true)

onMounted(async () => {
  const res = await driverApi.show(route.params.id)
  driver.value = res.data
  loading.value = false
})
</script>

<template>
  <v-container fluid class="pa-6" style="max-width:800px;">
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-4" @click="router.back()">Orqaga</v-btn>
    <v-skeleton-loader v-if="loading" type="article" rounded="xl" />
    <template v-else-if="driver">
      <v-card rounded="xl" class="border">
        <v-card-text class="pa-6">
          <div class="d-flex align-center gap-4 mb-4">
            <v-avatar color="primary" size="60" rounded="xl">
              <v-icon color="white" size="30">mdi-account</v-icon>
            </v-avatar>
            <div>
              <h2 class="text-h5 font-weight-bold">{{ driver.full_name }}</h2>
              <p class="text-medium-emphasis">{{ driver.organization?.name }}</p>
            </div>
            <v-spacer />
            <v-btn icon variant="text" color="primary" @click="router.push(`/drivers/${driver.id}/edit`)">
              <v-icon>mdi-pencil</v-icon>
            </v-btn>
          </div>
          <v-divider class="mb-4" />
          <v-row>
            <v-col cols="6" md="4"><div class="text-caption text-medium-emphasis">Guvohnoma</div><div class="font-weight-medium">{{ driver.license_number }}</div></v-col>
            <v-col cols="6" md="4"><div class="text-caption text-medium-emphasis">Kategoriya</div><div class="font-weight-medium">{{ driver.license_category }}</div></v-col>
            <v-col cols="6" md="4"><div class="text-caption text-medium-emphasis">PINFL</div><div class="font-weight-medium">{{ driver.pinfl }}</div></v-col>
            <v-col cols="6" md="4"><div class="text-caption text-medium-emphasis">Telefon</div><div class="font-weight-medium">{{ driver.phone || '—' }}</div></v-col>
            <v-col cols="6" md="4"><div class="text-caption text-medium-emphasis">Staj</div><div class="font-weight-medium">{{ driver.work_experience }} yil</div></v-col>
            <v-col cols="6" md="4"><div class="text-caption text-medium-emphasis">Avtomobil</div><div class="font-weight-medium">{{ driver.vehicle?.state_number || "Biriktirilmagan" }}</div></v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </template>
  </v-container>
</template>
