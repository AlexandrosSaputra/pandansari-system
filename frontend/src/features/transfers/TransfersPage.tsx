import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  Badge,
  Button,
  ListContent,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { dateFilters, enumOptions } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { getResource, keys, useList } from '../../lib/queries'
import type { StockTransfer } from '../../types/api'
import { formatDateTime } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
import { TransferForm } from './TransferForm'
import { TransferDetail } from './TransferDetail'
export function TransfersPage() {
  const auth = useAuth()
  const filters = useFilters()
  const client = useQueryClient()
  const query = useList<StockTransfer>('/stock-transfers', filters.params)
  const [creating, setCreating] = useState(false)
  const [detail, setDetail] = useState<number>()
  const prefetch = (id: number) => {
    const path = `/stock-transfers/${id}`
    void client.prefetchQuery({
      queryKey: keys.detail(path),
      queryFn: ({ signal }) => getResource<StockTransfer>(path, signal),
    })
  }
  return (
    <>
      {auth.canWarehouse && auth.hasPermission('transfer.create') && (
        <div className="page-actions" aria-label="Aksi halaman">
          <Button onClick={() => setCreating(true)}>+ Buat Transfer</Button>
        </div>
      )}
      <FilterBar
        filters={filters}
        definitions={[
          {
            name: 'status',
            label: 'Status',
            options: enumOptions('PENDING', 'ACCEPTED', 'REJECTED'),
          },
          {
            name: 'destination_operational_unit_id',
            label: 'ID unit tujuan',
            type: 'number',
          },
          { name: 'created_by', label: 'ID pembuat', type: 'number' },
          ...dateFilters,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada transfer"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          {
            title: 'Nomor transfer',
            render: (row) => <strong>{row.transfer_number}</strong>,
          },
          { title: 'Sumber', render: (row) => row.source_location.name },
          {
            title: 'Tujuan',
            render: (row) =>
              row.destination.operational_unit?.name ??
              row.destination.location.name,
          },
          { title: 'Status', render: (row) => <Badge value={row.status} /> },
          { title: 'Pembuat', render: (row) => row.creator.name },
          { title: 'Penanggap', render: (row) => row.responder?.name ?? '—' },
          { title: 'Dibuat', render: (row) => formatDateTime(row.created_at) },
          {
            title: 'Aksi',
            render: (row) => (
              <Button
                variant="ghost"
                onMouseEnter={() => prefetch(row.id)}
                onFocus={() => prefetch(row.id)}
                onClick={() => setDetail(row.id)}
              >
                Detail
              </Button>
            ),
          },
        ]}
      />
      {creating && (
        <TransferForm
          onClose={() => setCreating(false)}
          onSaved={() => setCreating(false)}
        />
      )}
      {detail && (
        <TransferDetail id={detail} onClose={() => setDetail(undefined)} />
      )}
    </>
  )
}
