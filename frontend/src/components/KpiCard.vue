<template>
  <div class="card card-hover flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl flex-shrink-0" :class="iconBg">
      {{ icon }}
    </div>
    <div>
      <p class="text-[28px] leading-tight font-bold" :class="valueClass">{{ value }}</p>
      <p class="text-text-muted text-[13px]">{{ label }}</p>
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

const color = computed(() => props.color ?? 'navy')

const valueClass = computed(() => ({
  navy:  'text-navy',
  gold:  'text-gold',
  red:   'text-red-600',
  amber: 'text-amber-600',
  green: 'text-emerald-600',
}[color.value]))

const iconBg = computed(() => ({
  navy:  'bg-slate-100',
  gold:  'bg-amber-100',
  red:   'bg-red-100',
  amber: 'bg-amber-100',
  green: 'bg-emerald-100',
}[color.value]))
</script>
