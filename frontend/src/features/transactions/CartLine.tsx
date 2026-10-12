import { useState } from 'react'
import { m } from 'motion/react'
import { Button, Input } from '../../components/ui'
import type { TransactionItem } from '../../types/api'
import { formatCurrency, label } from '../../utils/format'

function QuantityInput({
  item,
  disabled,
  update,
}: {
  item: TransactionItem
  disabled: boolean
  update: (quantity: number) => void
}) {
  const [quantity, setQuantity] = useState(String(item.quantity))
  const commit = () => {
    const next = Number(quantity)
    if (!Number.isInteger(next) || next < 1 || next > 4294967295) {
      setQuantity(String(item.quantity))
      return
    }
    if (next !== item.quantity) update(next)
  }
  return (
    <Input
      className="w-16 text-center"
      aria-label={`Jumlah ${item.item.name}`}
      type="number"
      min={1}
      max={4294967295}
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
  disabled,
  update,
  remove,
  attempt,
}: {
  item: TransactionItem
  disabled: boolean
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
      className="border-b border-slate-100 py-4"
    >
      <div className="mb-2 flex justify-between gap-3">
        <div>
          <p className="text-sm font-semibold">{item.item.name}</p>
          <p className="mt-1 text-xs text-slate-500">
            {formatCurrency(item.unit_price)} · {label(item.input_method)}
          </p>
        </div>
        <Button
          variant="ghost"
          disabled={disabled}
          aria-label={`Hapus ${item.item.name}`}
          onClick={remove}
        >
          ×
        </Button>
      </div>
      <div className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-1">
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
            update={update}
          />
          <Button
            variant="secondary"
            disabled={disabled || item.quantity >= 4294967295}
            aria-label={`Tambah ${item.item.name}`}
            onClick={() => update(item.quantity + 1)}
          >
            +
          </Button>
        </div>
        <strong className="text-sm">{formatCurrency(item.subtotal)}</strong>
      </div>
    </m.div>
  )
}
