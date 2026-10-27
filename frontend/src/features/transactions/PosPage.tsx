import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Badge,
  Button,
  Card,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Pagination,
  Skeleton,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type {
  CatalogItem,
  OperationalUnit,
  UnitSummary,
  UnitType,
} from '../../types/api'
import { formatCurrency, formatNumber } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { UnitSelector } from '../inventory/UnitSelector'
import { useTopbarOverride } from '../../components/common/topbarOverride'
import { usePosCart } from './usePosCart'
import { CartPanel } from './CartPanel'
const barcodeSchema = z.object({
  barcode: z.string().trim().min(1, 'Masukkan barcode.').max(255),
})
function TransactionWorkspace({ unit }: { unit: UnitSummary }) {
  const auth = useAuth()
  const filters = useFilters()
  const pos = usePosCart(unit.id)
  const barcode = useForm<z.infer<typeof barcodeSchema>>({
    resolver: zodResolver(barcodeSchema),
    defaultValues: { barcode: '' },
  })
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
        {unit.scanner_enabled && unit.type !== 'TICKETING' && (
          <Card className="mb-4 p-4">
            <form
              className="flex items-end gap-3"
              onSubmit={barcode.handleSubmit(async (values) => {
                try {
                  await pos.action.mutateAsync({
                    type: 'scan',
                    barcode: values.barcode,
                  })
                  barcode.reset()
                } catch {
                  /* The shared notification center displays the backend error. */
                }
              })}
            >
              <div className="flex-1">
                <FormField
                  label="Input Barcode"
                  hint="Masukkan barcode produk untuk menambahkan ke keranjang."
                  error={barcode.formState.errors.barcode?.message}
                >
                  <Input
                    disabled={disabled}
                    autoComplete="off"
                    {...barcode.register('barcode')}
                  />
                </FormField>
              </div>
              <Button type="submit" disabled={disabled}>
                Tambah
              </Button>
            </form>
          </Card>
        )}
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
  const [selected, setSelected] = useState<OperationalUnit>()
  const unit = auth.isSuperadmin ? selected : auth.user?.operational_unit
  const copy = workspaceCopy[type]
  const matchesWorkspace = unit?.type === type
  useTopbarOverride({
    label: copy.title,
    description: unit ? `${unit.name} · ${unit.code}` : copy.description,
  })
  return (
    <>
      {auth.isSuperadmin && (
        <div className="mb-5 max-w-md">
          <UnitSelector
            value={selected?.id}
            name={selected?.name}
            type={type}
            showCode={type !== 'OUTLET'}
            onChange={setSelected}
          />
        </div>
      )}
      {unit && matchesWorkspace ? (
        <TransactionWorkspace key={unit.id} unit={unit} />
      ) : (
        <Card>
          <EmptyState
            message={
              unit
                ? `${copy.title} tidak tersedia untuk unit ${unit.name}`
                : copy.empty
            }
          />
        </Card>
      )}
    </>
  )
}

export function PosPage() {
  return <TransactionPage type="OUTLET" />
}

export function TicketingPage() {
  return <TransactionPage type="TICKETING" />
}
