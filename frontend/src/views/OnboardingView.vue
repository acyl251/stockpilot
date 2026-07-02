<template>
  <div class="min-h-screen bg-gradient-to-br from-navy to-navy-dark flex items-center justify-center p-6">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl">
      <!-- Header -->
      <div class="px-8 pt-8 pb-6 border-b border-slate-200">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-gold rounded-xl flex items-center justify-center">
            <span class="text-white font-bold">S</span>
          </div>
          <h1 class="text-xl font-bold text-navy">Configuration initiale</h1>
        </div>
        <div class="flex items-center gap-2 mt-4">
          <div class="h-1.5 flex-1 rounded-full bg-gold" />
        </div>
        <p class="text-xs text-slate-400 mt-2">Étape 1 / 1</p>
      </div>

      <!-- Welcome screen -->
      <div class="px-8 py-10 text-center space-y-5">
        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-navy">Votre espace est prêt !</h2>
        <p class="text-slate-500 text-sm max-w-sm mx-auto">
          Commencez par créer vos premiers produits depuis le catalogue.
          Vous pourrez aussi configurer vos catégories, fournisseurs et utilisateurs à tout moment.
        </p>
        <button @click="complete" :disabled="saving"
          class="btn-primary w-full disabled:opacity-60">
          {{ saving ? 'Chargement…' : 'Accéder au tableau de bord →' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { onboardingApi } from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()
const saving = ref(false)

async function complete() {
  saving.value = true
  try {
    await onboardingApi.confirm([], [])
    await auth.fetchMe()
    router.push('/app')
  } finally {
    saving.value = false
  }
}
</script>
