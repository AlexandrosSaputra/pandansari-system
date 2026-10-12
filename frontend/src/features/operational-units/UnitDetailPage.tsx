import { useState } from 'react'
import { useParams } from 'react-router-dom'
import {
  Badge,
  Button,
  Card,
  ErrorState,
  ListContent,
  Notice,
  PageHeader,
  Skeleton,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { statusFilter } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useDetail, useList } from '../../lib/queries'
import type { OperationalUnit, UnitItem } from '../../types/api'
import { formatCurrency, label } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { UnitItemForm } from './UnitItemForm'
export function UnitDetailPage() {
  const { id } = useParams()
  const auth = useAuth()
  const filters = useFilters()
  const unit = useDetail<OperationalUnit>(`/operational-units/${id}`)
  const [tab, setTab] = useState('info')
  const items = useList<UnitItem>(
    `/operational-units/${id}/items`,
    filters.params,
    tab === 'items' && auth.hasPermission('item.view'),
  )
  const [editing, setEditing] = useState<UnitItem | 'new' | null>(null)
  const [saved, setSaved] = useState(false)
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
  return (
    <>
      <PageHeader
        title={data.name}
        description={`${data.code} · ${label(data.type)}`}
      />
      <div className="tabs">
        <Button
          variant={tab === 'info' ? 'primary' : 'secondary'}
          onClick={() => setTab('info')}
        >
          Informasi
        </Button>
        {auth.hasPermission('item.view') && (
          <Button
            variant={tab === 'items' ? 'primary' : 'secondary'}
            onClick={() => setTab('items')}
          >
            Child / Item
          </Button>
        )}
      </div>
      {tab === 'info' ? (
        <Card className="p-6">
          <dl className="detail-grid">
            <div>
              <dt>Kode</dt>
              <dd>{data.code}</dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd>
                <Badge value={data.is_active} />
              </dd>
            </div>
            <div>
              <dt>Scanner</dt>
              <dd>{data.scanner_enabled ? 'Diaktifkan' : 'Nonaktif'}</dd>
            </div>
            <div>
              <dt>Lokasi inventori</dt>
              <dd>{data.inventory_location?.name ?? '—'}</dd>
            </div>
          </dl>
        </Card>
      ) : (
        <>
          {saved && <Notice>Child / Item berhasil disimpan.</Notice>}
          {manage && (
            <Button
              onClick={() => {
                setSaved(false)
                setEditing('new')
              }}
            >
              + Tambah Child
            </Button>
          )}
          <FilterBar filters={filters} definitions={[statusFilter]} />
          <ListContent
            query={items}
            rowKey={(row) => row.id}
            empty="Belum ada item pada unit ini"
            onPage={(p) => filters.set('page', String(p))}
            onSize={(size) => filters.set('per_page', size)}
            columns={[
              { title: 'Kode', render: (row) => row.item.item_code },
              { title: 'Item', render: (row) => row.item.name },
              {
                title: 'Jenis',
                render: (row) => <Badge value={row.item.type} />,
              },
              {
                title: 'Harga jual',
                render: (row) => formatCurrency(row.selling_price),
              },
              { title: 'Stok minimum', render: (row) => row.minimum_stock },
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
        </>
      )}
      {editing && (
        <UnitItemForm
          unitId={data.id}
          item={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            setSaved(true)
          }}
        />
      )}
    </>
  )
}
