<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
      <div class="flex gap-3 items-center">
        <input v-model="search" @input="debouncedFetch"
          placeholder="Rechercher un client (nom ou téléphone)…"
          class="border border-slate-300 rounded-lg px-4 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-gold" />
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" v-model="onlyDebtors" @change="fetchClients" /> Débiteurs uniquement
        </label>
      </div>
      <button @click="openCreate" class="btn-primary">+ Nouveau client</button>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Client</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Téléphone</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Solde dû</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="4" class="text-center py-10 text-slate-400">Chargement…</td></tr>
          <tr v-else-if="clients.length === 0"><td colspan="4" class="text-center py-10 text-slate-400">Aucun client.</td></tr>
          <tr v-for="c in clients" :key="c.id" class="border-b border-slate-100 hover:bg-slate-50">
            <td class="px-4 py-3 font-medium text-navy">{{ c.nom }}</td>
            <td class="px-4 py-3 text-slate-500">{{ c.telephone || '—' }}</td>
            <td class="px-4 py-3 text-right font-semibold"
              :class="Number(c.solde) > 0 ? 'text-red-600' : 'text-emerald-600'">{{ money(c.solde) }}</td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center gap-3 justify-end">
                <button @click="openDetail(c.id)" class="text-gold hover:underline text-xs font-medium">Détails</button>
                <button @click="openReleve(c)" class="text-indigo-600 hover:underline text-xs font-medium">Relevé de compte</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Détail client -->
    <div v-if="detail" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 class="font-bold text-navy text-lg">{{ detail.client.nom }}</h2>
            <p class="text-slate-400 text-sm">{{ detail.client.telephone || 'Sans téléphone' }}</p>
          </div>
          <button @click="detail = null" class="text-slate-400 hover:text-navy text-xl">✕</button>
        </div>

        <div class="p-6 space-y-5">
          <!-- Solde + encaissement -->
          <div class="flex items-center justify-between bg-slate-50 rounded-xl p-4">
            <div>
              <p class="text-xs text-slate-500">Solde dû</p>
              <p class="text-2xl font-bold" :class="Number(detail.solde) > 0 ? 'text-red-600' : 'text-emerald-600'">
                {{ money(detail.solde) }}
              </p>
              <button v-if="Number(detail.solde) > 0 && detail.client.telephone"
                @click="remind" :disabled="reminding"
                class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 rounded-lg px-3 py-1.5 disabled:opacity-50">
                <span>📲</span> {{ reminding ? '…' : 'Relancer (WhatsApp)' }}
              </button>
              <p v-else-if="Number(detail.solde) > 0" class="text-xs text-slate-400 mt-2">Ajoutez un téléphone pour relancer.</p>
            </div>
            <div v-if="Number(detail.solde) > 0" class="flex items-end gap-2">
              <div>
                <label class="block text-xs text-slate-500 mb-1">Montant reçu</label>
                <input v-model.number="payAmount" type="number" min="0" step="0.001"
                  class="border border-slate-300 rounded-lg px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-gold" />
              </div>
              <select v-model="payMode" class="border border-slate-300 rounded-lg px-2 py-2 text-sm">
                <option value="especes">Espèces</option>
                <option value="carte">Carte</option>
              </select>
              <button @click="pay" :disabled="paying" class="btn-primary py-2 disabled:opacity-50">
                {{ paying ? '…' : 'Encaisser' }}
              </button>
              <button @click="payAmount = Number(detail.solde)" class="text-xs text-gold hover:underline pb-2">Tout</button>
            </div>
          </div>
          <p v-if="payError" class="text-red-600 text-xs">{{ payError }}</p>

          <!-- Ventes -->
          <div>
            <h3 class="font-semibold text-navy mb-2 text-sm">Tickets</h3>
            <table class="w-full text-sm">
              <thead class="text-slate-500 border-b border-slate-200">
                <tr>
                  <th class="text-left py-2">Ticket</th><th class="text-left py-2">Date</th>
                  <th class="text-right py-2">Total</th><th class="text-right py-2">Reste</th>
                  <th class="text-center py-2">Statut</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in detail.sales" :key="s.id" class="border-b border-slate-100">
                  <td class="py-2 font-mono text-xs">{{ s.numero }}</td>
                  <td class="py-2 text-slate-500">{{ formatDate(s.date_vente) }}</td>
                  <td class="py-2 text-right">{{ money(s.total_ttc) }}</td>
                  <td class="py-2 text-right" :class="Number(s.reste_a_payer) > 0 ? 'text-red-600 font-semibold' : 'text-slate-400'">
                    {{ money(s.reste_a_payer) }}
                  </td>
                  <td class="py-2 text-center">
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statutClass(s.statut_paiement)">
                      {{ statutLabel(s.statut_paiement) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="detail.sales.length === 0"><td colspan="5" class="py-4 text-center text-slate-400">Aucun ticket.</td></tr>
              </tbody>
            </table>
          </div>

          <!-- Paiements -->
          <div>
            <h3 class="font-semibold text-navy mb-2 text-sm">Historique des paiements</h3>
            <ul class="space-y-1">
              <li v-for="p in detail.payments" :key="p.id" class="flex justify-between text-sm py-1 border-b border-slate-100">
                <span class="text-slate-500">{{ formatDate(p.date_paiement) }} — <span class="capitalize">{{ p.mode_paiement }}</span></span>
                <span class="font-semibold text-emerald-600">{{ money(p.montant) }}</span>
              </li>
              <li v-if="detail.payments.length === 0" class="text-slate-400 text-sm py-2">Aucun paiement.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Relevé de compte -->
    <div v-if="releve" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 class="font-bold text-navy text-lg">Relevé de compte — {{ releveClient?.nom }}</h2>
            <p class="text-slate-400 text-sm">Ventes à crédit sur la période</p>
          </div>
          <button @click="releve = null" class="text-slate-400 hover:text-navy text-xl">✕</button>
        </div>

        <div class="p-6 space-y-4">
          <!-- Sélecteur période -->
          <div class="flex flex-wrap items-end gap-3">
            <div class="flex gap-1">
              <button v-for="p in periodPresets" :key="p.key" @click="applyPeriodPreset(p.key)"
                :class="['text-xs font-medium px-3 py-1.5 rounded-lg border',
                  periodPreset === p.key ? 'bg-navy text-white border-navy' : 'border-slate-300 text-slate-600 hover:bg-slate-50']">
                {{ p.label }}
              </button>
            </div>
            <div class="flex items-end gap-2">
              <div>
                <label class="block text-xs text-slate-500 mb-1">Du</label>
                <input v-model="releveDebut" @change="periodPreset = 'custom'; fetchReleve()" type="date"
                  class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm" />
              </div>
              <div>
                <label class="block text-xs text-slate-500 mb-1">Au</label>
                <input v-model="releveFin" @change="periodPreset = 'custom'; fetchReleve()" type="date"
                  class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm" />
              </div>
            </div>
          </div>

          <div v-if="releveLoading" class="text-center py-6 text-slate-400 text-sm">Chargement…</div>
          <template v-else>
            <table class="w-full text-sm">
              <thead class="text-slate-500 border-b border-slate-200">
                <tr>
                  <th class="text-left py-2">Date</th><th class="text-left py-2">N° facture</th>
                  <th class="text-right py-2">Montant</th><th class="text-right py-2">Réglé</th>
                  <th class="text-right py-2">Reste</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in releve.sales" :key="s.id" class="border-b border-slate-100">
                  <td class="py-2 text-slate-500">{{ formatDate(s.date_vente) }}</td>
                  <td class="py-2 font-mono text-xs">{{ s.numero_facture ?? s.numero }}</td>
                  <td class="py-2 text-right">{{ money(s.total_ttc) }}</td>
                  <td class="py-2 text-right">{{ money(s.montant_regle) }}</td>
                  <td class="py-2 text-right font-semibold" :class="(s.total_ttc - s.montant_regle) > 0 ? 'text-red-600' : 'text-slate-400'">
                    {{ money(s.total_ttc - s.montant_regle) }}
                  </td>
                </tr>
                <tr v-if="releve.sales.length === 0"><td colspan="5" class="py-4 text-center text-slate-400">Aucune vente à crédit sur cette période.</td></tr>
              </tbody>
            </table>

            <div class="flex items-center justify-between bg-slate-50 rounded-xl p-4">
              <span class="text-sm text-slate-500">Total dû (solde actuel)</span>
              <span class="text-xl font-bold text-red-600">{{ money(releve.solde) }}</span>
            </div>

            <button @click="downloadRelevePdf" :disabled="relevePdfLoading" class="btn-primary w-full disabled:opacity-50">
              {{ relevePdfLoading ? 'Génération…' : 'Générer PDF' }}
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- Création client -->
    <div v-if="creating" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg w-full max-w-sm p-6 space-y-3">
        <h2 class="font-bold text-navy text-lg">Nouveau client</h2>
        <input v-model="form.nom" placeholder="Nom *"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        <input v-model="form.telephone" placeholder="Téléphone"
          class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        <p v-if="formError" class="text-red-600 text-xs">{{ formError }}</p>
        <div class="flex gap-2 pt-2">
          <button @click="saveClient" class="btn-primary flex-1">Créer</button>
          <button @click="creating = false" class="flex-1 py-2 rounded-lg border border-slate-300 text-slate-600">Annuler</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { clientsApi } from '@/services/api'
import { formatPrice } from '@/utils/currency'

const clients = ref<any[]>([])
const loading = ref(false)
const search = ref('')
const onlyDebtors = ref(false)

const detail = ref<any>(null)
const payAmount = ref<number | null>(null)
const payMode = ref<'especes' | 'carte'>('especes')
const paying = ref(false)
const payError = ref('')
const reminding = ref(false)

const creating = ref(false)
const form = reactive({ nom: '', telephone: '' })
const formError = ref('')

let timer: ReturnType<typeof setTimeout>
function debouncedFetch() {
  clearTimeout(timer)
  timer = setTimeout(fetchClients, 300)
}

async function fetchClients() {
  loading.value = true
  try {
    const { data } = await clientsApi.list({
      search: search.value || undefined,
      debiteurs: onlyDebtors.value ? 1 : undefined,
    })
    clients.value = data
  } finally {
    loading.value = false
  }
}

async function openDetail(id: number) {
  const { data } = await clientsApi.get(id)
  detail.value = data
  payAmount.value = null
  payError.value = ''
}

async function pay() {
  if (!payAmount.value || payAmount.value <= 0) { payError.value = 'Montant invalide.'; return }
  paying.value = true
  payError.value = ''
  try {
    await clientsApi.pay(detail.value.client.id, { montant: payAmount.value, mode_paiement: payMode.value })
    await openDetail(detail.value.client.id)
    await fetchClients()
  } catch (e: any) {
    payError.value = e.response?.data?.message
      || (Object.values(e.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
      || 'Échec du paiement.'
  } finally {
    paying.value = false
  }
}

async function remind() {
  reminding.value = true
  try {
    const { data } = await clientsApi.remind(detail.value.client.id)
    if (data.wa_link) window.open(data.wa_link, '_blank')
    if (data.driver === 'twilio' && data.status === 'sent') {
      alert('Message WhatsApp envoyé au client.')
    }
  } catch (e: any) {
    alert(e.response?.data?.message || 'Impossible d\'envoyer la relance.')
  } finally {
    reminding.value = false
  }
}

function openCreate() {
  form.nom = ''
  form.telephone = ''
  formError.value = ''
  creating.value = true
}
async function saveClient() {
  if (!form.nom.trim()) { formError.value = 'Le nom est requis.'; return }
  try {
    await clientsApi.create({ nom: form.nom.trim(), telephone: form.telephone.trim() || null })
    creating.value = false
    await fetchClients()
  } catch (e: any) {
    formError.value = e.response?.data?.message || 'Échec de la création.'
  }
}

// ── Relevé de compte ─────────────────────────────────────────────────────────
const releve         = ref<any>(null)
const releveClient    = ref<any>(null)
const releveLoading   = ref(false)
const relevePdfLoading = ref(false)
const releveDebut     = ref('')
const releveFin       = ref('')
const periodPreset    = ref<'week' | 'month' | 'custom'>('month')
const periodPresets: { key: 'week' | 'month'; label: string }[] = [
  { key: 'week',  label: 'Cette semaine' },
  { key: 'month', label: 'Ce mois' },
]

function toIsoDate(d: Date): string {
  return d.toISOString().slice(0, 10)
}

function applyPeriodPreset(key: 'week' | 'month') {
  periodPreset.value = key
  const now = new Date()
  if (key === 'week') {
    const day = (now.getDay() + 6) % 7 // lundi = 0
    const monday = new Date(now)
    monday.setDate(now.getDate() - day)
    releveDebut.value = toIsoDate(monday)
    releveFin.value   = toIsoDate(now)
  } else {
    releveDebut.value = toIsoDate(new Date(now.getFullYear(), now.getMonth(), 1))
    releveFin.value   = toIsoDate(now)
  }
  fetchReleve()
}

function openReleve(c: any) {
  releveClient.value = c
  releve.value        = { sales: [], solde: 0 }
  applyPeriodPreset('month')
}

async function fetchReleve() {
  if (!releveClient.value) return
  releveLoading.value = true
  try {
    const { data } = await clientsApi.releve(releveClient.value.id, {
      debut: releveDebut.value, fin: releveFin.value,
    })
    releve.value = data
  } finally {
    releveLoading.value = false
  }
}

async function downloadRelevePdf() {
  if (!releveClient.value) return
  relevePdfLoading.value = true
  try {
    const { data } = await clientsApi.relevePdf(releveClient.value.id, {
      debut: releveDebut.value, fin: releveFin.value,
    })
    const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 10000)
  } catch {
    alert('Impossible de générer le relevé.')
  } finally {
    relevePdfLoading.value = false
  }
}

function money(v: number | string): string {
  return formatPrice(v)
}
function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR')
}
function statutLabel(s: string): string {
  return { paye: 'Payé', partiel: 'Partiel', impaye: 'Impayé', annulee: 'Annulée' }[s] ?? s
}
function statutClass(s: string): string {
  return {
    paye: 'bg-emerald-100 text-emerald-700',
    partiel: 'bg-amber-100 text-amber-700',
    impaye: 'bg-red-100 text-red-700',
    annulee: 'bg-slate-100 text-slate-500',
  }[s] ?? 'bg-slate-100 text-slate-600'
}

onMounted(fetchClients)
</script>
