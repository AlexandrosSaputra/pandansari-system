import { Link } from 'react-router-dom'
import type { UseQueryResult } from '@tanstack/react-query'
import {
  Badge,
  Card,
  ErrorState,
  Skeleton,
  Table,
} from '../../../components/ui'
import type { Page, StockTransfer, Transaction } from '../../../types/api'
import { formatCurrency, formatDateTime } from '../../../utils/format'
export function RecentActivity({
  transfers,
  transactions,
  showTransfers,
  showTransactions,
}: {
  transfers: UseQueryResult<Page<StockTransfer>>
  transactions: UseQueryResult<Page<Transaction>>
  showTransfers: boolean
  showTransactions: boolean
}) {
  return (
    <div className="grid items-start gap-6 xl:grid-cols-2">
      {showTransfers && (
        <Card className="overflow-hidden">
          <div className="flex items-center justify-between p-5">
            <h2>Transfer Menunggu</h2>
            <Link className="link text-xs" to="/stock-transfers">
              Lihat semua →
            </Link>
          </div>
          {transfers.error ? (
            <ErrorState error={transfers.error} />
          ) : transfers.isPending ? (
            <Skeleton rows={3} />
          ) : (
            transfers.data && (
              <Table
                rows={transfers.data.data}
                rowKey={(row) => row.id}
                empty="Belum ada transfer menunggu"
                columns={[
                  { title: 'Nomor', render: (row) => row.transfer_number },
                  {
                    title: 'Tujuan',
                    render: (row) =>
                      row.destination.operational_unit?.name ?? '—',
                  },
                  {
                    title: 'Status',
                    render: (row) => <Badge value={row.status} />,
                  },
                ]}
              />
            )
          )}
        </Card>
      )}
      {showTransactions && (
        <Card className="overflow-hidden">
          <div className="flex items-center justify-between p-5">
            <h2>Transaksi Terbaru</h2>
            <Link className="link text-xs" to="/transactions">
              Lihat semua →
            </Link>
          </div>
          {transactions.error ? (
            <ErrorState error={transactions.error} />
          ) : transactions.isPending ? (
            <Skeleton rows={3} />
          ) : (
            transactions.data && (
              <Table
                rows={transactions.data.data}
                rowKey={(row) => row.id}
                empty="Belum ada transaksi"
                columns={[
                  {
                    title: 'Transaksi',
                    render: (row) => (
                      <>
                        {row.transaction_number}
                        <small className="mt-1 block text-slate-400">
                          {formatDateTime(row.paid_at)}
                        </small>
                      </>
                    ),
                  },
                  {
                    title: 'Unit',
                    render: (row) => row.operational_unit.name,
                  },
                  {
                    title: 'Total',
                    render: (row) => formatCurrency(row.total),
                  },
                ]}
              />
            )
          )}
        </Card>
      )}
    </div>
  )
}
