<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

async function login() {
  loading.value = true
  error.value = ''
  try {
    await authStore.login(form.value.email, form.value.password)
    router.push('/')
  } catch {
    error.value = "Login yoki parol noto'g'ri"
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <!-- Left panel -->
    <div class="login-left">
      <div class="login-brand">
        <img src="/logo.svg" alt="UTG Logo" class="brand-svg-logo" />
      </div>

      <div class="login-hero">
        <h1><span style="color:white;">"Urganchtransgaz" UK</span><br>Avtotransport va<br><span class="hero-accent">Maxsus Texnika Tizimi</span></h1>
        <p>Yagona raqamli platforma — yo'l varaqalari, transport va yoqilg'i nazorati.</p>
      </div>

      <div class="login-features">
        <div class="feature-item">
          <v-icon color="#60A5FA" size="18">mdi-check-circle</v-icon>
          <span>14 ta quyi tashkilot</span>
        </div>
        <div class="feature-item">
          <v-icon color="#60A5FA" size="18">mdi-check-circle</v-icon>
          <span>Real vaqtda monitoring</span>
        </div>
        <div class="feature-item">
          <v-icon color="#60A5FA" size="18">mdi-check-circle</v-icon>
          <span>To'liq tasdiqlash zanjiri</span>
        </div>
      </div>
    </div>

    <!-- Right panel -->
    <div class="login-right">
      <div class="login-form-wrap">
        <div class="form-header">
          <img src="/logo.svg" alt="UTG Logo" class="form-logo" />
          <h2>Tizimga kirish</h2>
          <p>Hisobingizga kiring</p>
        </div>


        <div v-if="error" class="error-alert">
          <v-icon size="16" color="#DC2626">mdi-alert-circle</v-icon>
          {{ error }}
        </div>

        <form @submit.prevent="login" class="login-form">
          <div class="field-group">
            <label>Email manzil</label>
            <div class="field-wrap">
              <v-icon size="18" class="field-icon" color="#94A3B8">mdi-email-outline</v-icon>
              <input
                v-model="form.email"
                type="email"
                placeholder="email@utg.uz"
                autocomplete="email"
                required
              />
            </div>
          </div>

          <div class="field-group">
            <label>Parol</label>
            <div class="field-wrap">
              <v-icon size="18" class="field-icon" color="#94A3B8">mdi-lock-outline</v-icon>
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                autocomplete="current-password"
                required
              />
              <button type="button" class="eye-btn" @click="showPassword = !showPassword">
                <v-icon size="16" color="#94A3B8">{{ showPassword ? 'mdi-eye-off' : 'mdi-eye' }}</v-icon>
              </button>
            </div>
          </div>

          <button type="submit" class="submit-btn" :disabled="loading">
            <span v-if="!loading">Kirish</span>
            <v-progress-circular v-else size="20" width="2" indeterminate color="white" />
          </button>
        </form>

        <div class="login-footer">
          <div class="developer-credit">
            <div class="credit-line">
              <span class="credit-icon">⚡</span>
              <span class="credit-text">
                <span class="credit-role">Axborot texnologiyalari xizmati</span>
                <span class="credit-name">Jaloliddin Saidov</span>
                <span class="credit-action">tomonidan ishlab chiqilgan</span>
              </span>
            </div>
          </div>
          <p class="copyright">Urganchtransgaz © {{ new Date().getFullYear() }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  display: flex;
  min-height: 100vh;
  font-family: 'Inter', system-ui, sans-serif;
}

/* Left */
.login-left {
  flex: 1;
  background: #0F172A;
  padding: 48px;
  display: flex;
  flex-direction: column;
  gap: 48px;
  position: relative;
  overflow: hidden;
}
.login-left::before {
  content: '';
  position: absolute;
  top: -120px; right: -120px;
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(37,99,235,0.3) 0%, transparent 70%);
  pointer-events: none;
}
.login-left::after {
  content: '';
  position: absolute;
  bottom: -80px; left: -80px;
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(124,58,237,0.25) 0%, transparent 70%);
  pointer-events: none;
}

.login-brand { display: flex; align-items: center; }

.brand-svg-logo {
  height: 56px;
  width: auto;
  filter: brightness(0) invert(1);
  opacity: 0.95;
}

.login-hero { flex: 1; }
.login-hero h1 {
  font-size: 44px; font-weight: 800; color: white;
  line-height: 1.15; letter-spacing: -1px; margin: 0 0 16px;
}
.hero-accent { color: #60A5FA; }
.login-hero p { font-size: 15px; color: #64748B; line-height: 1.6; max-width: 360px; }

.login-features { display: flex; flex-direction: column; gap: 10px; }
.feature-item {
  display: flex; align-items: center; gap: 10px;
  font-size: 14px; color: #94A3B8;
}

/* Right */
.login-right {
  width: 480px;
  flex-shrink: 0;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px;
}
@media (max-width: 860px) {
  .login-left { display: none; }
  .login-right { width: 100%; }
}

.login-form-wrap { width: 100%; max-width: 360px; }

.form-header { margin-bottom: 32px; }

.form-logo {
  height: 80px;
  width: auto;
  display: block;
  margin: 0 auto 24px;
}

.form-header h2 { font-size: 28px; font-weight: 800; color: #0F172A; margin: 0 0 6px; text-align: center; }
.form-header p  { font-size: 14px; color: #94A3B8; margin: 0; text-align: center; }

.error-alert {
  display: flex; align-items: center; gap: 8px;
  background: #FEE2E2; color: #DC2626;
  font-size: 13px; font-weight: 500;
  padding: 12px 14px; border-radius: 10px;
  margin-bottom: 20px;
}

.login-form { display: flex; flex-direction: column; gap: 18px; }

.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label { font-size: 13px; font-weight: 600; color: #374151; }

.field-wrap {
  display: flex; align-items: center;
  border: 1.5px solid #E2E8F0; border-radius: 12px;
  padding: 0 14px; transition: border 0.15s;
  background: #FAFAFA;
}
.field-wrap:focus-within { border-color: #2563EB; background: white; }

.field-icon { flex-shrink: 0; margin-right: 10px; }

.field-wrap input {
  flex: 1; border: none; background: none; outline: none;
  font-size: 14px; color: #0F172A; padding: 14px 0;
  font-family: inherit;
}
.field-wrap input::placeholder { color: #CBD5E1; }

.eye-btn {
  background: none; border: none; cursor: pointer; padding: 4px;
  display: flex; align-items: center;
}

.submit-btn {
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, #2563EB, #7C3AED);
  color: white; font-size: 15px; font-weight: 700;
  border: none; border-radius: 12px; cursor: pointer;
  transition: opacity 0.15s, transform 0.15s;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(37,99,235,0.35);
  margin-top: 8px;
}
.submit-btn:hover:not(:disabled) { opacity: 0.92; transform: translateY(-1px); }
.submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }

.login-footer {
  margin-top: 32px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.developer-credit {
  width: 100%;
  padding: 12px 16px;
  background: linear-gradient(135deg, #F0F7FF 0%, #F5F3FF 100%);
  border: 1px solid rgba(37,99,235,0.12);
  border-radius: 12px;
}

.credit-line {
  display: flex;
  align-items: center;
  gap: 10px;
}

.credit-icon {
  font-size: 16px;
  flex-shrink: 0;
}

.credit-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.credit-role {
  font-size: 10px;
  font-weight: 600;
  color: #7C3AED;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.credit-name {
  font-size: 14px;
  font-weight: 800;
  color: #1E1B4B;
  letter-spacing: -0.3px;
}

.credit-action {
  font-size: 11px;
  color: #64748B;
}

.copyright {
  font-size: 11px;
  color: #CBD5E1;
  margin: 0;
}
</style>
