import { defineStore } from 'pinia'

// --- Definicje Typów ---

export type ToastColor = 'success' | 'error' | 'warning' | 'info' | 'primary' | 'secondary'
export type ToastPosition = 'top-right' | 'top-left' | 'top-center' | 'bottom-right' | 'bottom-left' | 'bottom-center'

// Typ dla opcji przekazywanych do metody show
export interface ToastOptions {
  text: string
  title?: string // Tytuł jest teraz opcjonalny
  color?: ToastColor
  icon?: string // Możliwość nadpisania ikony
  timeout?: number // 0 = bez limitu
  position?: ToastPosition
  showProgressBar?: boolean
  onClick?: () => void
}

// Typ dla obiektu toasta przechowywanego w stanie
export interface Toast extends ToastOptions {
  id: number
  createdAt: number
  isHiding: boolean
}

// Typ dla stanu store'a - grupuje toasty po pozycji
type ToastState = {
  [key in ToastPosition]: Toast[]
}

let toastCounter = 0

export const useToastStore = defineStore('toast', {
  state: (): { toasts: ToastState } => ({
    toasts: {
      'top-right': [],
      'top-left': [],
      'top-center': [],
      'bottom-right': [],
      'bottom-left': [],
      'bottom-center': [],
    },
  }),

  actions: {
    /**
     * Główna, rozbudowana metoda do wyświetlania powiadomień.
     * Zachowuje kompatybilność wsteczną ze starą sygnaturą `show(text, title, color)`.
     */
    show(options: ToastOptions | string, legacyTitle?: string, legacyColor?: ToastColor) {
      // ✅ ZACHOWANIE KOMPATYBILNOŚCI WSTECZNEJ
      if (typeof options === 'string') {
        return this.show({
          text: options,
          title: legacyTitle,
          color: legacyColor,
        })
      }

      const id = toastCounter++

      const defaults: ToastOptions = {
        title: '',
        color: 'info',
        timeout: 5000,
        position: 'top-right',
        showProgressBar: true,
      }

      const toast: Toast = {
        ...defaults,
        ...options,
        id,
        createdAt: Date.now(),
        isHiding: false,
      }

      // Automatyczne dopasowanie ikony do koloru, jeśli nie została podana
      if (!toast.icon) {
        if (toast.color === 'success')
          toast.icon = 'tabler-circle-check'
        else if (toast.color === 'error')
          toast.icon = 'tabler-alert-triangle'
        else if (toast.color === 'warning')
          toast.icon = 'tabler-alert-circle'
        else toast.icon = 'tabler-info-circle'
      }

      this.toasts[toast.position!].push(toast)

      if (toast.timeout && toast.timeout > 0)
        setTimeout(() => this.hide(id), toast.timeout)
    },

    /**
     * Ukrywa toast.
     */
    hide(id: number) {
      for (const position in this.toasts) {
        const toastIndex = this.toasts[position as ToastPosition].findIndex(t => t.id === id)
        if (toastIndex > -1) {
          this.toasts[position as ToastPosition][toastIndex].isHiding = true

          setTimeout(() => {
            const finalIndex = this.toasts[position as ToastPosition].findIndex(t => t.id === id)
            if (finalIndex > -1)
              this.toasts[position as ToastPosition].splice(finalIndex, 1)
          }, 500) // Czas musi pasować do czasu trwania animacji CSS

          return
        }
      }
    },
  },
})
