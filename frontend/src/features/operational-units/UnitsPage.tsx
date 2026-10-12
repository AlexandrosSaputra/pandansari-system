import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Badge,
  Button,
  ListContent,
  Notice,
  PageHeader,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import {
  enumOptions,
  statusFilter,
} from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { OperationalUnit } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { UnitForm } from './UnitForm'
export function UnitsPage() {
  const auth = useAuth()
  const filters = useFilters()
  const query = useList<OperationalUnit>('/operational-units', filters.params)
  const [editing, setEditing] = useState<OperationalUnit | 'new' | null>(null)
  const [saved, setSaved] = useState(false)
  const manage =
    auth.isSuperadmin && auth.hasPermission('operational-unit.manage')
  return (
    <>
      <PageHeader
        title="Unit Operasional"
        description="Kelola outlet, ticketing, dan item setiap unit."
        actions={
          manage && (
            <Button
              onClick={() => {
                setSaved(false)
                setEditing('new')
              }}
            >
              + Tambah Unit
            </Button>
          )
        }
      />
      {saved && <Notice>Unit operasional berhasil disimpan.</Notice>}
      <FilterBar
        filters={filters}
        definitions={[
          {
            name: 'type',
            label: 'Jenis',
            options: enumOptions('OUTLET', 'TICKETING'),
          },
          statusFilter,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada unit operasional"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          { title: 'Kode', render: (row) => row.code },
          {
            title: 'Nama unit',
            render: (row) => (
              <Link className="link" to={`/operational-units/${row.id}`}>
                {row.name}
              </Link>
            ),
          },
          { title: 'Jenis', render: (row) => <Badge value={row.type} /> },
          {
            title: 'Scanner',
            render: (row) => (row.scanner_enabled ? 'Diaktifkan' : 'Nonaktif'),
          },
          { title: 'Status', render: (row) => <Badge value={row.is_active} /> },
          {
            title: 'Aksi',
            render: (row) => (
              <div className="flex gap-2">
                <Link
                  className="btn btn-ghost"
                  to={`/operational-units/${row.id}`}
                >
                  Detail
                </Link>
                {manage && (
                  <Button
                    variant="ghost"
                    onClick={() => {
                      setSaved(false)
                      setEditing(row)
                    }}
                  >
                    Edit
                  </Button>
                )}
              </div>
            ),
          },
        ]}
      />
      {editing && (
        <UnitForm
          unit={editing === 'new' ? undefined : editing}
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
