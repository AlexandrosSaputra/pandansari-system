import { useEffect, useId, useRef, type ReactNode } from 'react'
import { m } from 'motion/react'
import { Button, ErrorState } from './primitives'

export function Modal({
  title,
  children,
  onClose,
  busy = false,
  wide = false,
  side = false,
}: {
  title: string
  children: ReactNode
  onClose: () => void
  busy?: boolean
  wide?: boolean
  side?: false | 'left' | 'right'
}) {
  const ref = useRef<HTMLDialogElement>(null)
  const id = useId()
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    const dialog = ref.current
    dialog?.showModal()
    const oldOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      dialog?.close()
      document.body.style.overflow = oldOverflow
      previous?.focus()
    }
  }, [])
  return (
    <dialog
      ref={ref}
      aria-labelledby={id}
      className={`modal ${wide ? 'modal-wide' : ''} ${side ? 'drawer' : ''} ${side === 'right' ? 'drawer-right' : ''}`}
      onCancel={(e) => {
        e.preventDefault()
        if (!busy) onClose()
      }}
    >
      <m.div
        initial={
          side
            ? { opacity: 0, x: side === 'right' ? 24 : -24 }
            : { opacity: 0, y: 8 }
        }
        animate={{ opacity: 1, x: 0, y: 0 }}
        transition={{ duration: 0.18 }}
      >
        <div className="modal-header">
          <h2 id={id}>{title}</h2>
          <Button
            variant="ghost"
            aria-label="Tutup dialog"
            disabled={busy}
            onClick={onClose}
          >
            ✕
          </Button>
        </div>
        <div className="modal-body">{children}</div>
      </m.div>
    </dialog>
  )
}
export function Drawer({
  title,
  children,
  onClose,
  side = 'left',
}: {
  title: string
  children: ReactNode
  onClose: () => void
  side?: 'left' | 'right'
}) {
  return (
    <Modal title={title} onClose={onClose} side={side}>
      {children}
    </Modal>
  )
}
export function ConfirmDialog({
  title,
  children,
  onConfirm,
  onClose,
  busy,
  error,
  danger,
}: {
  title: string
  children: ReactNode
  onConfirm: () => void
  onClose: () => void
  busy?: boolean
  error?: unknown
  danger?: boolean
}) {
  return (
    <Modal title={title} onClose={onClose} busy={busy}>
      <p className="text-sm leading-6 text-slate-600">{children}</p>
      {!!error && <ErrorState error={error} />}
      <div className="form-actions">
        <Button variant="secondary" disabled={busy} onClick={onClose}>
          Batal
        </Button>
        <Button
          variant={danger ? 'danger' : 'primary'}
          busy={busy}
          onClick={onConfirm}
        >
          Konfirmasi
        </Button>
      </div>
    </Modal>
  )
}
