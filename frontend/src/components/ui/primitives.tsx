import type {
  ButtonHTMLAttributes,
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'
import { apiError } from '../../lib/errors'
import { label } from '../../utils/format'

export function Button({
  variant = 'primary',
  busy,
  children,
  className = '',
  disabled,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  busy?: boolean
}) {
  return (
    <button
      type="button"
      {...props}
      disabled={disabled || busy}
      aria-busy={busy}
      className={`btn btn-${variant} ${className}`}
    >
      {busy ? 'Memproses…' : children}
    </button>
  )
}
export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`input ${props.className ?? ''}`} />
}
export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={`input ${props.className ?? ''}`} />
}
export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return (
    <textarea
      {...props}
      className={`input ${props.className ?? ''}`}
      rows={props.rows ?? 3}
    />
  )
}
export function FormField({
  label: title,
  error,
  children,
  hint,
}: {
  label: string
  error?: string
  hint?: string
  children: ReactNode
}) {
  return (
    <label className="field">
      <span>{title}</span>
      {children}
      {hint && <small className="text-slate-500">{hint}</small>}
      {error && (
        <small role="alert" className="text-red-700">
          {error}
        </small>
      )}
    </label>
  )
}
export function Card({
  children,
  className = '',
}: {
  children: ReactNode
  className?: string
}) {
  return <section className={`card ${className}`}>{children}</section>
}
export function Badge({ value }: { value: string | boolean }) {
  const text =
    typeof value === 'boolean' ? (value ? 'Aktif' : 'Nonaktif') : label(value)
  const tone = [true, 'Aktif', 'PAID', 'ACCEPTED', 'Normal', 'IN'].includes(value)
    ? 'success'
    : value === 'REJECTED'
      ? 'danger'
      : ['PENDING', 'Stok Rendah'].includes(value as string)
        ? 'warning'
        : value === 'OUTLET'
          ? 'location'
          : ['CART', 'TICKETING'].includes(value as string)
          ? 'info'
          : 'neutral'
  return <span className={`badge badge-${tone}`}>{text}</span>
}
export function EmptyState({
  message = 'Belum ada data',
  children,
}: {
  message?: string
  children?: ReactNode
}) {
  return (
    <div className="py-14 text-center">
      <div className="mx-auto mb-3 grid size-11 place-items-center rounded-xl bg-slate-100 text-xl text-slate-400">
        —
      </div>
      <p className="font-medium text-slate-600">{message}</p>
      {children}
    </div>
  )
}
export function ErrorState({
  error,
  retry,
}: {
  error: unknown
  retry?: () => void
}) {
  const detail = apiError(error)
  return (
    <div role="alert" className="notice notice-error">
      <p>{detail.message}</p>
      {Object.entries(detail.fields).map(([key, messages]) => (
        <p key={key} className="mt-1 text-sm">
          {messages.join(' ')}
        </p>
      ))}
      {retry && (
        <Button variant="secondary" className="mt-3" onClick={retry}>
          Coba lagi
        </Button>
      )}
    </div>
  )
}
export function Notice({ children }: { children: ReactNode }) {
  return (
    <div role="status" className="notice notice-success">
      {children}
    </div>
  )
}
export function Skeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div role="status" aria-label="Memuat data" className="space-y-4 p-5">
      {Array.from({ length: rows }, (_, i) => (
        <div
          key={i}
          className="h-9 animate-pulse rounded bg-slate-100 motion-reduce:animate-none"
        />
      ))}
    </div>
  )
}
