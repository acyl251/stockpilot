<template>
  <div class="space-y-5">
    <!-- Filtres + résumé -->
    <div class="flex flex-wrap gap-3 items-end justify-between">
      <div class="flex gap-3 flex-wrap items-end">
        <div>
          <label class="block text-xs text-slate-500 mb-1">Du</label>
          <input type="date" v-model="dateFrom" @change="fetchSales"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
        <div>
          <label class="block text-xs text-slate-500 mb-1">Au</label>
          <input type="date" v-model="dateTo" @change="fetchSales"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gold" />
        </div>
        <button @click="setToday" class="text-sm text-gold hover:underline pb-2">Aujourd'hui</button>
        <button @click="clearDates" class="text-sm text-slate-400 hover:underline pb-2">Tout</button>
        <select v-if="auth.isAdmin && pointsDeVente.length > 0"
          v-model="filterPdv" @change="() => { page = 1; fetchSales() }"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
          <option value="">Tous les points de vente</option>
          <option v-for="pdv in pointsDeVente" :key="pdv.id" :value="pdv.id">{{ pdv.nom }}</option>
        </select>
        <button @click="exportCsv" class="btn-primary text-sm py-2 px-4">Exporter CSV</button>
      </div>
      <div class="flex gap-3">
        <div class="card px-5 py-3">
          <p class="text-xs text-slate-500">Ventes</p>
          <p class="text-xl font-bold text-navy">{{ summary.nb_ventes }}</p>
        </div>
        <div v-if="summary.nb_annulees" class="card px-5 py-3">
          <p class="text-xs text-slate-500">Annulées</p>
          <p class="text-xl font-bold text-red-500">{{ summary.nb_annulees }}</p>
        </div>
        <div class="card px-5 py-3">
          <p class="text-xs text-slate-500">Chiffre d'affaires (TTC)</p>
          <p class="text-xl font-bold text-gold">{{ money(summary.ca_ttc) }}</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Ticket</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Date</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Client</th>
            <th class="text-left px-4 py-3 text-slate-600 font-semibold">Table</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Articles</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Paiement</th>
            <th class="text-center px-4 py-3 text-slate-600 font-semibold">Statut</th>
            <th class="text-right px-4 py-3 text-slate-600 font-semibold">Total TTC</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="9" class="text-center py-10 text-slate-400">Chargement…</td></tr>
          <tr v-else-if="sales.length === 0"><td colspan="9" class="text-center py-10 text-slate-400">Aucune vente sur la période.</td></tr>
          <tr v-for="s in sales" :key="s.id" class="border-b border-slate-100 hover:bg-slate-50 transition-colors"
            :class="s.statut === 'annulee' ? 'opacity-60' : ''">
            <td class="px-4 py-3 font-mono text-xs text-navy font-semibold">{{ s.numero }}</td>
            <td class="px-4 py-3 text-slate-600">{{ formatDate(s.date_vente) }}</td>
            <td class="px-4 py-3 text-slate-600">{{ s.client ? s.client.nom : '—' }}</td>
            <td class="px-4 py-3 text-slate-500 text-xs">
              <span v-if="s.restaurant_table">Table {{ s.restaurant_table.numero }}</span>
              <span v-else-if="s.type_commande === 'emporter'" class="text-sky-600">À emporter</span>
              <span v-else>—</span>
            </td>
            <td class="px-4 py-3 text-center text-slate-600">{{ s.items_count }}</td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                :class="paiementClass(s.mode_paiement)">
                {{ s.mode_paiement === 'credit' ? 'Crédit' : s.mode_paiement }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statutClass(s.statut_paiement)">
                {{ statutLabel(s.statut_paiement) }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold"
              :class="s.statut === 'annulee' ? 'text-slate-400 line-through' : 'text-navy'">{{ money(s.total_ttc) }}</td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-3">
                <button @click="openDetail(s.id)" class="text-gold hover:underline text-xs font-medium">Voir</button>
                <button @click="downloadInvoice(s)" class="text-navy hover:underline text-xs font-medium">Facture</button>
                <button v-if="s.statut !== 'annulee'" @click="cancelSale(s)"
                  class="text-red-500 hover:text-red-700 text-xs font-medium">Annuler</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-sm text-slate-500">
        <span>{{ pagination.total }} ventes</span>
        <div class="flex gap-2">
          <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Précédent</button>
          <span class="px-2 py-1">{{ pagination.current_page }} / {{ pagination.last_page || 1 }}</span>
          <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
            class="px-3 py-1 rounded border border-slate-200 disabled:opacity-40">Suivant</button>
        </div>
      </div>
    </div>

    <!-- Détail du ticket -->
    <div v-if="detail" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg w-full max-w-sm max-h-[90vh] overflow-y-auto">
        <div id="receipt" class="p-6 text-sm">
          <div class="text-center mb-4">
            <p class="font-bold text-lg">{{ orgName }}</p>
            <p class="text-slate-500">Reçu de caisse</p>
            <p class="text-slate-500">{{ detail.numero }}</p>
            <p class="text-slate-400 text-xs">{{ formatDate(detail.date_vente) }}</p>
          </div>
          <table class="w-full mb-3">
            <tbody>
              <tr v-for="it in detail.items" :key="it.id" class="align-top">
                <td class="py-1">{{ it.designation }}<br><span class="text-slate-400 text-xs">{{ Number(it.quantite) }} × {{ money(it.prix_unitaire_ttc) }}</span></td>
                <td class="py-1 text-right">{{ money(it.total_ligne_ttc) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="border-t border-dashed border-slate-300 pt-2 space-y-1">
            <div v-if="detail.statut === 'annulee'" class="text-center text-red-600 font-bold py-1">— VENTE ANNULÉE —</div>
            <div class="flex justify-between text-slate-500"><span>Total HT</span><span>{{ money(detail.total_ht) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>TVA</span><span>{{ money(detail.total_tva) }}</span></div>
            <div v-if="Number(detail.remise_montant) > 0" class="flex justify-between text-slate-500"><span>Remise</span><span>− {{ money(detail.remise_montant) }}</span></div>
            <div class="flex justify-between font-bold text-base"><span>Total TTC</span><span>{{ money(detail.total_ttc) }}</span></div>
            <div class="flex justify-between">
              <span>Paiement</span>
              <span>{{ detail.mode_paiement === 'credit' ? 'Crédit' : detail.mode_paiement === 'carte' ? 'Carte bancaire' : 'Espèces' }}</span>
            </div>
            <div v-if="detail.mode_paiement === 'carte' && detail.reference_carte" class="flex justify-between text-slate-500">
              <span>Réf. TPE</span><span class="font-mono text-xs">{{ detail.reference_carte }}</span>
            </div>
            <template v-if="detail.mode_paiement === 'especes' && detail.montant_paye">
              <div class="flex justify-between"><span>Reçu</span><span>{{ money(detail.montant_paye) }}</span></div>
              <div class="flex justify-between"><span>Rendu</span><span>{{ money(detail.monnaie_rendue) }}</span></div>
            </template>
            <template v-if="detail.mode_paiement === 'credit'">
              <div v-if="detail.client" class="flex justify-between"><span>Client</span><span>{{ detail.client.nom }}</span></div>
              <div class="flex justify-between"><span>Déjà réglé</span><span>{{ money(detail.montant_regle) }}</span></div>
              <div v-if="Number(detail.reste_a_payer) > 0" class="flex justify-between font-bold text-red-600"><span>Reste à payer</span><span>{{ money(detail.reste_a_payer) }}</span></div>
            </template>
          </div>
          <p class="text-center text-slate-400 text-xs mt-4">Merci de votre visite !</p>
        </div>
        <div class="flex gap-2 p-4 border-t border-slate-200">
          <button @click="doPrint" class="btn-primary flex-1 py-2">Imprimer</button>
          <button @click="detail = null" class="flex-1 py-2 rounded-lg border border-slate-300 text-slate-600">Fermer</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { salesApi, pointsDeVenteApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { printReceipt } from '@/utils/print'
import { formatPrice } from '@/utils/currency'

const auth = useAuthStore()
const orgName = auth.user?.organisation?.nom ?? 'StockPilot'

const sales         = ref<any[]>([])
const summary       = ref({ nb_ventes: 0, nb_annulees: 0, ca_ttc: 0 })
const pagination    = ref({ current_page: 1, last_page: 1, total: 0 })
const loading       = ref(false)
const detail        = ref<any>(null)
const pointsDeVente = ref<any[]>([])
const filterPdv     = ref<number | ''>('')

const _now     = new Date()
const today    = `${_now.getFullYear()}-${String(_now.getMonth() + 1).padStart(2, '0')}-${String(_now.getDate()).padStart(2, '0')}`
const dateFrom = ref(today)
const dateTo   = ref(today)
const page     = ref(1)

async function fetchSales() {
  loading.value = true
  try {
    const { data } = await salesApi.list({
      date_from:         dateFrom.value || undefined,
      date_to:           dateTo.value || undefined,
      page:              page.value,
      point_de_vente_id: filterPdv.value || undefined,
    })
    sales.value = data.data
    summary.value = data.summary ?? { nb_ventes: 0, nb_annulees: 0, ca_ttc: 0 }
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } finally {
    loading.value = false
  }
}

function changePage(p: number) {
  if (p < 1 || p > pagination.value.last_page) return
  page.value = p
  fetchSales()
}
function setToday() {
  dateFrom.value = today
  dateTo.value = today
  page.value = 1
  fetchSales()
}
function clearDates() {
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  fetchSales()
}

async function openDetail(id: number) {
  const { data } = await salesApi.get(id)
  detail.value = data
}

async function cancelSale(s: any) {
  if (!confirm(`Annuler la vente ${s.numero} ? Le stock sera réapprovisionné.`)) return
  try {
    await salesApi.cancel(s.id)
    await fetchSales()
  } catch (e: any) {
    alert(e.response?.data?.message
      || (Object.values(e.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
      || "Échec de l'annulation.")
  }
}

async function exportCsv() {
  const { data } = await salesApi.export({
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  })
  const url = URL.createObjectURL(new Blob([data], { type: 'text/csv' }))
  const a = document.createElement('a')
  a.href = url
  a.download = `ventes_${dateFrom.value || 'tout'}_${dateTo.value || ''}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

async function downloadInvoice(s: any) {
  try {
    const { data } = await salesApi.invoice(s.id)
    const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 10000)
    // Le numéro de facture vient d'être attribué côté serveur → rafraîchir.
    fetchSales()
  } catch {
    alert('Impossible de générer la facture.')
  }
}

function money(v: number | string): string {
  return formatPrice(v)
}
function formatDate(d: string): string {
  return new Date(d).toLocaleString('fr-FR')
}
function statutLabel(s: string): string {
  return ({ paye: 'Payé', partiel: 'Partiel', impaye: 'Impayé', annulee: 'Annulée' } as Record<string, string>)[s] ?? s
}
function statutClass(s: string): string {
  return ({
    paye: 'bg-emerald-100 text-emerald-700',
    partiel: 'bg-amber-100 text-amber-700',
    impaye: 'bg-red-100 text-red-700',
    annulee: 'bg-slate-100 text-slate-500',
  } as Record<string, string>)[s] ?? 'bg-slate-100 text-slate-600'
}
function paiementClass(m: string): string {
  if (m === 'carte') return 'bg-indigo-100 text-indigo-700'
  if (m === 'credit') return 'bg-amber-100 text-amber-700'
  return 'bg-emerald-100 text-emerald-700'
}
function doPrint() {
  if (!detail.value) return
  printReceipt(detail.value, {
    orgNom:       auth.user?.organisation?.nom,
    orgAdresse:   auth.user?.organisation?.adresse,
    orgTelephone: auth.user?.organisation?.telephone,
  })
}

onMounted(async () => {
  if (auth.isAdmin) {
    try { const { data } = await pointsDeVenteApi.list(); pointsDeVente.value = data } catch {}
  }
  fetchSales()
})
</script>
