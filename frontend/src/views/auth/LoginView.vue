<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({ email: 'admin@utg.uz', password: 'password' })
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

async function login() {
  loading.value = true
  error.value = ''
  try {
    await authStore.login(form.value.email, form.value.password)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || "Login yoki parol noto'g'ri"
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-app style="background: linear-gradient(135deg, #0D1B2A 0%, #1565C0 100%);">
    <v-main>
      <v-container class="fill-height" fluid>
        <v-row justify="center" align="center" class="fill-height">
          <v-col cols="12" sm="8" md="5" lg="4">
            <div class="text-center mb-8">
              <v-avatar color="white" size="72" rounded="xl" class="mb-4">
                <v-icon color="primary" size="40">mdi-gas-station</v-icon>
              </v-avatar>
              <h1 class="text-white text-h5 font-weight-bold">UTG Transport</h1>
              <p class="text-blue-lighten-3 text-body-2 mt-1">Avtotransport Boshqaruv Tizimi</p>
            </div>

            <v-card rounded="xl" elevation="8" class="pa-2">
              <v-card-text class="pa-6">
                <h2 class="text-h6 font-weight-bold mb-6">Tizimga kirish</h2>

                <v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mb-4" :text="error" />

                <v-form @submit.prevent="login">
                  <v-text-field
                    v-model="form.email"
                    label="Email"
                    type="email"
                    prepend-inner-icon="mdi-email-outline"
                    variant="outlined"
                    rounded="lg"
                    class="mb-3"
                    autocomplete="email"
                  />
                  <v-text-field
                    v-model="form.password"
                    label="Parol"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock-outline"
                    :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                    variant="outlined"
                    rounded="lg"
                    class="mb-4"
                    autocomplete="current-password"
                    @click:append-inner="showPassword = !showPassword"
                  />
                  <v-btn
                    type="submit"
                    color="primary"
                    variant="flat"
                    rounded="lg"
                    size="large"
                    block
                    :loading="loading"
                  >
                    Kirish
                  </v-btn>
                </v-form>
              </v-card-text>
            </v-card>

            <p class="text-center text-blue-lighten-3 text-caption mt-4">
              Urganchtransgaz © {{ new Date().getFullYear() }}
            </p>
          </v-col>
        </v-row>
      </v-container>
    </v-main>
  </v-app>
</template>
