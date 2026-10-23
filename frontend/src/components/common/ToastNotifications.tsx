import { useEffect, useState } from 'react'
import {
  notifications,
  TOAST_DURATION,
  type Toast,
} from '../../lib/notifications'

function ToastIcon({ tone }: { tone: Toast['tone'] }) {
  if (tone === 'success') {
    return (
      <svg aria-hidden="true" fill="none" viewBox="0 0 24 24">
        <path d="m5 12 4 4L19 6" />
      </svg>
    )
  }

  if (tone === 'error') {
    return (
      <svg aria-hidden="true" fill="none" viewBox="0 0 24 24">
        <path d="M12 8v4m0 4h.01M10.3 3.9 2.5 17.4A2 2 0 0 0 4.2 20h15.6a2 2 0 0 0 1.7-2.6L13.7 3.9a2 2 0 0 0-3.4 0Z" />
      </svg>
    )
  }

  return (
    <svg aria-hidden="true" fill="none" viewBox="0 0 24 24">
      <path d="M12 8h.01M11 12h1v4h1m8-4a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
  )
}

function ToastItem({ toast }: { toast: Toast }) {
  useEffect(() => {
    const timeout = window.setTimeout(
      () => notifications.dismiss(toast.id),
      TOAST_DURATION,
    )

    return () => window.clearTimeout(timeout)
  }, [toast.id])

  return (
    <div
      role="status"
      className={`toast toast-${toast.tone}`}
      data-duration={TOAST_DURATION}
    >
      <span className="toast-icon">
        <ToastIcon tone={toast.tone} />
      </span>
      <p>{toast.message}</p>
      <button
        type="button"
        className="toast-dismiss"
        aria-label="Tutup notifikasi"
        onClick={() => notifications.dismiss(toast.id)}
      >
        <svg aria-hidden="true" fill="none" viewBox="0 0 24 24">
          <path d="m6 6 12 12M18 6 6 18" />
        </svg>
      </button>
    </div>
  )
}

export function ToastNotifications() {
  const [toasts, setToasts] = useState<Toast[]>(notifications.getSnapshot)

  useEffect(() => notifications.subscribe(setToasts), [])

  return (
    <div className="toast-viewport" aria-live="polite" aria-relevant="additions">
      {toasts.map((toast) => (
        <ToastItem key={toast.id} toast={toast} />
      ))}
    </div>
  )
}
