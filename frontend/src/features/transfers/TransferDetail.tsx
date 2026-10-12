import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Badge,
  Button,
  ConfirmDialog,
  ErrorState,
  FormField,
  Modal,
  Skeleton,
  Table,
  Textarea,
} from '../../components/ui'
import { SaveForm } from '../../components/ui/SaveForm'
import { api } from '../../lib/api'
import { useDetail, useInvalidate } from '../../lib/queries'
import type { StockTransfer } from '../../types/api'
import { formatDateTime } from '../../utils/format'
import { useAuth } from '../auth/useAuth'
const rejectionSchema = z.object({
  rejection_reason: z
    .string()
    .trim()
    .min(1, 'Alasan penolakan wajib diisi.')
    .max(2000),
})
export function TransferDetail({
  id,
  onClose,
}: {
  id: number
  onClose: () => void
}) {
  const query = useDetail<StockTransfer>(`/stock-transfers/${id}`)
  const auth = useAuth()
  const invalidate = useInvalidate()
  const [action, setAction] = useState<'accept' | 'reject' | null>(null)
  const [notice, setNotice] = useState('')
  const form = useForm<z.infer<typeof rejectionSchema>>({
    resolver: zodResolver(rejectionSchema),
    defaultValues: { rejection_reason: '' },
  })
  const success = async () => {
    setAction(null)
    setNotice('Respons transfer berhasil disimpan.')
    await invalidate([
      '/stock-transfers',
      '/inventory/warehouse',
      '/inventory/movements',
      `/operational-units/${query.data?.destination.operational_unit?.id}/inventory`,
    ])
  }
  const accept = useMutation({
    mutationFn: () => api.post(`/stock-transfers/${id}/accept`),
    onSuccess: success,
    onError: () => {
      void query.refetch()
    },
  })
  const reject = useMutation({
    mutationFn: (values: z.infer<typeof rejectionSchema>) =>
      api.post(`/stock-transfers/${id}/reject`, values),
    onSuccess: success,
    onError: () => {
      void query.refetch()
    },
  })
  const data = query.data
  const canRespond =
    data?.status === 'PENDING' &&
    auth.hasPermission('transfer.respond') &&
    (auth.isSuperadmin ||
      (auth.user?.role?.slug === 'admin' &&
        data.destination.operational_unit?.id ===
          auth.user.operational_unit?.id))
  return (
    <Modal
      title={data?.transfer_number ?? 'Detail Transfer'}
      onClose={onClose}
      busy={accept.isPending || reject.isPending}
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
            {notice && (
              <p role="status" className="notice notice-success">
                {notice}
              </p>
            )}
            <dl className="detail-grid">
              <div>
                <dt>Status</dt>
                <dd>
                  <Badge value={data.status} />
                </dd>
              </div>
              <div>
                <dt>Dibuat</dt>
                <dd>{formatDateTime(data.created_at)}</dd>
              </div>
              <div>
                <dt>Sumber</dt>
                <dd>{data.source_location.name}</dd>
              </div>
              <div>
                <dt>Tujuan</dt>
                <dd>
                  {data.destination.operational_unit?.name ??
                    data.destination.location.name}
                </dd>
              </div>
              <div>
                <dt>Pembuat</dt>
                <dd>{data.creator.name}</dd>
              </div>
              <div>
                <dt>Penanggap</dt>
                <dd>
                  {data.responder?.name ?? '—'} ·{' '}
                  {formatDateTime(data.responded_at)}
                </dd>
              </div>
            </dl>
            {data.rejection_reason && (
              <p className="notice notice-error">
                Alasan penolakan: {data.rejection_reason}
              </p>
            )}
            <Table
              rows={data.items ?? []}
              rowKey={(row) => row.item.id}
              columns={[
                { title: 'Kode', render: (row) => row.item.item_code },
                { title: 'Produk', render: (row) => row.item.name },
                { title: 'Jumlah', render: (row) => row.quantity },
                { title: 'Satuan', render: (row) => row.item.uom ?? '—' },
              ]}
            />
            {canRespond && (
              <div className="form-actions">
                <Button
                  variant="danger"
                  onClick={() => {
                    reject.reset()
                    setAction('reject')
                  }}
                >
                  Tolak
                </Button>
                <Button
                  onClick={() => {
                    accept.reset()
                    setAction('accept')
                  }}
                >
                  Terima Transfer
                </Button>
              </div>
            )}
          </>
        )
      )}
      {action === 'accept' && (
        <ConfirmDialog
          title="Terima transfer?"
          busy={accept.isPending}
          error={accept.error}
          onClose={() => setAction(null)}
          onConfirm={() => accept.mutate()}
        >
          Pastikan produk dan jumlah yang diterima sesuai. Stok akan masuk ke
          unit tujuan.
        </ConfirmDialog>
      )}
      {action === 'reject' && (
        <Modal
          title="Tolak Transfer"
          onClose={() => setAction(null)}
          busy={reject.isPending}
        >
          <SaveForm
            form={form}
            mutation={reject}
            onClose={() => setAction(null)}
            onSaved={() => setAction(null)}
          >
            <FormField
              label="Alasan penolakan"
              error={form.formState.errors.rejection_reason?.message}
            >
              <Textarea {...form.register('rejection_reason')} />
            </FormField>
          </SaveForm>
        </Modal>
      )}
    </Modal>
  )
}
