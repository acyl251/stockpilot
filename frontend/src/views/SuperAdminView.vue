<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-navy">Plateforme StockPilot</h1>
        <p class="text-slate-500 text-sm mt-0.5">Vue super-admin — tous les tenants</p>
      </div>
      <div class="flex items-center gap-3">
        <button @click="openCreateOrg" class="bg-gold hover:bg-yellow-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
          + Nouvelle société
        </button>
        <span class="bg-violet-100 text-violet-700 text-xs font-semibold px-3 py-1.5 rounded-full">
          SUPER ADMIN
        </span>
      </div>
    </div>

    <!-- Onglets -->
    <div class="flex gap-1 border-b border-slate-200">
      <button
        @click="activeTab = 'plateforme'"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'plateforme' ? 'border-b-2 border-gold text-gold' : 'text-slate-500 hover:text-slate-700'"
      >
        Vue globale
      </button>
      <button
        @click="switchToSocietes"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'societes' ? 'border-b-2 border-gold text-gold' : 'text-slate-500 hover:text-slate-700'"
      >
        Sociétés
      </button>
      <button
        @click="switchToUsers"
        class="px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'utilisateurs' ? 'border-b-2 border-gold text-gold' : 'text-slate-500 hover:text-slate-700'"
      >
        Utilisateurs
      </button>
      <button
        @click="switchToDemos"
        class="px-4 py-2 text-sm font-medium transition-colors relative"
        :class="activeTab === 'demandes' ? 'border-b-2 border-gold text-gold' : 'text-slate-500 hover:text-slate-700'"
      >
        Demandes
        <span v-if="demoPending > 0"
          class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center">
          {{ demoPending }}
        </span>
      </button>
    </div>

    <!-- Modal Nouvelle société -->
    <div v-if="showOrgForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
        <h2 class="text-lg font-semibold text-navy mb-5">Nouvelle société</h2>
        <form @submit.prevent="submitOrg" class="space-y-4">

          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Société</p>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Nom de la société *</label>
              <input v-model="orgForm.org_nom" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Secteur</label>
              <select v-model="orgForm.org_secteur" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <option value="commerce">Commerce</option>
                <option value="restauration">Restauration</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Téléphone</label>
              <input v-model="orgForm.org_telephone" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Email de contact *</label>
              <input v-model="orgForm.org_email" type="email" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Plan *</label>
              <select v-model="orgForm.plan_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <option value="" disabled>Choisir un plan…</option>
                <option v-for="p in plans" :key="p.id" :value="p.id">
                  {{ p.nom }} — {{ formatCurrency(p.prix_mensuel) }}/mois
                </option>
              </select>
            </div>
          </div>

          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide pt-2">Compte administrateur</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Prénom *</label>
              <input v-model="orgForm.admin_prenom" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Nom *</label>
              <input v-model="orgForm.admin_nom" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Email *</label>
              <input v-model="orgForm.admin_email" type="email" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Mot de passe *</label>
              <input v-model="orgForm.admin_password" type="password" required minlength="8" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
          </div>

          <p v-if="orgError" class="text-red-500 text-xs">{{ orgError }}</p>
          <p v-if="orgSaving && orgForm.org_secteur" class="text-xs text-gold animate-pulse">
            ✨ L'IA génère le catalogue pour "{{ orgForm.org_secteur }}"…
          </p>
          <div class="flex gap-2 pt-2">
            <button type="button" @click="showOrgForm = false" class="flex-1 border rounded-lg py-2 text-sm hover:bg-slate-50">Annuler</button>
            <button type="submit" :disabled="orgSaving" class="flex-1 bg-gold hover:bg-yellow-500 text-white rounded-lg py-2 text-sm font-medium disabled:opacity-50">
              {{ orgSaving ? (orgForm.org_secteur ? 'Génération catalogue IA…' : 'Création…') : 'Créer la société' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Onglet Vue globale ─────────────────────────────────────────────── -->
    <template v-if="activeTab === 'plateforme'">
    <div v-if="loading" class="text-center py-20 text-slate-400">Chargement…</div>
    <div v-else-if="error" class="text-center py-20 text-red-500">{{ error }}</div>

    <template v-else>
      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card text-center">
          <p class="text-3xl font-bold text-navy">{{ data.kpis.orgs_actives }}</p>
          <p class="text-sm text-slate-500 mt-1">Tenants actifs</p>
          <p class="text-xs text-slate-400 mt-0.5">{{ data.kpis.orgs_inactives }} inactifs</p>
        </div>
        <div class="card text-center">
          <p class="text-3xl font-bold text-emerald-600">{{ formatCurrency(data.kpis.mrr) }}</p>
          <p class="text-sm text-slate-500 mt-1">MRR</p>
          <p class="text-xs text-slate-400 mt-0.5">ARR {{ formatCurrency(data.kpis.arr) }}</p>
        </div>
        <div class="card text-center">
          <p class="text-3xl font-bold text-navy">{{ data.kpis.total_users }}</p>
          <p class="text-sm text-slate-500 mt-1">Utilisateurs actifs</p>
          <p class="text-xs text-slate-400 mt-0.5">{{ data.kpis.total_produits }} produits</p>
        </div>
        <div class="card text-center">
          <p class="text-3xl font-bold text-navy">{{ data.kpis.total_mouvements.toLocaleString('fr') }}</p>
          <p class="text-sm text-slate-500 mt-1">Mouvements total</p>
          <p class="text-xs mt-0.5" :class="growthClass">
            {{ growth >= 0 ? '+' : '' }}{{ growth }} tenant(s) ce mois
          </p>
        </div>
      </div>

      <!-- Churn + Nouveaux tenants -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card flex flex-col items-center justify-center py-6">
          <p class="text-4xl font-bold" :class="data.kpis.churn_rate > 10 ? 'text-red-500' : 'text-emerald-600'">
            {{ data.kpis.churn_rate }}%
          </p>
          <p class="text-sm text-slate-500 mt-1">Taux d'inactivité</p>
        </div>
        <div class="card flex flex-col items-center justify-center py-6">
          <p class="text-4xl font-bold text-navy">{{ data.kpis.nouveaux_orgs_mois }}</p>
          <p class="text-sm text-slate-500 mt-1">Nouveaux ce mois</p>
        </div>
        <div class="card flex flex-col items-center justify-center py-6">
          <p class="text-4xl font-bold text-slate-400">{{ data.kpis.nouveaux_orgs_mois_precedent }}</p>
          <p class="text-sm text-slate-500 mt-1">Mois précédent</p>
        </div>
      </div>

      <!-- Plan distribution + Most active -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Plan distribution -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Répartition par plan</h3>
          <div class="space-y-3">
            <div v-for="plan in data.plan_distribution" :key="plan.id">
              <div class="flex items-center justify-between text-sm mb-1">
                <span class="font-medium text-slate-700">{{ plan.nom }}</span>
                <span class="text-slate-500">
                  {{ plan.actifs }} actifs / {{ plan.total }} total —
                  <span class="text-gold font-semibold">{{ formatCurrency(plan.prix_mensuel) }}/mois</span>
                </span>
              </div>
              <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                <div class="h-2 bg-gold rounded-full transition-all"
                  :style="{ width: planBarWidth(plan.actifs) }" />
              </div>
            </div>
            <p v-if="!data.plan_distribution.length" class="text-slate-400 text-sm">Aucun plan</p>
          </div>
        </div>

        <!-- Most active tenants -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Tenants les plus actifs <span class="text-xs font-normal text-slate-400">(30 jours)</span></h3>
          <div class="space-y-2">
            <div v-for="(tenant, i) in data.most_active as any[]" :key="tenant.id"
              class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
              <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-navy text-white text-xs flex items-center justify-center font-bold">
                  {{ i + 1 }}
                </span>
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ tenant.nom }}</p>
                  <p class="text-xs text-slate-400">{{ tenant.secteur ?? '—' }}</p>
                </div>
              </div>
              <span class="text-sm font-semibold text-navy">{{ tenant.total_mouvements }} mvt</span>
            </div>
            <p v-if="!data.most_active.length" class="text-slate-400 text-sm text-center py-4">Aucun mouvement ce mois</p>
          </div>
        </div>
      </div>

      <!-- Tenants near limits -->
      <div class="card">
        <h3 class="font-semibold text-navy mb-4">
          Tenants proches des limites
          <span class="ml-2 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">
            {{ data.near_limits.length }} concernés
          </span>
        </h3>
        <div v-if="data.near_limits.length === 0" class="text-slate-400 text-sm text-center py-6">
          Aucun tenant proche de ses limites
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-slate-400 text-xs border-b border-slate-100">
                <th class="pb-2 pr-4">Tenant</th>
                <th class="pb-2 pr-4">Plan</th>
                <th class="pb-2 pr-4">Utilisateurs</th>
                <th class="pb-2">Produits</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in data.near_limits" :key="t.id"
                class="border-b border-slate-50 last:border-0 hover:bg-slate-50 transition-colors">
                <td class="py-2 pr-4 font-medium text-slate-800">{{ t.nom }}</td>
                <td class="py-2 pr-4 text-slate-500">{{ t.plan ?? '—' }}</td>
                <td class="py-2 pr-4">
                  <span :class="t.near_users ? 'text-red-600 font-semibold' : 'text-slate-600'">
                    {{ t.users_count }} / {{ t.max_users }}
                  </span>
                </td>
                <td class="py-2">
                  <span :class="t.near_products ? 'text-red-600 font-semibold' : 'text-slate-600'">
                    {{ t.products_count }} / {{ t.max_products }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent signups + System health -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent signups -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Inscriptions récentes</h3>
          <div class="space-y-2">
            <div v-for="org in data.recent_orgs" :key="org.id"
              class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
              <div>
                <p class="text-sm font-medium text-slate-800">{{ org.nom }}</p>
                <p class="text-xs text-slate-400">{{ org.secteur ?? '—' }} · {{ org.plan?.nom ?? '—' }}</p>
              </div>
              <div class="text-right">
                <span :class="org.actif ? 'badge-stock' : 'badge-rupture'" class="text-xs px-2 py-0.5 rounded-full">
                  {{ org.actif ? 'Actif' : 'Inactif' }}
                </span>
                <p class="text-xs text-slate-400 mt-0.5">{{ formatDate(org.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- System health -->
        <div class="card">
          <h3 class="font-semibold text-navy mb-4">Santé du système</h3>
          <div class="space-y-3">
            <div v-for="(ok, service) in data.health" :key="service"
              class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
              <span class="text-sm font-medium text-slate-700 capitalize">{{ serviceLabel(service) }}</span>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full" :class="ok ? 'bg-emerald-400' : 'bg-red-400'" />
                <span class="text-sm" :class="ok ? 'text-emerald-600' : 'text-red-500'">
                  {{ ok ? 'Opérationnel' : 'Dégradé' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
    </template><!-- fin onglet plateforme -->

    <!-- ── Onglet Sociétés ────────────────────────────────────────────────── -->
    <template v-if="activeTab === 'societes'">
      <div v-if="orgsLoading" class="text-center py-20 text-slate-400">Chargement…</div>
      <div v-else-if="!orgs.length" class="text-center py-20 text-slate-400">Aucune société</div>
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div v-for="org in orgs" :key="org.id" class="card space-y-3">

          <!-- En-tête société -->
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-navy text-base">{{ org.nom }}</p>
              <p class="text-xs text-slate-400 mt-0.5">{{ org.secteur ?? '—' }}</p>
            </div>
            <span :class="org.actif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'"
              class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0">
              {{ org.actif ? 'Actif' : 'Inactif' }}
            </span>
          </div>

          <!-- Plan -->
          <div class="flex items-center justify-between text-sm bg-slate-50 rounded-lg px-3 py-2">
            <span class="text-slate-500">Plan</span>
            <span class="font-medium text-navy">
              {{ org.plan?.nom ?? '—' }}
              <span v-if="org.plan" class="text-gold font-normal text-xs ml-1">
                {{ formatCurrency(org.plan.prix_mensuel) }}/mois
              </span>
            </span>
          </div>

          <!-- Métriques -->
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="bg-slate-50 rounded-lg py-2">
              <p class="text-lg font-bold text-navy">{{ org.users_count }}</p>
              <p class="text-xs text-slate-400">Utilisateurs</p>
            </div>
            <div class="bg-slate-50 rounded-lg py-2">
              <p class="text-lg font-bold text-navy">{{ org.products_count }}</p>
              <p class="text-xs text-slate-400">Produits</p>
            </div>
            <div class="bg-slate-50 rounded-lg py-2">
              <p class="text-lg font-bold text-navy">{{ org.mouvements }}</p>
              <p class="text-xs text-slate-400">Mouvements</p>
            </div>
          </div>

          <!-- Chiffre d'affaires -->
          <div class="grid grid-cols-2 gap-2">
            <div class="bg-emerald-50 rounded-lg px-3 py-2 text-center">
              <p class="text-sm font-bold text-emerald-700">{{ formatCurrency(org.ca_mois) }}</p>
              <p class="text-xs text-emerald-500 mt-0.5">CA ce mois</p>
            </div>
            <div class="bg-slate-50 rounded-lg px-3 py-2 text-center">
              <p class="text-sm font-bold text-navy">{{ formatCurrency(org.ca_total) }}</p>
              <p class="text-xs text-slate-400 mt-0.5">CA total</p>
            </div>
          </div>

          <!-- Infos contact -->
          <div class="space-y-1 text-xs text-slate-500 pt-1 border-t border-slate-100">
            <p><span class="font-medium text-slate-600">Email :</span> {{ org.email_contact }}</p>
            <p v-if="org.telephone"><span class="font-medium text-slate-600">Tél :</span> {{ org.telephone }}</p>
            <p><span class="font-medium text-slate-600">Inscrite le :</span> {{ formatDate(org.created_at) }}</p>
          </div>
        </div>
      </div>
    </template><!-- fin onglet societes -->

    <!-- ── Onglet Utilisateurs ────────────────────────────────────────────── -->
    <template v-if="activeTab === 'utilisateurs'">
      <div v-if="usersLoading" class="text-center py-20 text-slate-400">Chargement…</div>
      <div v-else-if="!orgsWithUsers.length" class="text-center py-20 text-slate-400">Aucun utilisateur</div>
      <div v-else class="space-y-4">
        <div v-for="org in orgsWithUsers" :key="org.id" class="card overflow-hidden p-0">
          <!-- En-tête société -->
          <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-navy">{{ org.nom }}</span>
              <span :class="org.actif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'"
                class="text-xs font-medium px-2 py-0.5 rounded-full">
                {{ org.actif ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <span class="text-xs text-slate-400">{{ org.users.length }} utilisateur(s)</span>
          </div>

          <!-- Tableau utilisateurs -->
          <table v-if="org.users.length" class="w-full text-sm">
            <thead class="text-slate-400 text-xs uppercase border-b border-slate-100">
              <tr>
                <th class="px-5 py-2 text-left">Nom</th>
                <th class="px-5 py-2 text-left">Email</th>
                <th class="px-5 py-2 text-left">Rôle</th>
                <th class="px-5 py-2 text-left">Statut</th>
                <th class="px-5 py-2 text-left">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="u in org.users" :key="u.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-5 py-2.5 font-medium text-slate-800">{{ u.prenom }} {{ u.nom }}</td>
                <td class="px-5 py-2.5 text-slate-500">{{ u.email }}</td>
                <td class="px-5 py-2.5">
                  <span :class="roleBadge(u.role)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ u.role }}
                  </span>
                </td>
                <td class="px-5 py-2.5">
                  <span :class="u.actif ? 'text-emerald-600' : 'text-red-500'" class="text-xs font-medium">
                    {{ u.actif ? 'Actif' : 'Inactif' }}
                  </span>
                </td>
                <td class="px-5 py-2.5">
                  <button
                    @click="toggleUser(u)"
                    :class="u.actif ? 'text-red-500 hover:text-red-700' : 'text-emerald-600 hover:text-emerald-800'"
                    class="text-xs font-medium hover:underline"
                  >
                    {{ u.actif ? 'Désactiver' : 'Activer' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="px-5 py-4 text-sm text-slate-400">Aucun utilisateur dans cette société.</p>
        </div>
      </div>
    </template><!-- fin onglet utilisateurs -->

    <!-- ── Onglet Demandes ───────────────────────────────────────────────── -->
    <template v-if="activeTab === 'demandes'">
      <div v-if="demosLoading" class="text-center py-20 text-slate-400">Chargement…</div>
      <div v-else-if="!demos.length" class="text-center py-20 text-slate-400">Aucune demande pour le moment.</div>
      <div v-else class="space-y-3">
        <div v-for="d in demos" :key="d.id"
          class="bg-white rounded-xl border border-slate-200 p-5 flex flex-col sm:flex-row sm:items-center gap-4">

          <!-- Infos principales -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-navy text-sm">{{ d.prenom }} {{ d.nom }}</span>
              <span class="text-slate-400 text-xs">·</span>
              <span class="text-slate-500 text-sm">{{ d.societe }}</span>
              <span v-if="d.secteur" class="text-slate-400 text-xs">— {{ d.secteur }}</span>
              <span :class="['text-xs font-bold px-2 py-0.5 rounded-full', planBadge(d.plan_souhaite)]">
                {{ d.plan_souhaite ?? 'Non précisé' }}
              </span>
            </div>
            <div class="flex items-center gap-3 mt-1 flex-wrap">
              <span class="text-sm text-slate-500">{{ d.email }}</span>
              <span v-if="d.telephone" class="text-sm text-slate-500">{{ d.telephone }}</span>
              <span class="text-xs text-slate-400">{{ formatDate(d.created_at) }}</span>
            </div>
            <p v-if="d.message" class="text-sm text-slate-600 mt-2 italic">"{{ d.message }}"</p>
          </div>

          <!-- Statut + actions -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <span :class="['text-xs font-bold px-2.5 py-1 rounded-full', statutBadge(d.statut)]">
              {{ statutLabel(d.statut) }}
            </span>
            <button v-if="d.statut === 'en_attente'"
              @click="handleDemo(d, 'traite')"
              class="text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-lg font-medium hover:bg-emerald-100">
              Créer société
            </button>
            <button v-if="d.statut === 'en_attente'"
              @click="handleDemo(d, 'rejete')"
              class="text-xs bg-red-50 text-red-600 border border-red-200 px-3 py-1.5 rounded-lg font-medium hover:bg-red-100">
              Rejeter
            </button>
            <button v-if="d.statut !== 'en_attente'"
              @click="handleDemo(d, 'en_attente')"
              class="text-xs bg-slate-50 text-slate-500 border border-slate-200 px-3 py-1.5 rounded-lg font-medium hover:bg-slate-100">
              Remettre
            </button>
          </div>
        </div>
      </div>
    </template><!-- fin onglet demandes -->

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { superAdminApi, usersApi } from '@/services/api'
import { format } from 'date-fns'
import { fr } from 'date-fns/locale'

const loading = ref(true)
const error   = ref<string | null>(null)
const data    = ref<any>({
  kpis: {},
  plan_distribution: [],
  near_limits: [],
  most_active: [],
  recent_orgs: [],
  health: {},
})

// ── Onglets ───────────────────────────────────────────────────────────────────
const activeTab   = ref<'plateforme' | 'societes' | 'utilisateurs' | 'demandes'>('plateforme')
const orgs        = ref<any[]>([])
const orgsLoading = ref(false)
const orgsWithUsers  = ref<any[]>([])
const usersLoading   = ref(false)

async function switchToSocietes() {
  activeTab.value = 'societes'
  if (orgs.value.length) return
  orgsLoading.value = true
  try {
    const { data: d } = await superAdminApi.organisations()
    orgs.value = d
  } finally {
    orgsLoading.value = false
  }
}

async function switchToUsers() {
  activeTab.value = 'utilisateurs'
  if (orgsWithUsers.value.length) return
  usersLoading.value = true
  try {
    const { data: d } = await superAdminApi.users()
    orgsWithUsers.value = d
  } finally {
    usersLoading.value = false
  }
}

// ── Demandes d'accès ──────────────────────────────────────────────────────────
const demos        = ref<any[]>([])
const demosLoading = ref(false)
const demoPending  = computed(() => demos.value.filter((d: any) => d.statut === 'en_attente').length)

async function switchToDemos() {
  activeTab.value = 'demandes'
  demosLoading.value = true
  try {
    const { data: d } = await superAdminApi.demoRequests()
    demos.value = d
  } finally {
    demosLoading.value = false
  }
}

async function handleDemo(d: any, statut: string) {
  try {
    const { data: updated } = await superAdminApi.updateDemoStatus(d.id, statut)
    d.statut = updated.statut
    if (statut === 'traite') {
      await openCreateOrg()
      // Pré-remplir avec les infos de la demande (après reset)
      orgForm.value.org_nom       = d.societe
      orgForm.value.org_secteur   = ['commerce', 'restauration'].includes(d.secteur) ? d.secteur : 'commerce'
      orgForm.value.org_email     = d.email
      orgForm.value.org_telephone = d.telephone ?? ''
      orgForm.value.admin_prenom  = d.prenom
      orgForm.value.admin_nom     = d.nom
      orgForm.value.admin_email   = d.email
    }
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

function statutBadge(statut: string) {
  return ({
    'en_attente': 'bg-amber-100 text-amber-700',
    'traite':     'bg-emerald-100 text-emerald-700',
    'rejete':     'bg-red-100 text-red-600',
  } as Record<string, string>)[statut] ?? 'bg-gray-100 text-gray-600'
}

function statutLabel(statut: string) {
  return ({ 'en_attente': 'En attente', 'traite': 'Traité', 'rejete': 'Rejeté' } as Record<string, string>)[statut] ?? statut
}

function planBadge(plan: string) {
  return ({
    'starter':    'bg-blue-100 text-blue-700',
    'pro':        'bg-amber-100 text-amber-700',
    'enterprise': 'bg-purple-100 text-purple-700',
  } as Record<string, string>)[plan] ?? 'bg-gray-100 text-gray-600'
}

function roleBadge(role: string) {
  return ({
    'admin':        'bg-purple-100 text-purple-700',
    'gestionnaire': 'bg-blue-100 text-blue-700',
    'operateur':    'bg-gray-100 text-gray-600',
  } as Record<string, string>)[role] ?? 'bg-gray-100 text-gray-600'
}

async function toggleUser(u: any) {
  const action = u.actif ? 'Désactiver' : 'Activer'
  if (!confirm(`${action} le compte de ${u.prenom} ${u.nom} ?`)) return
  try {
    const { data: d } = await usersApi.delete(u.id)
    u.actif = d.actif
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

// ── Nouvelle société ──────────────────────────────────────────────────────────
const showOrgForm = ref(false)
const orgSaving   = ref(false)
const orgError    = ref('')
const plans       = ref<any[]>([])
const orgForm     = ref({
  org_nom: '', org_secteur: 'commerce', org_email: '', org_telephone: '',
  plan_id: '' as number | '',
  admin_prenom: '', admin_nom: '', admin_email: '', admin_password: '',
})

async function openCreateOrg() {
  orgError.value = ''
  orgForm.value  = {
    org_nom: '', org_secteur: 'commerce', org_email: '', org_telephone: '',
    plan_id: '',
    admin_prenom: '', admin_nom: '', admin_email: '', admin_password: '',
  }
  if (!plans.value.length) {
    const { data: p } = await superAdminApi.plans()
    plans.value = p
  }
  showOrgForm.value = true
}

async function submitOrg() {
  orgSaving.value = true
  orgError.value  = ''
  try {
    const { data: result } = await superAdminApi.createOrg(orgForm.value)
    showOrgForm.value = false
    const res = await superAdminApi.dashboard()
    data.value = res.data
    const nb = result.nb_produits ?? 0
    if (nb > 0) {
      alert(`Société créée avec succès.\n✨ ${nb} produits importés automatiquement via l'IA pour le secteur "${orgForm.value.org_secteur}".`)
    }
  } catch (e: any) {
    orgError.value = e.response?.data?.message ?? 'Erreur lors de la création.'
  } finally {
    orgSaving.value = false
  }
}

const growth = computed(() =>
  (data.value.kpis.nouveaux_orgs_mois ?? 0) - (data.value.kpis.nouveaux_orgs_mois_precedent ?? 0)
)

const growthClass = computed(() =>
  growth.value >= 0 ? 'text-emerald-600' : 'text-red-500'
)

const maxActifs = computed(() =>
  Math.max(...(data.value.plan_distribution ?? []).map((p: any) => p.actifs), 1)
)

function planBarWidth(actifs: number): string {
  return `${(actifs / maxActifs.value) * 100}%`
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-TN', { style: 'currency', currency: 'TND' }).format(v ?? 0)
}

function formatDate(d: string | number) {
  return format(new Date(d), 'd MMM yyyy', { locale: fr })
}

function serviceLabel(key: string | number): string {
  const labels: Record<string, string> = {
    database: 'Base de données',
    cache:    'Cache (fichier)',
    api:      'API REST',
  }
  return labels[String(key)] ?? String(key)
}

onMounted(async () => {
  try {
    const res = await superAdminApi.dashboard()
    data.value = res.data
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Erreur lors du chargement.'
  } finally {
    loading.value = false
  }
})
</script>