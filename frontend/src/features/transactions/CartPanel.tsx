import { useState } from 'react'
import { AnimatePresence } from 'motion/react'
import {
  Badge,
  Button,
  Card,
  ConfirmDialog,
  EmptyState,
  ErrorState,
  Skeleton,
} from '../../components/ui'
import { apiError } from '../../lib/errors'
import { formatCurrency, formatNumber } from '../../utils/format'
import type { usePosCart } from './usePosCart'
import { CartLine } from './CartLine'

export function CartPanel({ pos }: { pos: ReturnType<typeof usePosCart> }) {
  const [confirm, setConfirm] = useState(false)
  const data = pos.cart.data
  const busy = pos.action.isPending || pos.newCart.isPending
  return (
    <Card className="p-5">
      <div className="mb-4 flex justify-between">
        <h2>Keranjang</h2>
        {data && <Badge value={data.status} />}
      </div>
      {pos.cart.isPending ? (
        <Skeleton rows={4} />
      ) : pos.cart.error ? (
        <ErrorState
          error={pos.cart.error}
          retry={() => {
            void pos.cart.refetch()
          }}
        />
      ) : (
        data && (
          <>
            <p className="mb-2 text-xs text-slate-400">
              {data.transaction_number}
            </p>
            {data.status === 'PAID' && (
              <div role="status" className="notice notice-success">
                Pembayaran dikonfirmasi. Transaksi {data.transaction_number}{' '}
                selesai.
              </div>
            )}
            <AnimatePresence initial={false}>
              {data.items?.map((item) => (
                <CartLine
                  key={item.id}
                  attempt={pos.action.submittedAt}
                  item={item}
                  disabled={busy || data.status === 'PAID'}
                  update={(quantity) =>
                    pos.action.mutate({
                      type: 'quantity',
                      id: item.id,
                      quantity,
                    })
                  }
                  remove={() =>
                    pos.action.mutate({ type: 'remove', id: item.id })
                  }
                />
              ))}
            </AnimatePresence>
            {!data.items?.length && (
              <EmptyState message="Keranjang masih kosong">
                <p className="mt-2 text-xs text-slate-400">
                  Pilih item dari katalog.
                </p>
              </EmptyState>
            )}
            <div className="mt-5 space-y-3 text-sm">
              <div className="flex justify-between text-slate-500">
                <span>Subtotal</span>
                <span>{formatCurrency(data.subtotal)}</span>
              </div>
              <div className="flex justify-between text-slate-500">
                <span>Jumlah pengunjung</span>
                <span>{formatNumber(data.visitor_count)}</span>
              </div>
              <div className="flex justify-between border-t border-slate-100 pt-4 text-lg font-semibold">
                <span>Total</span>
                <span>{formatCurrency(data.total)}</span>
              </div>
            </div>
            {pos.action.error && (
              <>
                <ErrorState error={pos.action.error} />
                {apiError(pos.action.error).status === 409 && (
                  <p className="text-sm text-amber-800">
                    Stok atau status transaksi berubah. Periksa kembali
                    keranjang sebelum melanjutkan.
                  </p>
                )}
              </>
            )}
            {pos.newCart.error && <ErrorState error={pos.newCart.error} />}
            {data.status === 'PAID' ? (
              <Button
                className="mt-5 w-full"
                busy={pos.newCart.isPending}
                onClick={() => pos.newCart.mutate()}
              >
                Transaksi Baru
              </Button>
            ) : (
              <Button
                className="mt-5 w-full"
                disabled={!data.items?.length}
                busy={busy}
                onClick={() => {
                  pos.action.reset()
                  setConfirm(true)
                }}
              >
                Konfirmasi Pembayaran
              </Button>
            )}
          </>
        )
      )}
      {confirm && (
        <ConfirmDialog
          title="Konfirmasi Pembayaran"
          busy={pos.action.isPending}
          error={pos.action.error}
          onClose={() => setConfirm(false)}
          onConfirm={() =>
            pos.action.mutate(
              { type: 'checkout' },
              {
                onSuccess: () => setConfirm(false),
                onError: () => setConfirm(false),
              },
            )
          }
        >
          Pastikan pelanggan telah menunjukkan bukti pembayaran.
        </ConfirmDialog>
      )}
    </Card>
  )
}
