import type { StockUrgency } from '../../types/api'

export function stockUrgencyForAvailableQuantity(
  availableQuantity: number,
  minimumStock: number,
): StockUrgency {
  if (availableQuantity <= 0) return 'OUT_OF_STOCK'
  if (availableQuantity <= minimumStock) return 'BELOW_MINIMUM'
  if (availableQuantity <= minimumStock * 2) return 'NEAR_MINIMUM'
  return 'HEALTHY'
}

const statuses: Record<
  StockUrgency,
  { label: string; colorClass: string; bars: number; description: string }
> = {
  HEALTHY: {
    label: 'Aman',
    colorClass: 'text-green-600',
    bars: 4,
    description: 'Stok tersedia lebih dari dua kali stok minimum.',
  },
  NEAR_MINIMUM: {
    label: 'Mendekati minimum',
    colorClass: 'text-amber-600',
    bars: 2,
    description: 'Stok tersedia di atas minimum, sampai dua kali stok minimum.',
  },
  BELOW_MINIMUM: {
    label: 'Stok rendah',
    colorClass: 'text-orange-600',
    bars: 1,
    description: 'Stok tersedia sudah mencapai atau berada di bawah minimum.',
  },
  OUT_OF_STOCK: {
    label: 'Habis',
    colorClass: 'text-[var(--color-danger)]',
    bars: 0,
    description: 'Tidak ada stok yang tersedia untuk dijual.',
  },
}

export function StockUrgencyIndicator({
  urgency,
  minimumStock,
  availableQuantity,
  compact = false,
}: {
  urgency?: StockUrgency
  minimumStock?: number
  availableQuantity?: number
  compact?: boolean
}) {
  const status = urgency ? statuses[urgency] : undefined

  if (!status) {
    return <span className="text-xs text-slate-500">Status belum tersedia</span>
  }

  const noMinimum = urgency === 'HEALTHY' && minimumStock === 0
  const bars =
    availableQuantity === undefined || minimumStock === undefined
      ? status.bars
      : urgency === 'OUT_OF_STOCK'
        ? 0
        : minimumStock === 0 || urgency === 'HEALTHY'
          ? 4
          : Math.max(
              1,
              Math.min(
                3,
                Math.ceil((availableQuantity / (minimumStock * 2)) * 4),
              ),
            )

  return (
    <span
      className="inline-flex shrink-0 items-center gap-2 text-xs font-medium leading-5 text-slate-600"
      title={
        noMinimum
          ? 'Stok tersedia; batas minimum bernilai nol.'
          : status.description
      }
    >
      <svg
        aria-hidden="true"
        focusable="false"
        viewBox="0 0 76 36"
        width={compact ? 48 : 56}
        height={compact ? 24 : 28}
        className={`shrink-0 ${noMinimum ? 'text-slate-500' : status.colorClass}`}
        fill="none"
      >
        {noMinimum ? (
          <path
            d="M8 11 24 4l16 7v16l-16 7-16-7V11Zm0 0 16 7 16-7M24 18v16M16 7l16 7v8"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinejoin="round"
          />
        ) : (
          <>
            <rect
              x="1"
              y="5"
              width="48"
              height="26"
              rx="5"
              stroke="currentColor"
              strokeWidth="2"
            />
            {[0, 1, 2, 3].map((bar) => (
              <rect
                key={bar}
                x={6 + bar * 10}
                y="10"
                width="8"
                height="16"
                rx="1.5"
                fill={bar < bars ? 'currentColor' : 'var(--color-border)'}
              />
            ))}
          </>
        )}
        <circle cx="65" cy="18" r="10" fill="currentColor" />
        <g stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          {urgency === 'HEALTHY' && <path d="m60 18 3 3 6-6" />}
          {urgency === 'NEAR_MINIMUM' && <path d="M65 12v12m-4-4 4 4 4-4" />}
          {urgency === 'BELOW_MINIMUM' && <path d="M65 12v6m0 5h0" />}
          {urgency === 'OUT_OF_STOCK' && <path d="m61 14 8 8m0-8-8 8" />}
        </g>
      </svg>
      <span className={compact ? 'sr-only' : undefined}>
        {noMinimum ? 'Tersedia' : status.label}
      </span>
    </span>
  )
}
