import { useState } from 'react'
import { AppLoadingScreen } from '../../components/common/AppLoadingScreen'
import { Badge, Button, ListContent } from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { dateFilters, enumOptions } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { Transaction } from '../../types/api'
import { formatCurrency, formatDateTime } from '../../utils/format'
import { TransactionDetail } from './TransactionDetail'
export function TransactionsPage() {
  const filters = useFilters()
  const query = useList<Transaction>('/transactions', filters.params)
  const [detail, setDetail] = useState<number>()

  if (query.isPending) return <AppLoadingScreen />

  return (
    <>
      <FilterBar
        filters={filters}
        definitions={[
          {
            name: 'status',
            label: 'Status',
            options: enumOptions('CART', 'PAID'),
          },
          { name: 'operational_unit_id', label: 'ID unit', type: 'number' },
          { name: 'created_by', label: 'ID pembuat', type: 'number' },
          { name: 'paid_by', label: 'ID pengonfirmasi', type: 'number' },
          ...dateFilters,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada transaksi"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          {
            title: 'Nomor transaksi',
            render: (row) => <strong>{row.transaction_number}</strong>,
          },
          { title: 'Unit', render: (row) => row.operational_unit.name },
          { title: 'Status', render: (row) => <Badge value={row.status} /> },
          { title: 'Pembuat', render: (row) => row.creator.name },
          { title: 'Pengonfirmasi', render: (row) => row.payer?.name ?? '—' },
          { title: 'Total', render: (row) => formatCurrency(row.total) },
          { title: 'Pengunjung', render: (row) => row.visitor_count },
          { title: 'Dibuat', render: (row) => formatDateTime(row.created_at) },
          { title: 'Dibayar', render: (row) => formatDateTime(row.paid_at) },
          {
            title: 'Aksi',
            render: (row) => (
              <Button variant="ghost" onClick={() => setDetail(row.id)}>
                Detail
              </Button>
            ),
          },
        ]}
      />
      {detail && (
        <TransactionDetail id={detail} onClose={() => setDetail(undefined)} />
      )}
    </>
  )
}
