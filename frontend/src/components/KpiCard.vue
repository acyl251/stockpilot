<template>
  <div class="card flex items-center gap-4">
    <div class="text-3xl">{{ icon }}</div>
    <div>
      <p class="text-2xl font-bold" :class="valueClass">{{ value }}</p>
      <p class="text-slate-500 text-sm">{{ label }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  value: string | number
  icon: string
  color?: 'navy' | 'gold' | 'red' | 'amber' | 'green'
}>()

const valueClass = computed(() => ({
  navy:  'text-navy',
  gold:  'text-gold',
  red:   'text-red-600',
  amber: 'text-amber-600',
  green: 'text-emerald-600',
}[props.color ?? 'navy']))
</script>
