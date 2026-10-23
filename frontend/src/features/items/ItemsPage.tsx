import { useState } from 'react'
import {
  Badge,
  Button,
  ListContent,
  PageHeader,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import {
  enumOptions,
  statusFilter,
} from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { Item } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { ItemForm } from './ItemForm'
export function ItemsPage() {
  const auth = useAuth()
  const filters = useFilters()
  const query = useList<Item>('/items', filters.params)
  const [editing, setEditing] = useState<Item | 'new' | null>(null)
  const manage = auth.isSuperadmin && auth.hasPermission('item.manage')
  return (
    <>
      <PageHeader
        title="Master Item"
        description="Produk dan layanan untuk seluruh outlet dan ticketing."
        actions={
          manage && (
            <Button
              onClick={() => setEditing('new')}
            >
              + Tambah Item
            </Button>
          )
        }
      />
      <FilterBar
        filters={filters}
        definitions={[
          {
            name: 'type',
            label: 'Jenis',
            options: enumOptions('PRODUCT', 'SERVICE'),
          },
          statusFilter,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada item"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          { title: 'Kode', render: (row) => row.item_code },
          { title: 'Barcode', render: (row) => row.barcode ?? '—' },
          { title: 'Nama item', render: (row) => <strong>{row.name}</strong> },
          { title: 'Jenis', render: (row) => <Badge value={row.type} /> },
          { title: 'Satuan', render: (row) => row.uom ?? '—' },
          { title: 'Faktor pengunjung', render: (row) => row.visitor_factor },
          { title: 'Status', render: (row) => <Badge value={row.is_active} /> },
          {
            title: 'Aksi',
            render: (row) =>
              manage ? (
                <Button
                  variant="ghost"
                  onClick={() => setEditing(row)}
                >
                  Edit
                </Button>
              ) : (
                '—'
              ),
          },
        ]}
      />
      {editing && (
        <ItemForm
          item={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
          onSaved={() => setEditing(null)}
        />
      )}
    </>
  )
}
