import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useLimitStore = defineStore('limit', () => {
  const visible  = ref(false)
  const message  = ref('')
  const planName = ref('')

  function show(msg: string, plan = '') {
    message.value  = msg
    planName.value = plan
    visible.value  = true
  }

  function hide() {
    visible.value = false
  }

  return { visible, message, planName, show, hide }
})
