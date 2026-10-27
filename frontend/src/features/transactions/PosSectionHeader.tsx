import type { ReactNode } from 'react'

export function PosSectionHeader({
  kind,
  title,
  description,
  badge,
}: {
  kind: 'catalog' | 'cart'
  title: string
  description?: string
  badge: ReactNode
}) {
  return (
    <header className={`pos-panel-header pos-panel-header-${kind}`}>
      <span className="pos-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
          {kind === 'catalog' ? (
            <>
              <rect x="3" y="3" width="7" height="7" rx="1.5" />
              <rect x="14" y="3" width="7" height="7" rx="1.5" />
              <rect x="3" y="14" width="7" height="7" rx="1.5" />
              <rect x="14" y="14" width="7" height="7" rx="1.5" />
            </>
          ) : (
            <>
              <path d="M3 4h2l2.4 10.2a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7" />
              <circle cx="10" cy="20" r="1" />
              <circle cx="18" cy="20" r="1" />
            </>
          )}
        </svg>
      </span>
      <div className="pos-header-copy">
        <h2>{title}</h2>
        {description && <p>{description}</p>}
      </div>
      <div className="pos-header-badge">{badge}</div>
    </header>
  )
}
