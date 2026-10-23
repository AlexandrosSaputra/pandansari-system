import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Badge,
  Button,
  Card,
  EmptyState,
  ErrorState,
  Input,
  Select,
  Skeleton,
  Table,
} from '../../components/ui'
import { useList } from '../../lib/queries'
import type { OperationalUnit, UnitItem } from '../../types/api'
import { formatCurrency } from '../../utils/format'
import { UnitItemForm } from '../operational-units/UnitItemForm'

function ChevronIcon({ open }: { open: boolean }) {
  return (
    <svg
      aria-hidden="true"
      className={`size-4 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`}
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      <path d="m9 18 6-6-6-6" />
    </svg>
  )
}

function UnitItemPanel({
  unit,
  manage,
  manageUnits,
  initiallyOpen,
  onEditUnit,
}: {
  unit: OperationalUnit
  manage: boolean
  manageUnits: boolean
  initiallyOpen: boolean
  onEditUnit: (unit: OperationalUnit) => void
}) {
  const [open, setOpen] = useState(initiallyOpen)
  const [editing, setEditing] = useState<UnitItem | 'new' | null>(null)
  const items = useList<UnitItem>(
    `/operational-units/${unit.id}/items`,
    { per_page: 100 },
  )
  const rows = items.data?.data ?? []
  const panelId = `unit-items-${unit.id}`

  return (
    <Card className="overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 bg-emerald-50/60 px-4 py-3">
        <button
          type="button"
          className="flex min-w-0 flex-1 items-center gap-3 text-left text-slate-700"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((current) => !current)}
        >
          <ChevronIcon open={open} />
          <span className="min-w-0">
            <span className="block truncate font-semibold text-slate-900">
              {unit.name}
            </span>
            <span className="mt-0.5 block text-xs text-slate-500">
              {unit.code}
            </span>
          </span>
          <Badge value={unit.type} />
          <span className="badge badge-neutral">
            {items.isPending ? '…' : `${items.data?.meta.total ?? 0} item`}
          </span>
          {!unit.is_active && <Badge value={false} />}
        </button>
        <div className="flex flex-wrap items-center gap-2">
          {manage && (
            <Button
              variant="secondary"
              onClick={() => {
                setOpen(true)
                setEditing('new')
              }}
            >
              + Tambah Item
            </Button>
          )}
          <Link
            className="btn btn-ghost"
            to={`/operational-units/${unit.id}`}
          >
            Detail
          </Link>
          {manageUnits && (
            <Button variant="ghost" onClick={() => onEditUnit(unit)}>
              Edit
            </Button>
          )}
        </div>
      </div>

      {open && (
        <div id={panelId} className="border-t border-emerald-100">
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
          ) : (
            <Table
              rows={rows}
              rowKey={(row) => row.id}
              empty="Belum ada item pada unit ini"
              columns={[
                { title: 'Kode', render: (row) => row.item.item_code },
                {
                  title: 'Nama item',
                  render: (row) => (
                    <strong className="text-slate-800">{row.item.name}</strong>
                  ),
                },
                {
                  title: 'Jenis',
                  render: (row) => <Badge value={row.item.type} />,
                },
                {
                  title: 'Harga jual',
                  render: (row) => formatCurrency(row.selling_price),
                },
                {
                  title: 'Stok minimum',
                  render: (row) =>
                    row.item.type === 'SERVICE' ? '—' : row.minimum_stock,
                },
                {
                  title: 'Status',
                  render: (row) => <Badge value={row.is_active} />,
                },
                {
                  title: 'Aksi',
                  render: (row) =>
                    manage ? (
                      <Button variant="ghost" onClick={() => setEditing(row)}>
                        Edit
                      </Button>
                    ) : (
                      '—'
                    ),
                },
              ]}
            />
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
  const visibleUnits = useMemo(() => {
    const normalizedSearch = search.trim().toLocaleLowerCase('id')
    return (units.data?.data ?? []).filter((unit) => {
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
  }, [search, status, type, units.data?.data])

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
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <Input
          type="search"
          aria-label="Cari outlet atau ticketing"
          placeholder="Cari outlet atau ticketing…"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
        <Select
          aria-label="Filter jenis outlet"
          value={type}
          onChange={(event) => setType(event.target.value)}
        >
          <option value="">Semua jenis</option>
          <option value="OUTLET">Outlet</option>
          <option value="TICKETING">Ticketing</option>
        </Select>
        <Select
          aria-label="Filter status outlet"
          value={status}
          onChange={(event) => setStatus(event.target.value)}
        >
          <option value="">Semua status</option>
          <option value="active">Aktif</option>
          <option value="inactive">Nonaktif</option>
        </Select>
      </div>
      {visibleUnits.length ? (
        visibleUnits.map((unit, index) => (
          <UnitItemPanel
            key={unit.id}
            unit={unit}
            manage={manage}
            manageUnits={manageUnits}
            initiallyOpen={index === 0}
            onEditUnit={onEditUnit}
          />
        ))
      ) : (
        <Card>
          <EmptyState
            message={
              search
                ? 'Outlet atau ticketing tidak ditemukan'
                : 'Belum ada outlet atau ticketing'
            }
          />
        </Card>
      )}
    </div>
  )
}
