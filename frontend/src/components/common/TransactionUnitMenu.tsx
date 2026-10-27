import { Link } from 'react-router-dom'
import { useList } from '../../lib/queries'
import type { OperationalUnit } from '../../types/api'
import type { NavigationItem } from './navigation'

export function TransactionUnitMenu({
  item,
  selectedUnitId,
  onNavigate,
}: {
  item: NavigationItem
  selectedUnitId?: number
  onNavigate?: () => void
}) {
  const query = useList<OperationalUnit>('/operational-units', {
    is_active: 1,
    type: item.workspaceType,
    page: 1,
    per_page: 100,
  })
  const activeLabel =
    item.workspaceType === 'TICKETING' ? 'Loket Aktif' : 'Kasir Aktif'

  return (
    <div
      id={`transaction-units-${item.to.slice(1)}`}
      className="sidebar-unit-menu"
      aria-label={`Unit ${item.label}`}
    >
      {query.isPending && (
        <p className="sidebar-unit-message">Memuat unit...</p>
      )}
      {query.error && (
        <button
          type="button"
          className="sidebar-unit-message sidebar-unit-retry"
          onClick={() => {
            void query.refetch()
          }}
        >
          Gagal memuat · Coba lagi
        </button>
      )}
      {query.data?.data.map((unit) => {
        const selected = selectedUnitId === unit.id

        return (
          <Link
            key={unit.id}
            to={`${item.to}/${unit.id}`}
            className={`sidebar-unit-option ${selected ? 'active' : ''}`}
            aria-current={selected ? 'page' : undefined}
            onClick={onNavigate}
          >
            <span className="sidebar-unit-dot" aria-hidden="true" />
            <span className="min-w-0 truncate">
              {unit.name}
              {selected && (
                <span className="sidebar-unit-active"> ({activeLabel})</span>
              )}
            </span>
          </Link>
        )
      })}
      {query.data?.data.length === 0 && (
        <p className="sidebar-unit-message">Unit belum tersedia.</p>
      )}
    </div>
  )
}
