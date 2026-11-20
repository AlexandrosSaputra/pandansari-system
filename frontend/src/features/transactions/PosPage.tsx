import { useState } from 'react'
import { useParams } from 'react-router-dom'
import {
  Card,
  EmptyState,
  ErrorState,
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
import { useAuth } from '../auth/useAuth'
import { useTopbarOverride } from '../../components/common/topbarOverride'
import { usePosCart } from './usePosCart'
import { CartPanel } from './CartPanel'
import { PosCatalogCard } from './PosCatalogCard'
import { PosSectionHeader } from './PosSectionHeader'
import { PosPagination } from './PosPagination'
import { formatNumber } from '../../utils/format'
import './pos-workspace.css'

function TransactionWorkspace({ unit }: { unit: UnitSummary }) {
  const auth = useAuth()
  const filters = useFilters()
  const pos = usePosCart(unit.id, unit.scanner_enabled === true)
  const [activePanel, setActivePanel] = useState<'catalog' | 'cart'>('catalog')
  const cartQuantity =
    pos.cart.data?.items?.reduce((total, item) => total + item.quantity, 0) ?? 0
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
    <div className="pos-grid pos-workspace" data-active-panel={activePanel}>
      <div
        className="pos-panel-switch"
        role="group"
        aria-label="Tampilan transaksi"
      >
        <button
          type="button"
          aria-pressed={activePanel === 'catalog'}
          onClick={() => setActivePanel('catalog')}
        >
          Katalog
        </button>
        <button
          type="button"
          aria-pressed={activePanel === 'cart'}
          onClick={() => setActivePanel('cart')}
        >
          Keranjang <span>{formatNumber(cartQuantity)}</span>
        </button>
      </div>
      <div className="pos-catalog-column">
        <Card className="pos-catalog">
          <PosSectionHeader
            kind="catalog"
            title={unit.type === 'TICKETING' ? 'Katalog Ticketing' : 'Katalog Item'}
            description={catalog.data
              ? `${formatNumber(catalog.data.meta.total)} item dalam katalog`
              : 'Pilih item untuk transaksi'}
            badge={<span className="pos-unit-label">{unit.name}</span>}
          />
          <FilterBar filters={filters} />
          <div
            className="pos-catalog-items"
            role="region"
            aria-label="Daftar katalog"
            tabIndex={0}
          >
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
              <div className="pos-product-grid">
                {catalog.data.data.map((row) => {
                  const quantityInCart =
                    pos.cart.data?.status === 'CART'
                      ? (pos.cart.data.items?.find(
                          (line) => line.item.id === row.item.id,
                        )?.quantity ?? 0)
                      : 0
                  return (
                    <PosCatalogCard
                      key={row.id}
                      row={row}
                      quantityInCart={quantityInCart}
                      disabled={disabled || catalog.isPlaceholderData}
                      onAdd={() =>
                        pos.action.mutate({ type: 'add', item_id: row.item.id })
                      }
                    />
                  )
                })}
              </div>
            ) : (
              <EmptyState message="Belum ada item yang dapat ditransaksikan" />
            )}
          </div>
          <PosPagination
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
