<template>
  <div class="min-h-screen bg-gradient-to-br from-navy to-navy-dark flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
      <div class="text-center mb-6">
        <div class="w-14 h-14 bg-gold rounded-2xl flex items-center justify-center mx-auto mb-4">
          <span class="text-white font-bold text-2xl">S</span>
        </div>
        <h1 class="text-2xl font-bold text-navy">Vérifiez votre email</h1>
        <p class="text-slate-500 text-sm mt-1">
          Un code de confirmation a été envoyé à<br><strong>{{ email }}</strong>
        </p>
      </div>

      <div v-if="error" class="bg-red-50 text-red-700 border border-red-200 rounded-lg px-4 py-3 mb-5 text-sm">
        {{ error }}
      </div>
      <div v-if="info" class="bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg px-4 py-3 mb-5 text-sm">
        {{ info }}
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Code de confirmation</label>
          <input v-model="code" inputmode="numeric" maxlength="6" required
            class="w-full border border-slate-300 rounded-lg px-4 py-3 text-center text-2xl tracking-[0.5em] font-bold focus:outline-none focus:ring-2 focus:ring-gold"
            placeholder="000000" />
        </div>
        <button type="submit" :disabled="loading || code.length < 6"
          class="w-full bg-navy hover:bg-navy-light text-white font-semibold py-3 rounded-lg transition-colors disabled:opacity-60">
          {{ loading ? 'Vérification…' : 'Confirmer et accéder' }}
        </button>
      </form>

      <div class="flex items-center justify-between mt-5 text-sm">
        <button @click="resend" :disabled="resending" class="text-gold hover:underline disabled:opacity-60">
          {{ resending ? 'Envoi…' : 'Renvoyer le code' }}
        </button>
        <RouterLink to="/login" class="text-slate-400 hover:text-slate-600">Retour à la connexion</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/services/api'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const email = ref(String(route.query.email ?? ''))
const code  = ref('')
const loading = ref(false)
const resending = ref(false)
const error = ref('')
const info  = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  info.value = ''
  try {
    await auth.verifyEmail(email.value, code.value)
    router.push(auth.isSuperAdmin ? '/app/super-admin' : '/app')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Code invalide.'
  } finally {
    loading.value = false
  }
}

async function resend() {
  resending.value = true
  error.value = ''
  info.value = ''
  try {
    await authApi.resendCode(email.value)
    info.value = 'Un nouveau code a été envoyé.'
  } catch {
    error.value = 'Impossible de renvoyer le code.'
  } finally {
    resending.value = false
  }
}
</script>
