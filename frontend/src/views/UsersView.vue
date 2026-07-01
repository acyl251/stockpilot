<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Utilisateurs</h1>
      <button
        v-if="auth.isAdmin && !auth.isSuperAdmin"
        @click="showForm = true"
        class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium"
      >
        + Nouvel utilisateur
      </button>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-400">Chargement…</div>

    <!-- ── Vue super admin : groupée par société ─────────────────────────── -->
    <template v-else-if="auth.isSuperAdmin">
      <div v-if="orgs.length === 0" class="text-center py-20 text-gray-400">Aucune société</div>
      <div v-else class="space-y-6">
        <div v-for="org in orgs" :key="org.id" class="bg-white rounded-xl shadow overflow-hidden">
          <!-- En-tête société -->
          <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-100">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-gray-800">{{ org.nom }}</span>
              <span :class="org.actif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600'"
                class="text-xs font-medium px-2 py-0.5 rounded-full">
                {{ org.actif ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <span class="text-xs text-gray-400">{{ org.users.length }} utilisateur(s)</span>
          </div>

          <!-- Table utilisateurs -->
          <table v-if="org.users.length" class="w-full text-sm">
            <thead class="text-gray-400 text-xs uppercase border-b border-gray-100">
              <tr>
                <th class="px-4 py-2 text-left">Nom</th>
                <th class="px-4 py-2 text-left">Email</th>
                <th class="px-4 py-2 text-left">Rôle</th>
                <th class="px-4 py-2 text-left">Statut</th>
                <th class="px-4 py-2 text-left">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="u in org.users" :key="u.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 font-medium text-gray-900">{{ u.prenom }} {{ u.nom }}</td>
                <td class="px-4 py-2 text-gray-500">{{ u.email }}</td>
                <td class="px-4 py-2">
                  <span :class="roleBadge(u.role)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ u.role }}
                  </span>
                </td>
                <td class="px-4 py-2">
                  <span :class="u.actif ? 'text-green-600' : 'text-red-500'" class="text-xs font-medium">
                    {{ u.actif ? 'Actif' : 'Inactif' }}
                  </span>
                </td>
                <td class="px-4 py-2">
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
          <p v-else class="px-5 py-4 text-sm text-gray-400">Aucun utilisateur dans cette société.</p>
        </div>
      </div>
    </template>

    <!-- ── Vue admin/gestionnaire : liste plate ──────────────────────────── -->
    <template v-else>
      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Nom</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">Rôle</th>
              <th class="px-4 py-3 text-left">Statut</th>
              <th v-if="auth.isAdmin" class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="users.length === 0">
              <td :colspan="auth.isAdmin ? 5 : 4" class="px-4 py-8 text-center text-gray-400">Aucun utilisateur</td>
            </tr>
            <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ u.prenom }} {{ u.nom }}</td>
              <td class="px-4 py-3 text-gray-600">{{ u.email }}</td>
              <td class="px-4 py-3">
                <span :class="roleBadge(u.role)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ u.role }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span :class="u.actif ? 'text-green-600' : 'text-red-500'" class="text-xs font-medium">
                  {{ u.actif ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td v-if="auth.isAdmin" class="px-4 py-3">
                <button
                  @click="deleteUser(u)"
                  class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline"
                >
                  Supprimer
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Add User Modal (admin seulement) -->
    <div v-if="showForm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold mb-4">Nouvel utilisateur</h2>
        <form @submit.prevent="submitUser" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Prénom</label>
              <input v-model="form.prenom" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Nom</label>
              <input v-model="form.nom" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <input v-model="form.email" type="email" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Mot de passe</label>
            <input v-model="form.password" type="password" required minlength="8" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Rôle</label>
            <select v-model="form.role" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
              <option value="operateur">Opérateur</option>
              <option value="gestionnaire">Gestionnaire</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <p v-if="error" class="text-red-500 text-xs">{{ error }}</p>
          <div class="flex gap-2 pt-2">
            <button type="button" @click="showForm = false" class="flex-1 border rounded-lg py-2 text-sm hover:bg-gray-50">Annuler</button>
            <button type="submit" :disabled="saving" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg py-2 text-sm font-medium disabled:opacity-50">
              {{ saving ? 'Enregistrement…' : 'Créer' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usersApi, superAdminApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const users    = ref<any[]>([])
const orgs     = ref<any[]>([])
const loading  = ref(true)
const showForm = ref(false)
const saving   = ref(false)
const error    = ref('')
const form     = ref({ prenom: '', nom: '', email: '', password: '', role: 'operateur' })

async function load() {
  try {
    if (auth.isSuperAdmin) {
      const { data } = await superAdminApi.users()
      orgs.value = data
    } else {
      const { data } = await usersApi.list()
      users.value = Array.isArray(data) ? data : data.data ?? []
    }
  } finally {
    loading.value = false
  }
}

async function toggleUser(u: any) {
  const action = u.actif ? 'Désactiver' : 'Activer'
  if (!confirm(`${action} le compte de ${u.prenom} ${u.nom} ?`)) return
  try {
    const { data } = await usersApi.update(u.id, { actif: !u.actif })
    u.actif = data.actif
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

async function deleteUser(u: any) {
  if (!confirm(`Supprimer définitivement ${u.prenom} ${u.nom} ?\nCette action est irréversible.`)) return
  try {
    await usersApi.delete(u.id)
    users.value = users.value.filter((x: any) => x.id !== u.id)
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

async function submitUser() {
  saving.value = true
  error.value  = ''
  try {
    await usersApi.create(form.value)
    showForm.value = false
    form.value = { prenom: '', nom: '', email: '', password: '', role: 'operateur' }
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Erreur lors de la création.'
  } finally {
    saving.value = false
  }
}

function roleBadge(role: string) {
  return {
    'admin':        'bg-purple-100 text-purple-700',
    'super_admin':  'bg-red-100 text-red-700',
    'gestionnaire': 'bg-blue-100 text-blue-700',
    'operateur':    'bg-gray-100 text-gray-600',
  }[role] ?? 'bg-gray-100 text-gray-600'
}

onMounted(load)
</script>