<script setup>
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
</script>

<template>
  <v-app>
    <AppSidebar />
    <AppHeader />
    <v-main style="background: #F5F7FA;">
      <RouterView />
    </v-main>
  </v-app>
</template>
