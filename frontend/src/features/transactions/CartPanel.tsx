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
import { PosSectionHeader } from './PosSectionHeader'

export function CartPanel({ pos }: { pos: ReturnType<typeof usePosCart> }) {
  const [confirm, setConfirm] = useState(false)
  const [clearConfirm, setClearConfirm] = useState(false)
  const data = pos.cart.data
  const busy = pos.action.isPending || pos.newCart.isPending
  const itemCount =
    data?.items?.reduce((total, item) => total + item.quantity, 0) ?? 0
  return (
    <Card className="pos-cart">
      <PosSectionHeader
        kind="cart"
        title="Keranjang"
        description={data?.transaction_number}
        badge={
          data?.status === 'PAID' ? (
            <Badge value={data.status} />
          ) : (
            <>
              <Button
                variant="ghost"
                className="pos-clear-cart-button"
                disabled={busy || !data?.items?.length}
                aria-label="Hapus semua barang dari keranjang"
                title="Hapus semua barang"
                onClick={() => {
                  pos.action.reset()
                  setClearConfirm(true)
                }}
              >
                <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6" />
                </svg>
              </Button>
              <span className="pos-cart-count">{formatNumber(itemCount)} item</span>
            </>
          )
        }
      />
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
            <div className="pos-cart-items" role="region" aria-label="Daftar item keranjang" tabIndex={0}>
              <AnimatePresence initial={false}>
                {data.items?.map((item, index) => (
                  <CartLine
                    key={item.id}
                    rowNumber={index + 1}
                    attempt={pos.action.submittedAt}
                    item={item}
                    maximumQuantity={item.maximum_quantity}
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
            </div>
            <div className="pos-cart-summary">
              <div className="pos-summary-row">
                <span>Subtotal</span>
                <span>{formatCurrency(data.subtotal)}</span>
              </div>
              {(data.operational_unit.type === 'TICKETING' ||
                data.visitor_count > 0) && (
                <div className="pos-summary-row">
                  <span>Jumlah pengunjung</span>
                  <span>{formatNumber(data.visitor_count)}</span>
                </div>
              )}
              <div className="pos-summary-total">
                <span>Total</span>
                <strong>{formatCurrency(data.total)}</strong>
              </div>
            </div>
            {pos.action.error && (
              <>
                {apiError(pos.action.error).status === 409 && (
                  <p className="text-sm text-amber-800">
                    Stok atau status transaksi berubah. Periksa kembali
                    keranjang sebelum melanjutkan.
                  </p>
                )}
              </>
            )}
            {data.status === 'PAID' ? (
              <Button
                className="pos-checkout"
                busy={pos.newCart.isPending}
                onClick={() => pos.newCart.mutate()}
              >
                Transaksi Baru
              </Button>
            ) : (
              <Button
                className="pos-checkout"
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
      {clearConfirm && (
        <ConfirmDialog
          title="Hapus Semua Barang"
          busy={pos.action.isPending}
          error={pos.action.error}
          danger
          onClose={() => setClearConfirm(false)}
          onConfirm={() =>
            pos.action.mutate(
              { type: 'clear' },
              { onSuccess: () => setClearConfirm(false) },
            )
          }
        >
          Semua barang pada keranjang akan dihapus. Tindakan ini tidak dapat dibatalkan.
        </ConfirmDialog>
      )}
    </Card>
  )
}
