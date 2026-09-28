interface StatusBadgeProps {
  label: string
  tone: 'success' | 'danger'
}

const toneClasses: Record<StatusBadgeProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  danger: 'bg-rose-50 text-rose-700 ring-rose-600/20',
}

export function StatusBadge({ label, tone }: StatusBadgeProps) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${toneClasses[tone]}`}
    >
      {label}
    </span>
  )
}
