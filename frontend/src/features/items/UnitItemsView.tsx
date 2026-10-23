import { useMemo, useState, type ReactNode } from 'react'
import { useQueries } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  Button,
  Card,
  EmptyState,
  ErrorState,
  Input,
  Modal,
  Select,
  Skeleton,
} from '../../components/ui'
import { getPage, keys, useList } from '../../lib/queries'
import type {
  Item,
  OperationalUnit,
  Page,
  UnitItem,
} from '../../types/api'
import { formatCurrency } from '../../utils/format'
import { UnitItemForm } from '../operational-units/UnitItemForm'

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

function ChevronIcon({ open }: { open: boolean }) {
  return (
    <SvgIcon
      className={`size-4 transition-transform duration-200 ${open ? 'rotate-90' : ''}`}
    >
      <path d="m9 18 6-6-6-6" />
    </SvgIcon>
  )
}

function PlusIcon() {
  return (
    <SvgIcon className="size-3.5">
      <path d="M12 5v14M5 12h14" />
    </SvgIcon>
  )
}

function EyeIcon() {
  return (
    <SvgIcon className="size-3.5">
      <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
      <circle cx="12" cy="12" r="2.5" />
    </SvgIcon>
  )
}

function EditIcon() {
  return (
    <SvgIcon className="size-3.5">
      <path d="M12 20h9" />
      <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
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

type UnitItemsQueryState = {
  data?: Page<UnitItem>
  isPending: boolean
  error: unknown
  refetch: () => unknown
}

function SummaryIcon({ type }: { type: string }) {
  const paths: Record<string, ReactNode> = {
    location: (
      <>
        <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16" />
        <path d="M3 21h18M9 7h1M14 7h1M9 11h1M14 11h1M10 21v-5h4v5" />
      </>
    ),
    item: (
      <>
        <path d="m4 7 8-4 8 4-8 4Z" />
        <path d="m4 7 8 4 8-4v10l-8 4-8-4Z" />
        <path d="M12 11v10" />
      </>
    ),
    plotted: (
      <>
        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
        <rect x="9" y="3" width="6" height="4" rx="2" />
        <path d="m9 14 2 2 4-4" />
      </>
    ),
    ticket: (
      <path d="M5 5a2 2 0 0 0-2 2v3a2 2 0 0 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 0 1 0-4V7a2 2 0 0 0-2-2ZM15 8v.01M15 12v.01M15 16v.01" />
    ),
  }

  return (
    <span className="items-metric-icon">
      <SvgIcon className="size-5">{paths[type]}</SvgIcon>
    </span>
  )
}

function SummaryCard({
  tone,
  label,
  value,
  tag,
  icon,
}: {
  tone: 'emerald' | 'blue' | 'violet' | 'amber'
  label: string
  value: string | number
  tag: string
  icon: string
}) {
  return (
    <article className={`items-metric items-metric-${tone}`}>
      <div className="items-metric-heading">
        <span>{label}</span>
        <SummaryIcon type={icon} />
      </div>
      <strong>{value}</strong>
      <span className="items-metric-pill">
        <i />
        {tag}
      </span>
    </article>
  )
}

function OutletSummary({
  units,
  itemQueries,
  masterItemTotal,
}: {
  units: OperationalUnit[]
  itemQueries: UnitItemsQueryState[]
  masterItemTotal?: number
}) {
  const activeUnits = units.filter((unit) => unit.is_active).length
  const outletQueries = itemQueries.filter(
    (_, index) => units[index]?.type === 'OUTLET',
  )
  const plottedTotal = outletQueries.every((query) => !query.isPending)
    ? outletQueries.reduce(
        (total, query) => total + (query.data?.meta.total ?? 0),
        0,
      )
    : '…'
  const ticketPrice = itemQueries
    .flatMap((query, index) =>
      units[index]?.type === 'TICKETING' ? (query.data?.data ?? []) : [],
    )
    .find(
      (row) =>
        row.is_active &&
        row.item.type === 'SERVICE' &&
        /ticket|tiket/i.test(`${row.item.item_code} ${row.item.name}`),
    )?.selling_price

  return (
    <section
      aria-label="Ringkasan outlet"
      className="items-metrics"
    >
      <SummaryCard
        tone="emerald"
        label="Total lokasi"
        value={units.length}
        tag={`${activeUnits} titik aktif`}
        icon="location"
      />
      <SummaryCard
        tone="blue"
        label="Total item yang ada"
        value={masterItemTotal ?? '…'}
        tag="Master item"
        icon="item"
      />
      <SummaryCard
        tone="violet"
        label="Total item diplot"
        value={plottedTotal}
        tag="Teralokasi"
        icon="plotted"
      />
      <SummaryCard
        tone="amber"
        label="Tiket masuk reguler"
        value={ticketPrice === undefined ? '—' : formatCurrency(ticketPrice)}
        tag={ticketPrice === undefined ? 'Belum diatur' : 'Tarif aktif'}
        icon="ticket"
      />
    </section>
  )
}

function UnitItemPanel({
  unit,
  manage,
  manageUnits,
  initiallyOpen,
  onEditUnit,
  items,
}: {
  unit: OperationalUnit
  manage: boolean
  manageUnits: boolean
  initiallyOpen: boolean
  onEditUnit: (unit: OperationalUnit) => void
  items: UnitItemsQueryState
}) {
  const [open, setOpen] = useState(initiallyOpen)
  const [editing, setEditing] = useState<UnitItem | 'new' | null>(null)
  const [detail, setDetail] = useState<UnitItem | null>(null)
  const rows = items.data?.data ?? []
  const panelId = `unit-items-${unit.id}`

  return (
    <Card className="overflow-hidden rounded-2xl border-slate-200/90 shadow-sm transition hover:border-slate-300">
      <div
        className={`flex flex-col gap-4 border-l-4 border-l-emerald-500 px-4 py-4 transition-colors sm:px-6 md:flex-row md:items-center md:justify-between ${
          open
            ? 'border-b border-b-emerald-200/80 bg-emerald-50/80'
            : 'bg-emerald-50/40 hover:bg-emerald-50/70'
        }`}
      >
        <button
          type="button"
          className="flex min-w-0 flex-1 items-center gap-3.5 text-left"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((current) => !current)}
        >
          <span
            className={`grid size-8 shrink-0 place-items-center rounded-lg border bg-white shadow-sm transition-colors ${
              open
                ? 'border-slate-200 text-slate-600'
                : 'border-slate-200 text-slate-400'
            }`}
          >
            <ChevronIcon open={open} />
          </span>
          <span className="min-w-0">
            <span className="flex flex-wrap items-center gap-2">
              <strong className="truncate text-base font-bold tracking-tight text-slate-900">
                {unit.name}
              </strong>
              <span className="inline-flex rounded-md border border-slate-200/70 bg-slate-100 px-2 py-0.5 text-[0.68rem] font-medium text-slate-600">
                {unit.type === 'TICKETING' ? 'Ticketing' : 'Outlet'}
              </span>
              <span
                className={`inline-flex rounded-md border px-2 py-0.5 text-[0.68rem] font-medium ${
                  open
                    ? 'border-emerald-200/70 bg-emerald-50 text-emerald-700'
                    : 'border-slate-200/70 bg-slate-100 text-slate-600'
                }`}
              >
                {items.isPending ? '…' : `${items.data?.meta.total ?? 0} item`}
              </span>
              {!unit.is_active && (
                <span className="inline-flex rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-[0.68rem] font-medium text-red-700">
                  Nonaktif
                </span>
              )}
            </span>
            <span className="mt-0.5 block truncate font-mono text-xs text-slate-400">
              {unit.code}
            </span>
          </span>
        </button>

        <div className="flex flex-wrap items-center gap-2 self-end md:self-auto">
          {manage && (
            <Button
              variant="secondary"
              className="min-h-8 rounded-lg px-3 py-1.5 text-xs text-slate-700 hover:text-emerald-700"
              onClick={() => {
                setOpen(true)
                setEditing('new')
              }}
            >
              <span className="text-emerald-600">
                <PlusIcon />
              </span>
              Tambah Item
            </Button>
          )}
          <Link
            className="btn btn-secondary min-h-8 rounded-lg px-3 py-1.5 text-xs"
            to={`/operational-units/${unit.id}`}
          >
            <span className="text-slate-500">
              <EyeIcon />
            </span>
            Detail
          </Link>
          {manageUnits && (
            <Button
              variant="secondary"
              className="min-h-8 rounded-lg border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs text-emerald-800 hover:bg-emerald-100"
              onClick={() => onEditUnit(unit)}
            >
              <span className="text-emerald-700">
                <EditIcon />
              </span>
              Edit
            </Button>
          )}
        </div>
      </div>

      {open && (
        <div id={panelId}>
          {items.error ? (
            <div className="p-4">
              <ErrorState
                error={items.error}
                retry={() => {
                  void items.refetch()
                }}
              />
            </div>
          ) : items.isPending ? (
            <Skeleton rows={3} />
          ) : rows.length ? (
            <div className="table-scroll">
              <table className="text-xs sm:text-[0.82rem]">
                <thead>
                  <tr>
                    <th className="pl-6">Kode</th>
                    <th>Nama item</th>
                    <th className="px-4 text-center">Jenis</th>
                    <th>Harga jual</th>
                    <th>Stok minimum</th>
                    <th className="px-4 text-center">Status</th>
                    <th className="px-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr
                      key={row.id}
                      className="cursor-pointer"
                      tabIndex={0}
                      aria-label={`Lihat rincian ${row.item.name}`}
                      onClick={() => setDetail(row)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault()
                          setDetail(row)
                        }
                      }}
                    >
                      <td className="pl-6 font-mono text-xs font-medium text-slate-500">
                        {row.item.item_code}
                      </td>
                      <td>
                        <strong className="text-slate-900">
                          {row.item.name}
                        </strong>
                      </td>
                      <td className="px-4 text-center">
                        <span className="flex justify-center">
                          <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                            {row.item.type === 'SERVICE' ? 'Layanan' : 'Produk'}
                          </span>
                        </span>
                      </td>
                      <td className="font-medium text-slate-900">
                        {formatCurrency(row.selling_price)}
                      </td>
                      <td className="text-slate-500">
                        {row.item.type === 'SERVICE' ? '—' : row.minimum_stock}
                      </td>
                      <td className="px-4 text-center">
                        <span className="flex justify-center">
                          <span
                            className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium ${
                              row.is_active
                                ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
                                : 'border-red-100 bg-red-50 text-red-700'
                            }`}
                          >
                            <span
                              className={`size-1.5 rounded-full ${row.is_active ? 'bg-emerald-500' : 'bg-red-500'}`}
                            />
                            {row.is_active ? 'Aktif' : 'Nonaktif'}
                          </span>
                        </span>
                      </td>
                      <td className="px-4 text-center">
                        <div className="flex justify-center gap-2">
                          <Button
                            variant="secondary"
                            className="min-h-7 rounded-lg px-2.5 py-1 text-xs"
                            aria-label={`Lihat rincian ${row.item.name}`}
                            onClick={(event) => {
                              event.stopPropagation()
                              setDetail(row)
                            }}
                          >
                            <EyeIcon />
                            Detail
                          </Button>
                          {manage && (
                            <Button
                              variant="secondary"
                              className="min-h-7 rounded-lg border-amber-200/70 bg-amber-50 px-2.5 py-1 text-xs text-amber-700 hover:bg-amber-100"
                              onClick={(event) => {
                                event.stopPropagation()
                                setEditing(row)
                              }}
                            >
                              <EditIcon />
                              Edit
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <EmptyState message="Belum ada item pada unit ini" />
          )}
        </div>
      )}

      {editing && (
        <UnitItemForm
          unitId={unit.id}
          unitName={unit.name}
          item={editing === 'new' ? undefined : editing}
          excludedItemIds={rows.map((row) => row.item.id)}
          onClose={() => setEditing(null)}
          onSaved={() => setEditing(null)}
        />
      )}

      {detail && (
        <Modal
          title="Rincian Item"
          onClose={() => setDetail(null)}
        >
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p className="font-mono text-xs font-medium text-slate-500">
              {detail.item.item_code}
            </p>
            <p className="mt-1 text-lg font-bold text-slate-900">
              {detail.item.name}
            </p>
          </div>
          <dl className="detail-grid mt-5 mb-0">
            <div>
              <dt>Outlet / Loket</dt>
              <dd>{unit.name}</dd>
            </div>
            <div>
              <dt>Jenis</dt>
              <dd>{detail.item.type === 'SERVICE' ? 'Layanan' : 'Produk'}</dd>
            </div>
            <div>
              <dt>Harga jual</dt>
              <dd>{formatCurrency(detail.selling_price)}</dd>
            </div>
            <div>
              <dt>Stok minimum</dt>
              <dd>
                {detail.item.type === 'SERVICE' ? '—' : detail.minimum_stock}
              </dd>
            </div>
            <div>
              <dt>Barcode</dt>
              <dd>{detail.item.barcode ?? '—'}</dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd>
                <span
                  className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium ${
                    detail.is_active
                      ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
                      : 'border-red-100 bg-red-50 text-red-700'
                  }`}
                >
                  <span
                    className={`size-1.5 rounded-full ${detail.is_active ? 'bg-emerald-500' : 'bg-red-500'}`}
                  />
                  {detail.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
              </dd>
            </div>
          </dl>
          <div className="form-actions">
            <Button variant="secondary" onClick={() => setDetail(null)}>
              Tutup
            </Button>
          </div>
        </Modal>
      )}
    </Card>
  )
}

export function UnitItemsView({
  manage,
  manageUnits = false,
  onEditUnit = () => undefined,
}: {
  manage: boolean
  manageUnits?: boolean
  onEditUnit?: (unit: OperationalUnit) => void
}) {
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('active')
  const units = useList<OperationalUnit>('/operational-units', {
    per_page: 100,
  })
  const masterItems = useList<Item>('/items', { per_page: 1 })
  const unitRows = useMemo(() => units.data?.data ?? [], [units.data?.data])
  const itemQueries = useQueries({
    queries: unitRows.map((unit) => {
      const path = `/operational-units/${unit.id}/items`
      const params = { per_page: 100 }

      return {
        queryKey: keys.list(path, params),
        queryFn: ({ signal }: { signal: AbortSignal }) =>
          getPage<UnitItem>(path, params, signal),
      }
    }),
  })
  const visibleUnits = useMemo(() => {
    const normalizedSearch = search.trim().toLocaleLowerCase('id')
    return unitRows
      .map((unit, index) => ({ unit, items: itemQueries[index] }))
      .filter(({ unit }) => {
        const matchesSearch =
          !normalizedSearch ||
          `${unit.code} ${unit.name} ${unit.type}`
            .toLocaleLowerCase('id')
            .includes(normalizedSearch)
        const matchesType = !type || unit.type === type
        const matchesStatus =
          !status || unit.is_active === (status === 'active')

        return matchesSearch && matchesType && matchesStatus
      })
  }, [itemQueries, search, status, type, unitRows])
  const hasFilters = search !== '' || type !== '' || status !== 'active'

  if (units.error) {
    return (
      <ErrorState
        error={units.error}
        retry={() => {
          void units.refetch()
        }}
      />
    )
  }

  if (units.isPending) return <Skeleton />

  return (
    <div className="space-y-5">
      <OutletSummary
        units={unitRows}
        itemQueries={itemQueries}
        masterItemTotal={masterItems.data?.meta.total}
      />

      <section
        aria-label="Pencarian dan filter outlet"
        className="rounded-2xl border border-slate-200/80 bg-white p-3.5 shadow-sm sm:p-4"
      >
        <div className="flex flex-col items-stretch gap-3 lg:flex-row lg:items-center">
          <label className="relative flex-1">
            <span className="sr-only">Cari outlet atau ticketing</span>
            <span className="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-slate-400">
              <SearchIcon />
            </span>
            <Input
              type="search"
              className="rounded-xl border-slate-200 bg-slate-50 py-2.5 pr-4 pl-10 placeholder:text-slate-400 focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-500/20"
              placeholder="Cari outlet atau ticketing…"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </label>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <Select
              aria-label="Filter jenis outlet"
              className="rounded-xl border-slate-200 bg-slate-50 font-medium sm:w-56"
              value={type}
              onChange={(event) => setType(event.target.value)}
            >
              <option value="">Semua jenis</option>
              <option value="OUTLET">Outlet</option>
              <option value="TICKETING">Ticketing</option>
            </Select>
            <Select
              aria-label="Filter status outlet"
              className="rounded-xl border-slate-200 bg-slate-50 font-medium sm:w-48"
              value={status}
              onChange={(event) => setStatus(event.target.value)}
            >
              <option value="">Semua status</option>
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </Select>
            <button
              type="button"
              title="Reset filter"
              aria-label="Reset filter"
              disabled={!hasFilters}
              className="hidden size-10 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-40 sm:grid"
              onClick={() => {
                setSearch('')
                setType('')
                setStatus('active')
              }}
            >
              <ResetIcon />
            </button>
          </div>
        </div>
      </section>

      <div className="space-y-3.5">
        {visibleUnits.length ? (
          visibleUnits.map(({ unit, items }, index) => (
            <UnitItemPanel
              key={unit.id}
              unit={unit}
              manage={manage}
              manageUnits={manageUnits}
              initiallyOpen={index === 0}
              onEditUnit={onEditUnit}
              items={items}
            />
          ))
        ) : (
          <Card>
            <EmptyState
              message={
                hasFilters
                  ? 'Outlet atau ticketing tidak ditemukan'
                  : 'Belum ada outlet atau ticketing'
              }
            />
          </Card>
        )}
      </div>
    </div>
  )
}
