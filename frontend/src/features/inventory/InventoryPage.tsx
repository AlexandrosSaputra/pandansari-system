import { useState } from 'react'
import {
  Button,
  EmptyState,
  ListContent,
  type Column,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { StockUrgencyIndicator } from '../../components/ui/StockUrgencyIndicator'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { InventoryBalance, OperationalUnit } from '../../types/api'
import {
  formatCurrency,
  formatDateTime,
  formatNumber,
} from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { StockForm } from './StockForm'
import { UnitSelector } from './UnitSelector'
import { useTopbarOverride } from '../../components/common/topbarOverride'
export function InventoryPage() {
  const auth = useAuth()
  const filters = useFilters()
  const [tab, setTab] = useState(auth.canWarehouse ? 'warehouse' : 'unit')
  const [unit, setUnit] = useState<OperationalUnit>()
  const unitId =
    auth.user?.role?.slug === 'admin'
      ? auth.user.operational_unit?.id
      : unit?.id
  const path =
    tab === 'warehouse'
      ? '/inventory/warehouse'
      : `/operational-units/${unitId}/inventory`
  const query = useList<InventoryBalance>(
    path,
    {
      search: filters.params.search,
      page: filters.params.page,
      per_page: filters.params.per_page,
    },
    tab === 'warehouse' || !!unitId,
  )
  const [stock, setStock] = useState<'in' | 'out' | null>(null)
  useTopbarOverride({
    description:
      tab === 'warehouse'
        ? 'Gudang Utama · Saldo stok produk terkini.'
        : `${auth.user?.operational_unit?.name ?? unit?.name ?? 'Unit operasional'} · Inventori produk aktif.`,
  })
  const columns: Column<InventoryBalance>[] = [
    { title: 'Kode', render: (row) => row.item.item_code },
    { title: 'Item', render: (row) => <strong>{row.item.name}</strong> },
  ]
  if (tab === 'unit')
    columns.push(
      {
        title: 'Harga jual',
        render: (row) => formatCurrency(row.selling_price),
      },
      {
        title: 'Stok minimum',
        render: (row) => formatNumber(row.minimum_stock),
      },
    )
  columns.push(
    {
      title: 'Stok fisik',
      render: (row) => formatNumber(row.quantity_on_hand),
    },
    {
      title: 'Direservasi',
      render: (row) => formatNumber(row.quantity_reserved),
    },
    {
      title: 'Tersedia',
      render: (row) => <strong>{formatNumber(row.available_quantity)}</strong>,
    },
  )
  if (tab === 'unit')
    columns.push({
      title: 'Status stok',
      render: (row) => (
        <StockUrgencyIndicator
          urgency={row.stock_urgency}
          minimumStock={row.minimum_stock}
          availableQuantity={row.available_quantity}
        />
      ),
    })
  columns.push({
    title: 'Diperbarui',
    render: (row) => formatDateTime(row.updated_at),
  })
  return (
    <>
      {auth.canWarehouse &&
        tab === 'warehouse' &&
        (auth.hasPermission('inventory.stock-in') ||
          auth.hasPermission('inventory.stock-out')) && (
          <div className="page-actions" aria-label="Aksi halaman">
            {auth.hasPermission('inventory.stock-in') && (
              <Button onClick={() => setStock('in')}>+ Stok Masuk</Button>
            )}
            {auth.hasPermission('inventory.stock-out') && (
              <Button variant="secondary" onClick={() => setStock('out')}>
                Stok Keluar
              </Button>
            )}
          </div>
        )}
      {auth.canWarehouse && (
        <div className="tabs">
          <Button
            variant={tab === 'warehouse' ? 'primary' : 'secondary'}
            onClick={() => {
              setTab('warehouse')
              filters.set('page', '1')
            }}
          >
            Gudang
          </Button>
          <Button
            variant={tab === 'unit' ? 'primary' : 'secondary'}
            onClick={() => {
              setTab('unit')
              filters.set('page', '1')
            }}
          >
            Unit Operasional
          </Button>
        </div>
      )}
      {tab === 'unit' && auth.canWarehouse && (
        <div className="max-w-md">
          <UnitSelector
            value={unit?.id}
            name={unit?.name}
            onChange={(value) => {
              setUnit(value)
              filters.set('page', '1')
            }}
          />
        </div>
      )}
      {tab === 'unit' && !unitId ? (
        <EmptyState message="Pilih unit untuk melihat inventori" />
      ) : (
        <>
          <FilterBar filters={filters} />
          <ListContent
            query={query}
            columns={columns}
            rowKey={(row) => row.item.id}
            empty="Belum ada stok produk"
            onPage={(p) => filters.set('page', String(p))}
            onSize={(size) => filters.set('per_page', size)}
          />
        </>
      )}
      {stock && (
        <StockForm
          direction={stock}
          onClose={() => setStock(null)}
          onSaved={() => setStock(null)}
        />
      )}
    </>
  )
}
