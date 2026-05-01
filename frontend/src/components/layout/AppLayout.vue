<script setup>
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
</script>

<template>
  <v-app>
    <AppSidebar />
    <AppHeader />
    <v-main style="background: #F1F5F9;">
      <RouterView />
    </v-main>
  </v-app>
</template>
