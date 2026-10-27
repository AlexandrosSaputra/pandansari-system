import type { CatalogItem } from '../../types/api'
import { formatCurrency, formatNumber } from '../../utils/format'
import {
  StockUrgencyIndicator,
  stockUrgencyForAvailableQuantity,
} from '../../components/ui/StockUrgencyIndicator'

export function PosCatalogCard({
  row,
  quantityInCart,
  disabled,
  onAdd,
}: {
  row: CatalogItem
  quantityInCart: number
  disabled: boolean
  onAdd: () => void
}) {
  const availableQuantity = row.inventory
    ? Math.max(0, row.inventory.available_quantity - quantityInCart)
    : null
  const unavailable = availableQuantity === 0

  return (
    <article className="pos-product">
      <div className="pos-product-heading">
        <h3>{row.item.name}</h3>
        {availableQuantity !== null && (
          <StockUrgencyIndicator
            urgency={stockUrgencyForAvailableQuantity(availableQuantity, row.minimum_stock)}
            minimumStock={row.minimum_stock}
            availableQuantity={availableQuantity}
            compact
          />
        )}
      </div>
      <div className="pos-product-details">
        <p className="pos-product-price">{formatCurrency(row.selling_price)}</p>
        {availableQuantity !== null && (
          <p className="pos-product-stock">
            <strong>{formatNumber(availableQuantity)}</strong> tersedia
          </p>
        )}
      </div>
      <button
        type="button"
        className="pos-add-button"
        disabled={disabled || unavailable}
        aria-label={`${unavailable ? 'Stok habis' : 'Tambah ke keranjang'}: ${row.item.name}`}
        onClick={onAdd}
      >
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 4h2l2.4 10.2a2 2 0 0 0 2 1.5h7.8a2 2 0 0 0 1.9-1.4L21 8H7" />
          <circle cx="10" cy="20" r="1" />
          <circle cx="18" cy="20" r="1" />
        </svg>
        {unavailable ? 'Stok Habis' : 'Tambah ke Keranjang'}
      </button>
    </article>
  )
}
