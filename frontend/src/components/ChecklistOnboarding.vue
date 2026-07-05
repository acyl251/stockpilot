<template>
  <Transition name="checklist-fade">
    <div v-if="visible" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <span class="text-xl">🚀</span>
          <div>
            <h3 class="font-semibold text-navy text-sm">Démarrez avec StockPilot</h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ completedCount }} / {{ totalCount }} étapes complétées</p>
          </div>
        </div>
        <button @click="dismiss" class="text-slate-400 hover:text-slate-600 text-lg leading-none w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition">×</button>
      </div>

      <!-- Progress bar -->
      <div class="px-5 py-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
            <div
              class="h-2 rounded-full transition-all duration-500"
              :style="{ width: progressPct + '%', background: 'linear-gradient(90deg, #F59E0B, #EAB308)' }"
            />
          </div>
          <span class="text-xs font-semibold text-gold w-8 text-right">{{ progressPct }}%</span>
        </div>
      </div>

      <!-- Steps list -->
      <div v-if="!allCompleted" class="divide-y divide-slate-50">
        <div
          v-for="step in steps"
          :key="step.id"
          class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors group"
        >
          <!-- Status icon -->
          <div class="w-5 h-5 flex-shrink-0 flex items-center justify-center">
            <svg v-if="step.completed" class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <div v-else class="w-4 h-4 rounded-full border-2 border-slate-300"></div>
          </div>

          <!-- Label -->
          <span
            class="flex-1 text-sm"
            :class="step.completed ? 'text-slate-400 line-through' : 'text-slate-700'"
          >
            {{ step.label }}
          </span>

          <!-- Action button -->
          <RouterLink
            v-if="!step.completed"
            :to="step.link"
            class="text-xs font-medium text-gold hover:text-yellow-600 border border-gold/30 hover:border-yellow-500 px-2.5 py-1 rounded-lg transition opacity-0 group-hover:opacity-100"
          >
            → Faire
          </RouterLink>
        </div>
      </div>

      <!-- Félicitations -->
      <div v-if="allCompleted" class="px-5 py-5 text-center">
        <p class="text-2xl mb-1">🎉</p>
        <p class="font-semibold text-emerald-600">Félicitations ! Votre espace est prêt.</p>
        <p class="text-xs text-slate-400 mt-1">Toutes les étapes de démarrage sont terminées.</p>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { onboardingApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'

interface Step {
  id: string
  label: string
  completed: boolean
  link: string
  visible: boolean
}

const auth = useAuthStore()

const steps          = ref<Step[]>([])
const completedCount = ref(0)
const totalCount     = ref(0)
const allCompleted   = ref(false)
const visible        = ref(false)

const progressPct = computed(() =>
  totalCount.value > 0 ? Math.round((completedCount.value / totalCount.value) * 100) : 0
)

const dismissKey = computed(() => `checklist_dismissed_${auth.user?.organisation?.id ?? 0}`)

function dismiss() {
  visible.value = false
  localStorage.setItem(dismissKey.value, 'true')
}

async function load() {
  if (!auth.isAdmin) return
  if (localStorage.getItem(dismissKey.value) === 'true') return

  try {
    const { data } = await onboardingApi.checklist()
    steps.value          = data.steps
    completedCount.value = data.completed_count
    totalCount.value     = data.total_count
    allCompleted.value   = data.all_completed

    const pendingCount = data.total_count - data.completed_count
    if (pendingCount >= 3) {
      visible.value = true
    } else if (data.all_completed) {
      visible.value = true
      setTimeout(() => { visible.value = false }, 3000)
    }
  } catch {
    // silently ignore — checklist is non-critical
  }
}

watch(allCompleted, (val) => {
  if (val && visible.value) {
    setTimeout(() => { visible.value = false }, 3000)
  }
})

onMounted(load)

defineExpose({ load })
</script>

<style scoped>
.checklist-fade-enter-active,
.checklist-fade-leave-active {
  transition: opacity 0.3s, transform 0.3s;
}
.checklist-fade-enter-from,
.checklist-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
