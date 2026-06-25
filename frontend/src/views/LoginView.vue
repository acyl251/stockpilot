<template>
  <div class="min-h-screen bg-gradient-to-br from-navy to-navy-dark flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="w-14 h-14 bg-gold rounded-2xl flex items-center justify-center mx-auto mb-4">
          <span class="text-white font-bold text-2xl">S</span>
        </div>
        <h1 class="text-2xl font-bold text-navy">StockPilot</h1>
        <p class="text-slate-500 text-sm mt-1">Gestion de stock intelligente</p>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-50 text-red-700 border border-red-200 rounded-lg px-4 py-3 mb-6 text-sm">
        {{ error }}
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Adresse email</label>
          <input v-model="form.email" type="email" required
            class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
            placeholder="vous@entreprise.tn" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Mot de passe</label>
          <input v-model="form.password" type="password" required
            class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gold focus:border-transparent"
            placeholder="••••••••" />
        </div>
        <button type="submit" :disabled="loading"
          class="w-full bg-navy hover:bg-navy-light text-white font-semibold py-3 rounded-lg transition-colors disabled:opacity-60">
          <span v-if="loading">Connexion en cours…</span>
          <span v-else>Se connecter</span>
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-6">
        © 2025 StockPilot — ISET Sousse
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const form    = ref({ email: '', password: '' })
const loading = ref(false)
const error   = ref('')

async function submit() {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.email, form.value.password)
    router.push(auth.isSuperAdmin ? '/app/super-admin' : '/app')
  } catch (e: any) {
    // Email non vérifié → redirection vers l'écran de saisie du code.
    if (e.response?.status === 403 && e.response?.data?.verification_required) {
      router.push({ name: 'verify-email', query: { email: e.response.data.email } })
      return
    }
    error.value = e.response?.data?.message ?? 'Erreur de connexion.'
  } finally {
    loading.value = false
  }
}
</script>
