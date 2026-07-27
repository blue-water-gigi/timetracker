import { readonly, ref } from 'vue'

export type ToastTone = 'success' | 'error' | 'neutral'

export interface Toast {
  id: number
  message: string
  tone: ToastTone
}

const toasts = ref<Toast[]>([])
let nextToastId = 1

export function useToast() {
  function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  function show(message: string, tone: ToastTone = 'neutral'): void {
    const id = nextToastId++
    toasts.value.push({ id, message, tone })
    window.setTimeout(() => dismiss(id), 4500)
  }

  return {
    toasts: readonly(toasts),
    show,
    dismiss,
  }
}
