import { Badge, ErrorState, Modal, Skeleton, Table } from '../../components/ui'
import { useDetail } from '../../lib/queries'
import type { Transaction } from '../../types/api'
import {
  formatCurrency,
  formatDateTime,
  formatNumber,
  label,
} from '../../utils/format'
export function TransactionDetail({
  id,
  onClose,
}: {
  id: number
  onClose: () => void
}) {
  const query = useDetail<Transaction>(`/transactions/${id}`)
  const data = query.data
  return (
    <Modal
      title={data?.transaction_number ?? 'Detail Transaksi'}
      onClose={onClose}
      wide
    >
      {query.isPending ? (
        <Skeleton />
      ) : query.error ? (
        <ErrorState
          error={query.error}
          retry={() => {
            void query.refetch()
          }}
        />
      ) : (
        data && (
          <>
            <dl className="detail-grid">
              <div>
                <dt>Unit</dt>
                <dd>{data.operational_unit.name}</dd>
              </div>
              <div>
                <dt>Status</dt>
                <dd>
                  <Badge value={data.status} />
                </dd>
              </div>
              <div>
                <dt>Pembuat</dt>
                <dd>{data.creator.name}</dd>
              </div>
              <div>
                <dt>Konfirmasi pembayaran oleh</dt>
                <dd>{data.payer?.name ?? '—'}</dd>
              </div>
              <div>
                <dt>Dibuat</dt>
                <dd>{formatDateTime(data.created_at)}</dd>
              </div>
              <div>
                <dt>Dibayar</dt>
                <dd>{formatDateTime(data.paid_at)}</dd>
              </div>
              <div>
                <dt>Subtotal</dt>
                <dd>{formatCurrency(data.subtotal)}</dd>
              </div>
              <div>
                <dt>Total</dt>
                <dd className="font-semibold">{formatCurrency(data.total)}</dd>
              </div>
              <div>
                <dt>Jumlah pengunjung</dt>
                <dd>{formatNumber(data.visitor_count)}</dd>
              </div>
            </dl>
            <Table
              rows={data.items ?? []}
              rowKey={(row) => row.id}
              columns={[
                { title: 'Item', render: (row) => row.item.name },
                { title: 'Jumlah', render: (row) => row.quantity },
                {
                  title: 'Harga saat transaksi',
                  render: (row) => formatCurrency(row.unit_price),
                },
                {
                  title: 'Subtotal',
                  render: (row) => formatCurrency(row.subtotal),
                },
                {
                  title: 'Metode input',
                  render: (row) => label(row.input_method),
                },
              ]}
            />
          </>
        )
      )}
    </Modal>
  )
}
