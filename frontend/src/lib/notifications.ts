export const TOAST_DURATION = 5_000

export type ToastTone = 'success' | 'error' | 'info'

export type Toast = {
  id: number
  message: string
  tone: ToastTone
}

type Listener = (toasts: Toast[]) => void

let nextToastId = 1
let toasts: Toast[] = []

const listeners = new Set<Listener>()

function emit(): void {
  listeners.forEach((listener) => listener(toasts))
}

function add(message: string, tone: ToastTone): number {
  const toast = { id: nextToastId, message, tone }
  nextToastId += 1
  toasts = [...toasts, toast].slice(-3)
  emit()

  return toast.id
}

export const notifications = {
  success: (message: string) => add(message, 'success'),
  error: (message: string) => add(message, 'error'),
  info: (message: string) => add(message, 'info'),
  dismiss: (id: number): void => {
    toasts = toasts.filter((toast) => toast.id !== id)
    emit()
  },
  getSnapshot: (): Toast[] => toasts,
  subscribe: (listener: Listener): (() => void) => {
    listeners.add(listener)
    listener(toasts)

    return () => listeners.delete(listener)
  },
}
