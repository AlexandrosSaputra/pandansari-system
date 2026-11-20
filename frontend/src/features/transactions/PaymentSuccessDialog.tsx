import { Button, Modal } from '../../components/ui'
import type { Money } from '../../types/api'
import { formatCurrency } from '../../utils/format'

export function PaymentSuccessDialog({
  total,
  onClose,
}: {
  total: Money
  onClose: () => void
}) {
  return (
    <Modal title="Pembayaran Berhasil" onClose={onClose}>
      <div className="pos-payment-success">
        <span className="pos-payment-success-icon" aria-hidden="true">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2.25"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="m5 12 4 4L19 6" />
          </svg>
        </span>
        <p>Transaksi berhasil disimpan dan sudah dibayar.</p>
        <strong>{formatCurrency(total)}</strong>
      </div>
      <div className="form-actions">
        <Button onClick={onClose}>Selesai</Button>
      </div>
    </Modal>
  )
}
