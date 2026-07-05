<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-10 text-center">

      <!-- Logo -->
      <div class="flex items-center justify-center gap-2 mb-8">
        <div class="w-10 h-10 bg-[#1F3A5F] rounded-xl flex items-center justify-center text-white font-extrabold text-xl">S</div>
        <span class="text-xl font-bold text-[#1F3A5F]">StockPilot</span>
      </div>

      <!-- Chargement -->
      <template v-if="status === 'loading'">
        <div class="w-12 h-12 border-4 border-slate-200 border-t-[#C8860A] rounded-full animate-spin mx-auto mb-6"></div>
        <p class="text-slate-500">Vérification en cours…</p>
      </template>

      <!-- Succès -->
      <template v-else-if="status === 'verified' || status === 'already_verified'">
        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-3xl mx-auto mb-6">✅</div>
        <h1 class="text-2xl font-bold text-[#1F3A5F] mb-3">Email confirmé !</h1>
        <p class="text-slate-600 mb-6">
          <template v-if="prenom">Merci <strong>{{ prenom }}</strong>. </template>Votre demande est bien enregistrée.<br>
          Notre équipe vous contactera sous <strong>24 heures</strong>.
        </p>
        <a href="/" class="inline-block bg-[#C8860A] hover:bg-yellow-600 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
          Retour à l'accueil
        </a>
      </template>

      <!-- Expiré -->
      <template v-else-if="status === 'expired'">
        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center text-3xl mx-auto mb-6">⏱</div>
        <h1 class="text-2xl font-bold text-[#1F3A5F] mb-3">Lien expiré</h1>
        <p class="text-slate-600 mb-6">
          Ce lien a expiré (valable 48h). Soumettez une nouvelle demande depuis la page d'accueil.
        </p>
        <a href="/" class="inline-block bg-[#1F3A5F] hover:bg-blue-900 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
          Nouvelle demande
        </a>
      </template>

      <!-- Invalide -->
      <template v-else>
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center text-3xl mx-auto mb-6">❌</div>
        <h1 class="text-2xl font-bold text-[#1F3A5F] mb-3">Lien invalide</h1>
        <p class="text-slate-600 mb-6">
          Ce lien de vérification est invalide ou a déjà été utilisé.
        </p>
        <a href="/" class="inline-block bg-[#1F3A5F] hover:bg-blue-900 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
          Retour à l'accueil
        </a>
      </template>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/services/api'

const route  = useRoute()
const status = ref<'loading' | 'verified' | 'already_verified' | 'expired' | 'invalid'>('loading')
const prenom = ref('')

onMounted(async () => {
  const token = route.params.token as string
  try {
    const { data } = await api.get(`/verify-email/${token}`)
    status.value = data.status
    prenom.value = data.prenom ?? ''
  } catch (e: any) {
    const s = e.response?.data?.status
    status.value = (s === 'expired' || s === 'invalid') ? s : 'invalid'
  }
})
</script>
