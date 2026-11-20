import { useEffect, useRef } from 'react'
import { Button, Modal } from '../../components/ui'
import paymentSuccessCheckmark from '../../assets/payment-success-checkmark.gif'
import type { Money } from '../../types/api'
import { formatCurrency } from '../../utils/format'

export const PAYMENT_SUCCESS_DURATION_MS = 5_000

export function PaymentSuccessDialog({
  total,
  onClose,
}: {
  total: Money
  onClose: () => void
}) {
  const onCloseRef = useRef(onClose)

  useEffect(() => {
    onCloseRef.current = onClose
  }, [onClose])

  useEffect(() => {
    const timeout = window.setTimeout(
      () => onCloseRef.current(),
      PAYMENT_SUCCESS_DURATION_MS,
    )

    return () => window.clearTimeout(timeout)
  }, [])

  return (
    <Modal title="Pembayaran Berhasil" onClose={onClose}>
      <div className="pos-payment-success">
        <img
          className="pos-payment-success-animation"
          src={paymentSuccessCheckmark}
          alt=""
          aria-hidden="true"
        />
        <p>Transaksi berhasil disimpan dan sudah dibayar.</p>
        <strong>{formatCurrency(total)}</strong>
      </div>
      <div
        className="pos-payment-success-countdown"
        role="progressbar"
        aria-label="Popup akan ditutup otomatis dalam 5 detik"
        data-duration={PAYMENT_SUCCESS_DURATION_MS}
      >
        <small>Menutup otomatis dalam 5 detik</small>
        <span className="pos-payment-success-countdown-track">
          <span
            className="pos-payment-success-countdown-bar"
            style={{ animationDuration: `${PAYMENT_SUCCESS_DURATION_MS}ms` }}
          />
        </span>
      </div>
      <div className="form-actions">
        <Button onClick={onClose}>Selesai</Button>
      </div>
    </Modal>
  )
}
