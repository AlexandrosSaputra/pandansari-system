import { useParams } from 'react-router-dom'
import {
  Badge,
  Card,
  EmptyState,
  ErrorState,
  Pagination,
  Skeleton,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { useFilters } from '../../hooks/useFilters'
import { useDetail, useList } from '../../lib/queries'
import type {
  CatalogItem,
  OperationalUnit,
  UnitSummary,
  UnitType,
} from '../../types/api'
import { formatCurrency, formatNumber } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { useTopbarOverride } from '../../components/common/topbarOverride'
import { usePosCart } from './usePosCart'
import { CartPanel } from './CartPanel'
function TransactionWorkspace({ unit }: { unit: UnitSummary }) {
  const auth = useAuth()
  const filters = useFilters()
  const pos = usePosCart(unit.id)
  const catalog = useList<CatalogItem>('/transaction-catalog', {
    ...(auth.isSuperadmin ? { operational_unit_id: unit.id } : {}),
    search: filters.params.search,
    page: filters.params.page,
    per_page: filters.params.per_page,
  })
  const disabled =
    !pos.cart.data ||
    pos.cart.data.status === 'PAID' ||
    pos.action.isPending ||
    pos.newCart.isPending
  return (
    <div className="pos-grid">
      <div>
        <Card className="p-5">
          <div className="flex items-center justify-between">
            <h2>
              {unit.type === 'TICKETING' ? 'Katalog Ticketing' : 'Katalog Item'}
            </h2>
            <Badge value={unit.type ?? 'OUTLET'} />
          </div>
          <FilterBar filters={filters} />
          {catalog.error && (
            <ErrorState
              error={catalog.error}
              retry={() => {
                void catalog.refetch()
              }}
            />
          )}
          {catalog.isPending ? (
            <Skeleton />
          ) : catalog.data?.data.length ? (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {catalog.data.data.map((row) => (
                <button
                  type="button"
                  className="flex min-h-36 flex-col rounded-xl border border-slate-200 p-4 text-left transition-colors hover:border-emerald-500 hover:bg-emerald-50/30 disabled:cursor-not-allowed disabled:opacity-50"
                  key={row.id}
                  disabled={
                    disabled ||
                    catalog.isPlaceholderData ||
                    (row.inventory !== null &&
                      row.inventory.available_quantity <= 0)
                  }
                  onClick={() =>
                    pos.action.mutate({ type: 'add', item_id: row.item.id })
                  }
                >
                  <span className="mb-3 text-xs text-slate-400">
                    {row.item.item_code}
                  </span>
                  <span className="mb-2 text-sm font-semibold">
                    {row.item.name}
                  </span>
                  <span className="mt-auto font-semibold text-emerald-800">
                    {formatCurrency(row.selling_price)}
                  </span>
                  {row.inventory && (
                    <span className="mt-2 text-xs text-slate-500">
                      Tersedia: {formatNumber(row.inventory.available_quantity)}
                    </span>
                  )}
                </button>
              ))}
            </div>
          ) : (
            <EmptyState message="Belum ada item yang dapat ditransaksikan" />
          )}
          <Pagination
            meta={catalog.data?.meta}
            onPage={(p) => filters.set('page', String(p))}
            disabled={catalog.isPlaceholderData}
          />
        </Card>
      </div>
      <CartPanel pos={pos} />
    </div>
  )
}
const workspaceCopy: Record<
  UnitType,
  { title: string; empty: string; description: string }
> = {
  OUTLET: {
    title: 'POS',
    empty: 'Pilih unit outlet',
    description: 'Pilih unit outlet untuk memulai transaksi POS.',
  },
  TICKETING: {
    title: 'Ticketing',
    empty: 'Pilih unit ticketing',
    description: 'Pilih unit ticketing untuk memulai transaksi tiket.',
  },
}

function TransactionPage({ type }: { type: UnitType }) {
  const auth = useAuth()
  const { unitId } = useParams()
  const requestedUnitId = Number(unitId)
  const hasRequestedUnit =
    Number.isInteger(requestedUnitId) && requestedUnitId > 0
  const selected = useDetail<OperationalUnit>(
    `/operational-units/${hasRequestedUnit ? requestedUnitId : 0}`,
    auth.isSuperadmin && hasRequestedUnit,
  )
  const unit = auth.isSuperadmin ? selected.data : auth.user?.operational_unit
  const copy = workspaceCopy[type]
  const matchesWorkspace = unit?.type === type
  useTopbarOverride({
    label: copy.title,
    description: unit ? `${unit.name} · ${unit.code}` : copy.description,
  })

  if (auth.isSuperadmin && hasRequestedUnit && selected.isPending) {
    return <Skeleton />
  }

  if (auth.isSuperadmin && selected.error) {
    return (
      <ErrorState
        error={selected.error}
        retry={() => {
          void selected.refetch()
        }}
      />
    )
  }

  return (
    unit && matchesWorkspace ? (
      <TransactionWorkspace key={unit.id} unit={unit} />
    ) : (
      <Card>
        <EmptyState
          message={
            unit
              ? `${copy.title} tidak tersedia untuk unit ${unit.name}`
              : `${copy.empty} melalui menu ${copy.title}`
          }
        />
      </Card>
    )
  )
}

export function PosPage() {
  return <TransactionPage type="OUTLET" />
}

export function TicketingPage() {
  return <TransactionPage type="TICKETING" />
}
