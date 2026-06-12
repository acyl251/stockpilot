<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-navy">Nouveau type de produit</h2>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
      </div>

      <div class="flex-1 overflow-y-auto p-6 space-y-5">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="form-label">Nom *</label>
            <input v-model="form.nom" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Icône (emoji)</label>
            <input v-model="form.icone" class="form-input" placeholder="📦" />
          </div>
          <div>
            <label class="form-label">Description</label>
            <input v-model="form.description" class="form-input" />
          </div>
        </div>

        <!-- Attributes -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm font-semibold text-slate-600">Attributs personnalisés</label>
            <button @click="addAttr" class="text-gold text-sm font-medium hover:underline">+ Ajouter</button>
          </div>
          <div class="space-y-3">
            <div v-for="(attr, i) in form.attributs" :key="i"
              class="bg-slate-50 rounded-xl p-3 space-y-2">
              <div class="grid grid-cols-3 gap-2">
                <input v-model="attr.label" placeholder="Label" class="form-input col-span-2 text-xs py-1.5" />
                <select v-model="attr.type_donnee" class="form-input text-xs py-1.5">
                  <option value="text">Texte</option>
                  <option value="number">Nombre</option>
                  <option value="date">Date</option>
                  <option value="boolean">Oui/Non</option>
                  <option value="select">Liste</option>
                </select>
              </div>
              <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-xs text-slate-500">
                  <input v-model="attr.obligatoire" type="checkbox" class="w-3 h-3" />
                  Obligatoire
                </label>
                <button @click="removeAttr(i)" class="text-red-400 hover:text-red-600 text-xs">Supprimer</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-200">
        <button @click="$emit('close')" class="px-4 py-2 text-sm text-slate-600">Annuler</button>
        <button @click="save" :disabled="saving" class="btn-primary">
          {{ saving ? 'Enregistrement…' : 'Créer le type' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { productTypesApi } from '@/services/api'

const emit  = defineEmits(['close', 'saved'])
const saving = ref(false)

const form = ref({
  nom: '', icone: '', description: '',
  attributs: [] as any[],
})

function addAttr() {
  form.value.attributs.push({ label: '', nom: '', type_donnee: 'text', obligatoire: false })
}

function removeAttr(i: number) {
  form.value.attributs.splice(i, 1)
}

async function save() {
  // Auto-generate nom from label
  form.value.attributs = form.value.attributs.map(a => ({
    ...a,
    nom: a.nom || a.label.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, ''),
  }))

  saving.value = true
  try {
    await productTypesApi.create(form.value)
    emit('saved')
  } finally {
    saving.value = false
  }
}
</script>
