import { useState } from 'react'
import { m } from 'motion/react'
import { Button, Input } from '../../components/ui'
import type { TransactionItem } from '../../types/api'
import { formatCurrency, label } from '../../utils/format'

function QuantityInput({
  item,
  disabled,
  maximumQuantity,
  update,
}: {
  item: TransactionItem
  disabled: boolean
  maximumQuantity?: number
  update: (quantity: number) => void
}) {
  const [quantity, setQuantity] = useState(String(item.quantity))
  const commit = () => {
    const next = Number(quantity)
    if (
      !Number.isInteger(next) ||
      next < 1 ||
      next > 4294967295 ||
      (maximumQuantity !== undefined && next > maximumQuantity)
    ) {
      setQuantity(String(item.quantity))
      return
    }
    if (next !== item.quantity) update(next)
  }
  return (
    <Input
      className="pos-quantity-input"
      aria-label={`Jumlah ${item.item.name}`}
      type="number"
      min={1}
      max={maximumQuantity ?? 4294967295}
      disabled={disabled}
      value={quantity}
      onChange={(e) => setQuantity(e.target.value)}
      onBlur={commit}
      onKeyDown={(e) => {
        if (e.key === 'Enter') e.currentTarget.blur()
      }}
    />
  )
}
export function CartLine({
  item,
  rowNumber,
  disabled,
  maximumQuantity,
  update,
  remove,
  attempt,
}: {
  item: TransactionItem
  rowNumber: number
  disabled: boolean
  maximumQuantity?: number
  update: (quantity: number) => void
  remove: () => void
  attempt: number
}) {
  return (
    <m.div
      initial={{ opacity: 0, y: 5 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0 }}
      transition={{ duration: 0.16 }}
      className="pos-cart-line"
      data-tone={rowNumber % 2 === 0 ? 'blue' : 'neutral'}
    >
      <div className="pos-cart-line-heading">
        <div className="pos-cart-line-identity">
          <span className="pos-cart-line-number" aria-label={`Nomor ${rowNumber}`}>
            <span>No.</span> {String(rowNumber).padStart(2, '0')}
          </span>
          <div className="min-w-0">
            <p className="pos-cart-line-name">{item.item.name}</p>
            <p className="pos-cart-line-meta">
              {formatCurrency(item.unit_price)} · {label(item.input_method)}
            </p>
          </div>
        </div>
        <Button
          variant="ghost"
          className="pos-remove-button"
          disabled={disabled}
          aria-label={`Hapus ${item.item.name}`}
          title={`Hapus ${item.item.name}`}
          onClick={remove}
        >
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6" />
          </svg>
        </Button>
      </div>
      <div className="pos-cart-line-footer">
        <div className="pos-quantity-control" role="group" aria-label={`Atur jumlah ${item.item.name}`}>
          <Button
            variant="secondary"
            disabled={disabled || item.quantity <= 1}
            aria-label={`Kurangi ${item.item.name}`}
            onClick={() => update(item.quantity - 1)}
          >
            −
          </Button>
          <QuantityInput
            key={`${item.quantity}-${attempt}`}
            item={item}
            disabled={disabled}
            maximumQuantity={maximumQuantity}
            update={update}
          />
          <Button
            variant="secondary"
            disabled={
              disabled ||
              item.quantity >= 4294967295 ||
              (maximumQuantity !== undefined && item.quantity >= maximumQuantity)
            }
            aria-label={`Tambah ${item.item.name}`}
            onClick={() => update(item.quantity + 1)}
          >
            +
          </Button>
        </div>
        <strong className="pos-line-subtotal">{formatCurrency(item.subtotal)}</strong>
      </div>
    </m.div>
  )
}
