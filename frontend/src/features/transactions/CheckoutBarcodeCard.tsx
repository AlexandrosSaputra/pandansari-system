import { Card } from '../../components/ui'
import type { Transaction } from '../../types/api'
import { Code128Barcode } from './Code128Barcode'

export function CheckoutBarcodeCard({
  transaction,
  busy,
}: {
  transaction?: Transaction
  busy: boolean
}) {
  const itemCount = transaction?.items?.length ?? 0
  const barcode = transaction?.scanner_checkout_barcode
  const canScan =
    transaction?.status === 'CART' && itemCount > 0 && !busy && !!barcode

  let unavailableMessage = 'Memuat barcode konfirmasi pembayaran…'
  if (transaction?.status === 'PAID') {
    unavailableMessage = 'Pembayaran selesai. Buat transaksi baru untuk memindai lagi.'
  } else if (transaction && itemCount === 0) {
    unavailableMessage = 'Tambahkan item agar barcode konfirmasi dapat dipindai.'
  } else if (busy) {
    unavailableMessage = 'Transaksi sedang diproses. Tunggu hingga barcode muncul kembali.'
  }

  return (
    <Card className="pos-checkout-barcode">
      <div className="pos-checkout-barcode-heading">
        <span className="pos-checkout-barcode-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
            <path d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M7 9v6M10 8v8M14 8v8M17 9v6" />
          </svg>
        </span>
        <span>
          <strong>Konfirmasi via Scanner</strong>
          <small>Pindai sebagai pengganti tombol konfirmasi pembayaran.</small>
        </span>
      </div>
      {canScan ? (
        <div className="pos-checkout-barcode-body">
          <Code128Barcode value={barcode} />
          <code>{barcode}</code>
        </div>
      ) : (
        <p className="pos-checkout-barcode-unavailable" role="status">
          {unavailableMessage}
        </p>
      )}
    </Card>
  )
}
