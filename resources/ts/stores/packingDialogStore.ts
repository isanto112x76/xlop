import { defineStore } from 'pinia'
import { ref } from 'vue'

export const usePackingDialogStore = defineStore('packingDialog', () => {
  // --- STATE ---
  const isDialogOpen = ref(false)
  const orderId = ref<number | null>(null)

  // --- ACTIONS ---
  /**
   * Otwiera dialog pakowania dla konkretnego zamówienia.
   * @param id ID zamówienia do spakowania.
   */
  function open(id: number) {
    orderId.value = id
    isDialogOpen.value = true
  }

  /**
   * Zamyka dialog pakowania.
   */
  function close() {
    isDialogOpen.value = false

    // Opóźniamy reset ID, aby dane nie zniknęły w trakcie animacji zamykania
    setTimeout(() => {
      orderId.value = null
    }, 300)
  }

  return {
    isDialogOpen,
    orderId,
    open,
    close,
  }
})
