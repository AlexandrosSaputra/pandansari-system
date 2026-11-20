import type { Money } from '../types/api'
export const formatCurrency = (value?: Money | null) =>
  value == null
    ? '—'
    : new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 2,
      }).format(Number(value))

export const formatRupiahInput = (value: string) => {
  if (!value) return ''

  const [integerPart = '', decimalPart] = value.split('.')
  const normalizedInteger = integerPart.replace(/^0+(?=\d)/, '') || '0'
  const groupedInteger = normalizedInteger.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

  return decimalPart === undefined
    ? groupedInteger
    : `${groupedInteger},${decimalPart}`
}

export const parseRupiahInput = (value: string) => {
  const sanitized = value.replace(/[^\d,]/g, '')
  const commaIndex = sanitized.indexOf(',')
  const integerDigits = (
    commaIndex === -1 ? sanitized : sanitized.slice(0, commaIndex)
  ).replace(/^0+(?=\d)/, '')

  if (commaIndex === -1) return integerDigits

  const decimalDigits = sanitized
    .slice(commaIndex + 1)
    .replace(/,/g, '')
    .slice(0, 2)

  return `${integerDigits || '0'}.${decimalDigits}`
}
export const formatNumber = (value?: number | null) =>
  value == null ? '—' : new Intl.NumberFormat('id-ID').format(value)
export const formatDateTime = (value?: string | null) =>
  value
    ? new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(new Date(value))
    : '—'
export const formatDate = (value?: string | null) =>
  value
    ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(`${value}T00:00:00`),
      )
    : '—'
export const labels: Record<string, string> = {
  PRODUCT: 'Produk',
  SERVICE: 'Layanan',
  OUTLET: 'Outlet',
  TICKETING: 'Ticketing',
  WAREHOUSE: 'Gudang',
  UNIT: 'Unit',
  CART: 'Keranjang',
  PAID: 'Dibayar',
  PENDING: 'Menunggu',
  APPROVED: 'Disetujui',
  ACCEPTED: 'Diterima',
  REJECTED: 'Ditolak',
  IN: 'Masuk',
  OUT: 'Keluar',
  STOCK_IN: 'Stok masuk',
  STOCK_OUT: 'Stok keluar',
  TRANSFER_IN: 'Transfer masuk',
  TRANSFER_OUT: 'Transfer keluar',
  SALE: 'Penjualan',
  MANUAL: 'Manual',
  SCANNER: 'Barcode',
}
export const label = (value: string) => labels[value] ?? value
