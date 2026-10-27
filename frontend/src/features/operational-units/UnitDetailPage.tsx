import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import {
  Button,
  EmptyState,
  ErrorState,
  Input,
  Select,
  Skeleton,
} from '../../components/ui'
import { useFilters } from '../../hooks/useFilters'
import { useDetail, useList } from '../../lib/queries'
import type {
  InventoryBalance,
  OperationalUnit,
  UnitItem,
} from '../../types/api'
import { formatCurrency, formatNumber } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { UnitItemForm } from './UnitItemForm'
import './UnitDetailPage.css'
import { useTopbarOverride } from '../../components/common/topbarOverride'

function SvgIcon({
  children,
  className = 'size-4',
}: {
  children: ReactNode
  className?: string
}) {
  return (
    <svg
      aria-hidden="true"
      className={className}
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      {children}
    </svg>
  )
}

function StoreIcon() {
  return (
    <SvgIcon className="size-5">
      <path d="m4 10 1-5h14l1 5" />
      <path d="M3 10h18v2a3 3 0 0 1-5 2.2A3 3 0 0 1 12 15a3 3 0 0 1-4 0A3 3 0 0 1 3 12Z" />
      <path d="M5 15v5h14v-5M9 20v-4h6v4" />
    </SvgIcon>
  )
}

function ListIcon() {
  return (
    <SvgIcon className="size-4">
      <path d="M9 6h11M9 12h11M9 18h11" />
      <path d="M4 6h.01M4 12h.01M4 18h.01" />
    </SvgIcon>
  )
}

function CheckIcon() {
  return (
    <SvgIcon className="size-4">
      <circle cx="12" cy="12" r="9" />
      <path d="m8 12 2.5 2.5L16 9" />
    </SvgIcon>
  )
}

function ScannerIcon() {
  return (
    <SvgIcon className="size-4">
      <path d="M4 7V5a2 2 0 0 1 2-2h2M16 3h2a2 2 0 0 1 2 2v2M20 17v2a2 2 0 0 1-2 2h-2M8 21H6a2 2 0 0 1-2-2v-2" />
      <path d="M8 8v8M11 8v8M14 8v8M17 8v8" />
    </SvgIcon>
  )
}

function PinIcon() {
  return (
    <SvgIcon className="size-4">
      <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
      <circle cx="12" cy="10" r="2.5" />
    </SvgIcon>
  )
}

function SearchIcon() {
  return (
    <SvgIcon>
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-4-4" />
    </SvgIcon>
  )
}

function ResetIcon() {
  return (
    <SvgIcon>
      <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
      <path d="M3 3v5h5" />
    </SvgIcon>
  )
}

function CopyIcon() {
  return (
    <SvgIcon className="size-3.5">
      <rect x="8" y="8" width="12" height="12" rx="2" />
      <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
    </SvgIcon>
  )
}

function PlusIcon() {
  return (
    <SvgIcon>
      <path d="M12 5v14M5 12h14" />
    </SvgIcon>
  )
}

function InventoryIcon() {
  return (
    <SvgIcon className="size-3.5">
      <path d="m4 7 8-4 8 4-8 4Z" />
      <path d="m4 7 8 4 8-4v10l-8 4-8-4Z" />
      <path d="M12 11v10" />
    </SvgIcon>
  )
}

function ItemIcon({ type }: { type: UnitItem['item']['type'] }) {
  return (
    <span
      className={`grid size-9 shrink-0 place-items-center rounded-lg ${
        type === 'SERVICE'
          ? 'bg-blue-50 text-blue-700'
          : 'bg-emerald-50 text-emerald-700'
      }`}
    >
      {type === 'SERVICE' ? (
        <SvgIcon className="size-4">
          <path d="M5 5h14v14H5z" />
          <path d="m8 12 2.5 2.5L16 9" />
        </SvgIcon>
      ) : (
        <InventoryIcon />
      )}
    </span>
  )
}

function MetricCard({
  label: title,
  value,
  detail,
  tone = 'slate',
  children,
}: {
  label: string
  value: string | number
  detail?: string
  tone?: 'slate' | 'emerald' | 'amber'
  children: ReactNode
}) {
  const toneClasses = {
    slate: 'text-slate-400',
    emerald: 'text-green-700',
    amber: 'text-amber-700',
  }
  const valueClasses = {
    slate: 'text-slate-900',
    emerald: 'text-green-700',
    amber: 'text-amber-700',
  }

  return (
    <div className={`unit-detail-metric unit-detail-metric-${tone}`}>
      <div className="unit-detail-metric-label">
        <span>
          {title}
        </span>
        <span className={toneClasses[tone]}>{children}</span>
      </div>
      <div className="unit-detail-metric-value">
        <strong className={`text-base font-bold ${valueClasses[tone]}`}>
          {value}
        </strong>
        {detail && <span className={`text-xs font-medium ${tone === 'slate' ? 'text-slate-500' : valueClasses[tone]}`}>{detail}</span>}
      </div>
    </div>
  )
}

function InfoCell({
  label: title,
  children,
}: {
  label: string
  children: ReactNode
}) {
  return (
    <div className="unit-detail-info-cell">
      <dt>{title}</dt>
      <dd>{children}</dd>
    </div>
  )
}

function ItemStatus({ active }: { active: boolean }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold ${
        active
          ? 'border-green-100 bg-green-50 text-green-700'
          : 'border-slate-200 bg-slate-100 text-slate-600'
      }`}
    >
      <span
        className={`size-1.5 rounded-full ${active ? 'bg-green-500' : 'bg-slate-400'}`}
      />
      {active ? 'Aktif' : 'Nonaktif'}
    </span>
  )
}

export function UnitDetailPage() {
  const { id } = useParams()
  const auth = useAuth()
  const filters = useFilters()
  const [search, setSearch] = useState(() => filters.value('search'))
  const [itemStatus, setItemStatus] = useState(
    () => filters.value('is_active') || '1',
  )
  const [itemType, setItemType] = useState<string | undefined>(() =>
    filters.value('type') || undefined,
  )
  const unitPath = `/operational-units/${id}`
  const itemPath = `${unitPath}/items`
  const unit = useDetail<OperationalUnit>(unitPath)
  const effectiveItemType =
    itemType ?? (unit.data?.type === 'TICKETING' ? 'SERVICE' : 'PRODUCT')
  const items = useList<UnitItem>(
    itemPath,
    {
      ...filters.params,
      is_active: itemStatus || undefined,
      type: effectiveItemType,
    },
    auth.hasPermission('item.view'),
  )
  const itemTotals = useList<UnitItem>(
    itemPath,
    { per_page: 1 },
    auth.hasPermission('item.view'),
  )
  const activeItemTotals = useList<UnitItem>(
    itemPath,
    { per_page: 1, is_active: 1 },
    auth.hasPermission('item.view'),
  )
  const inventory = useList<InventoryBalance>(
    `${unitPath}/inventory`,
    { per_page: 100 },
    auth.hasPermission('inventory.view'),
  )
  useTopbarOverride({
    label: unit.data?.name ?? 'Detail Outlet',
    description:
      'Kelola konfigurasi, katalog, dan batas stok unit operasional.',
  })
  const [editing, setEditing] = useState<UnitItem | 'new' | null>(null)
  const manage = auth.isSuperadmin && auth.hasPermission('item.manage')
  if (unit.isPending) return <Skeleton />
  if (unit.error || !unit.data)
    return (
      <ErrorState
        error={unit.error}
        retry={() => {
          void unit.refetch()
        }}
      />
    )

  const data = unit.data
  const totalItems = itemTotals.data?.meta.total
  const activeItems = activeItemTotals.data?.meta.total
  const activePercent =
    totalItems !== undefined && activeItems !== undefined
      ? totalItems === 0
        ? '0%'
        : `${Math.round((activeItems / totalItems) * 100)}%`
      : '…'
  const criticalStock = inventory.data?.data.filter((row) => row.low_stock).length
  const canReadItems = auth.hasPermission('item.view')

  return (
    <div className="unit-detail-page">
      <div className="unit-detail-shell">
        {canReadItems && (
          <section className="unit-detail-metrics" aria-label="Ringkasan outlet">
            <MetricCard
              label="Total SKU"
              value={totalItems ?? '…'}
              detail="item"
            >
              <ListIcon />
            </MetricCard>
            <MetricCard
              label="Stok kritis"
              value={criticalStock ?? '—'}
              detail={
                criticalStock === undefined
                  ? 'Akses inventori'
                  : criticalStock === 0
                    ? 'Aman'
                    : 'Perlu tindak'
              }
              tone={
                criticalStock === undefined
                  ? 'slate'
                  : criticalStock === 0
                    ? 'emerald'
                    : 'amber'
              }
            >
              <CheckIcon />
            </MetricCard>
            <div className="unit-detail-metric-wide">
              <MetricCard
                label="Koleksi aktif"
                value={activePercent}
                tone="emerald"
              >
                <CheckIcon />
              </MetricCard>
            </div>
          </section>
        )}

        <section className="unit-detail-info">
        <div className="unit-detail-info-header">
          <span className="unit-detail-info-icon">
            <StoreIcon />
          </span>
          <div className="min-w-0">
            <div className="unit-detail-info-title-row">
              <h2 className="unit-detail-info-title">Informasi Outlet</h2>
              <span className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[0.65rem] font-bold ${data.is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'}`}>
                <span className={`size-1.5 rounded-full ${data.is_active ? 'bg-green-500' : 'bg-slate-400'}`} />
                {data.is_active ? 'Operasional' : 'Nonaktif'}
              </span>
            </div>
            <p className="unit-detail-info-copy">
              Parameter otorisasi operasional dan integrasi peripheral titik kasir.
            </p>
          </div>
        </div>

        <dl className="unit-detail-info-grid">
          <InfoCell label="Kode outlet">
            <span className="flex items-center justify-between gap-2">
              <span className="inline-flex max-w-full rounded border border-slate-200 bg-white px-2.5 py-1 font-mono text-xs font-bold tracking-wide text-slate-700 shadow-sm">
                {data.code}
              </span>
              <button
                type="button"
                aria-label="Salin kode outlet"
                title="Salin kode outlet"
                className="grid size-7 shrink-0 place-items-center rounded-md text-slate-400 transition hover:bg-white hover:text-slate-700"
                onClick={() => void navigator.clipboard.writeText(data.code)}
              >
                <CopyIcon />
              </button>
            </span>
          </InfoCell>
          <InfoCell label="Status sistem">
            <span className="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <span className={`grid size-7 place-items-center rounded-md ${data.is_active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'}`}>
                {data.is_active ? <CheckIcon /> : <span aria-hidden="true">—</span>}
              </span>
              {data.is_active ? 'Aktif (POS & Kasir)' : 'Nonaktif'}
            </span>
          </InfoCell>
          <InfoCell label="Hardware scanner">
            <span className="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <span className={`grid size-7 place-items-center rounded-md ${data.scanner_enabled ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'}`}>
                <ScannerIcon />
              </span>
              {data.scanner_enabled
                ? 'Diaktifkan (USB / HID)'
                : 'Tidak diaktifkan'}
            </span>
          </InfoCell>
          <InfoCell label="Lokasi inventori">
            <span className="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-800">
              <span className="grid size-7 shrink-0 place-items-center rounded-md bg-slate-200 text-slate-600">
                <PinIcon />
              </span>
              <span className="truncate">
                {data.inventory_location?.name ?? 'Belum tersedia'}
              </span>
            </span>
          </InfoCell>
        </dl>
      </section>

      {canReadItems && (
        <section aria-labelledby="unit-items-title" className="space-y-4">
          <div className="unit-detail-items">
            <div className="unit-detail-toolbar">
              <div className="unit-detail-toolbar-filters">
                <form
                  className="unit-detail-search"
                  onSubmit={(event) => {
                    event.preventDefault()
                    filters.set('search', search)
                  }}
                >
                  <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <SearchIcon />
                  </span>
                  <Input
                    type="search"
                    className="h-10 rounded-lg border-slate-300 bg-slate-50 pr-9 pl-9 text-sm focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-500/20"
                    placeholder="Cari SKU, nama produk…"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                  />
                  {search && (
                    <button
                      type="button"
                      aria-label="Bersihkan pencarian"
                      className="absolute inset-y-0 right-2 grid size-7 place-items-center self-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                      onClick={() => {
                        setSearch('')
                        filters.set('search', '')
                      }}
                    >
                      ×
                    </button>
                  )}
                </form>
                <Select
                  aria-label="Filter status item"
                  className="unit-detail-filter unit-detail-filter-status"
                  value={itemStatus}
                  onChange={(event) => {
                    setItemStatus(event.target.value)
                    filters.set('page', '1')
                  }}
                >
                  <option value="">Semua status</option>
                  <option value="1">Aktif</option>
                  <option value="0">Nonaktif</option>
                </Select>
                <Select
                  aria-label="Filter jenis item"
                  className="unit-detail-filter unit-detail-filter-type"
                  value={effectiveItemType}
                  onChange={(event) => {
                    setItemType(event.target.value || undefined)
                    filters.set('page', '1')
                  }}
                >
                  <option value="">Semua jenis</option>
                  <option value="PRODUCT">Produk Retail</option>
                  <option value="SERVICE">Layanan / Jasa</option>
                </Select>
                <button
                  type="button"
                  aria-label="Reset filter item"
                  title="Reset filter"
                  className="unit-detail-reset"
                  onClick={() => {
                    setSearch('')
                    filters.reset()
                    setItemStatus('1')
                    setItemType(undefined)
                  }}
                >
                  <ResetIcon />
                </button>
              </div>
              <div className="flex items-center justify-between gap-3">
                <h2 id="unit-items-title" className="unit-detail-visually-hidden">
                  Item Unit
                </h2>
                {manage && (
                  <Button
                    className="unit-detail-add shadow-sm shadow-emerald-700/20"
                    onClick={() => setEditing('new')}
                  >
                    <PlusIcon />
                    Tambah Item
                  </Button>
                )}
              </div>
            </div>

            {items.error ? (
              <ErrorState
                error={items.error}
                retry={() => {
                  void items.refetch()
                }}
              />
            ) : items.isPending ? (
              <Skeleton rows={5} />
            ) : items.data?.data.length ? (
              <>
                <div className="unit-detail-table-wrap">
                  <table className="unit-detail-table">
                    <thead>
                      <tr>
                        <th className="w-12 text-center">No</th>
                        <th>Kode item</th>
                        <th>Item &amp; deskripsi</th>
                        <th className="text-center">Jenis</th>
                        <th className="text-right">Harga jual</th>
                        <th className="text-center">Stok minimum</th>
                        <th className="text-center">Status</th>
                        <th className="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.data.data.map((row, index) => (
                        <tr key={row.id}>
                          <td className="text-center font-medium text-slate-500">
                            {(items.data?.meta.from ?? 1) + index}
                          </td>
                          <td>
                            <span className="inline-flex rounded bg-slate-100 px-2 py-0.5 font-mono text-[0.68rem] font-medium text-slate-600">
                              {row.item.item_code}
                            </span>
                          </td>
                          <td>
                            <div className="flex items-center gap-3">
                              <ItemIcon type={row.item.type} />
                              <div className="min-w-0">
                                <strong className="block truncate text-sm text-slate-900">
                                  {row.item.name}
                                </strong>
                                <span className="block truncate text-[0.68rem] text-slate-500">
                                  {row.item.uom
                                    ? `Satuan: ${row.item.uom}`
                                    : row.item.type === 'SERVICE'
                                      ? 'Layanan operasional'
                                      : 'Produk terdaftar'}
                                </span>
                              </div>
                            </div>
                          </td>
                          <td className="text-center">
                            <span className="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-[0.68rem] font-medium text-blue-700">
                              {row.item.type === 'SERVICE' ? 'Layanan' : 'Produk'}
                            </span>
                          </td>
                          <td className="text-right font-semibold text-slate-900">
                            {formatCurrency(row.selling_price)}
                          </td>
                          <td className="text-center">
                            {row.item.type === 'SERVICE' ? (
                              <span className="text-slate-400">—</span>
                            ) : (
                              <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">
                                <InventoryIcon />
                                {formatNumber(row.minimum_stock)}
                              </span>
                            )}
                          </td>
                          <td className="text-center">
                            <ItemStatus active={row.is_active} />
                          </td>
                          <td className="text-center">
                            {manage ? (
                              <Button
                                variant="ghost"
                                className="min-h-7 rounded-md px-2.5 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                onClick={() => setEditing(row)}
                              >
                                Edit
                              </Button>
                            ) : (
                              '—'
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="unit-detail-pagination">
                  <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span>
                      Menampilkan{' '}
                      <strong className="text-slate-700">
                        {items.data.meta.from ?? 0}–{items.data.meta.to ?? 0}
                      </strong>{' '}
                      dari{' '}
                      <strong className="text-slate-700">
                        {items.data.meta.total}
                      </strong>{' '}
                      item unit
                    </span>
                    <span className="h-4 w-px bg-slate-200" />
                    <span>Baris:</span>
                    <Select
                      aria-label="Jumlah baris per halaman"
                      className="h-7 min-h-7 w-20 rounded-md border-slate-300 bg-slate-50 py-0 text-xs"
                      value={String(items.data.meta.per_page)}
                      onChange={(event) =>
                        filters.set('per_page', event.target.value)
                      }
                    >
                      {[15, 30, 50, 100].map((size) => (
                        <option key={size} value={size}>
                          {size} / hal
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div className="flex items-center justify-end gap-2">
                    <Button
                      variant="secondary"
                      className="min-h-8 rounded-md px-3 py-1 text-xs"
                      disabled={
                        items.isPlaceholderData ||
                        items.data.meta.current_page <= 1
                      }
                      onClick={() =>
                        filters.set(
                          'page',
                          String(items.data.meta.current_page - 1),
                        )
                      }
                    >
                      ‹ Sebelumnya
                    </Button>
                    <span className="grid size-8 place-items-center rounded-md bg-emerald-700 text-xs font-bold text-white shadow-sm">
                      {items.data.meta.current_page}
                    </span>
                    <Button
                      variant="secondary"
                      className="min-h-8 rounded-md px-3 py-1 text-xs"
                      disabled={
                        items.isPlaceholderData ||
                        items.data.meta.current_page >= items.data.meta.last_page
                      }
                      onClick={() =>
                        filters.set(
                          'page',
                          String(items.data.meta.current_page + 1),
                        )
                      }
                    >
                      Berikutnya ›
                    </Button>
                  </div>
                </div>
              </>
            ) : (
              <EmptyState message="Belum ada item pada unit ini" />
            )}
          </div>
        </section>
      )}

      {editing && (
        <UnitItemForm
          unitId={data.id}
          unitName={data.name}
          item={editing === 'new' ? undefined : editing}
          excludedItemIds={items.data?.data.map((row) => row.item.id)}
          onClose={() => setEditing(null)}
          onSaved={() => setEditing(null)}
        />
      )}
      </div>
    </div>
  )
}
