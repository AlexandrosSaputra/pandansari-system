import type { ActivityLog } from '../../types/api'
import { formatCurrency, formatNumber } from '../../utils/format'

type ActivityTone = 'account' | 'inventory' | 'transaction' | 'transfer' | 'system'

export interface ActivityPresentation {
  title: string
  description: string
  tone: ActivityTone
}

function metadata(log: ActivityLog): Record<string, unknown> {
  return log.metadata !== null && typeof log.metadata === 'object'
    ? (log.metadata as Record<string, unknown>)
    : {}
}

function stringValue(value: unknown): string | null {
  return typeof value === 'string' && value.trim() ? value : null
}

function numberValue(value: unknown): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string' && value.trim() && Number.isFinite(Number(value))) {
    return Number(value)
  }
  return null
}

function subjectReference(log: ActivityLog, fallback: string): string {
  return log.subject_id ? `${fallback} #${log.subject_id}` : fallback
}

export function presentActivity(log: ActivityLog): ActivityPresentation {
  const detail = metadata(log)

  switch (log.action) {
    case 'TRANSACTION_PAID': {
      const number = stringValue(detail.transaction_number)
      const total = numberValue(detail.total)
      const visitors = numberValue(detail.visitor_count)
      const parts = [
        number ?? subjectReference(log, 'Transaksi'),
        total === null ? null : `Total ${formatCurrency(total)}`,
        visitors === null ? null : `${formatNumber(visitors)} pengunjung`,
      ].filter(Boolean)

      return {
        title: 'Transaksi berhasil disimpan',
        description: parts.join(' • '),
        tone: 'transaction',
      }
    }
    case 'WAREHOUSE_STOCK_IN':
    case 'WAREHOUSE_STOCK_OUT': {
      const isStockIn = log.action === 'WAREHOUSE_STOCK_IN'
      const quantity = numberValue(detail.quantity)
      const itemId = numberValue(detail.item_id)
      const parts = [
        quantity === null ? null : `${formatNumber(quantity)} unit`,
        itemId === null ? 'Barang gudang' : `Barang #${itemId}`,
      ].filter(Boolean)

      return {
        title: `${isStockIn ? 'Stok masuk' : 'Stok keluar'} berhasil dicatat`,
        description: `${parts.join(' • ')} • Gudang utama`,
        tone: 'inventory',
      }
    }
    case 'TRANSFER_CREATED':
      return {
        title: 'Transfer stok berhasil dibuat',
        description:
          stringValue(detail.transfer_number) ?? subjectReference(log, 'Transfer'),
        tone: 'transfer',
      }
    case 'TRANSFER_ACCEPTED':
      return {
        title: 'Transfer stok berhasil diterima',
        description:
          stringValue(detail.transfer_number) ?? subjectReference(log, 'Transfer'),
        tone: 'transfer',
      }
    case 'TRANSFER_REJECTED':
      return {
        title: 'Transfer stok ditolak',
        description:
          stringValue(detail.transfer_number) ?? subjectReference(log, 'Transfer'),
        tone: 'transfer',
      }
    case 'AUTH_LOGIN_SUCCESS':
      return {
        title: 'Berhasil masuk ke sistem',
        description: 'Sesi akun dimulai',
        tone: 'account',
      }
    case 'AUTH_LOGOUT':
      return {
        title: 'Berhasil keluar dari sistem',
        description: 'Sesi akun diakhiri',
        tone: 'account',
      }
    case 'AUTH_LOGIN_LOCKED':
      return {
        title: 'Akun dikunci sementara',
        description: 'Terlalu banyak percobaan masuk yang gagal',
        tone: 'account',
      }
    case 'AUTH_ASSIGNMENT_SELECTED':
      return {
        title: 'Ruang kerja aktif diganti',
        description: 'Unit atau peran aktif berhasil diperbarui',
        tone: 'account',
      }
    case 'ACCOUNT_PROFILE_UPDATED':
      return {
        title: 'Profil berhasil diperbarui',
        description: 'Perubahan data akun telah disimpan',
        tone: 'account',
      }
    case 'ACCOUNT_PASSWORD_UPDATED':
    case 'AUTH_PASSWORD_RESET':
      return {
        title: 'Kata sandi berhasil diperbarui',
        description: 'Keamanan akun telah diperbarui',
        tone: 'account',
      }
    case 'ITEM_CREATED':
      return {
        title: 'Barang berhasil ditambahkan',
        description:
          stringValue(detail.item_code) ?? subjectReference(log, 'Barang'),
        tone: 'inventory',
      }
    case 'ITEM_UPDATED':
    case 'ITEM_STATUS_CHANGED':
      return {
        title: 'Data barang berhasil diperbarui',
        description: subjectReference(log, 'Barang'),
        tone: 'inventory',
      }
    case 'UNIT_ITEM_CREATED':
      return {
        title: 'Barang unit berhasil ditambahkan',
        description: subjectReference(log, 'Barang unit'),
        tone: 'inventory',
      }
    case 'UNIT_ITEM_UPDATED':
    case 'UNIT_ITEM_STATUS_CHANGED':
      return {
        title: 'Barang unit berhasil diperbarui',
        description: subjectReference(log, 'Barang unit'),
        tone: 'inventory',
      }
    case 'OPERATIONAL_UNIT_CREATED':
      return {
        title: 'Unit operasional berhasil dibuat',
        description: stringValue(detail.code) ?? subjectReference(log, 'Unit'),
        tone: 'system',
      }
    case 'OPERATIONAL_UNIT_UPDATED':
    case 'OPERATIONAL_UNIT_STATUS_CHANGED':
      return {
        title: 'Unit operasional berhasil diperbarui',
        description: subjectReference(log, 'Unit'),
        tone: 'system',
      }
    case 'USER_CREATED':
      return {
        title: 'Pengguna berhasil dibuat',
        description:
          stringValue(detail.username) ?? subjectReference(log, 'Pengguna'),
        tone: 'system',
      }
    case 'USER_UPDATED':
    case 'USER_STATUS_CHANGED':
      return {
        title: 'Data pengguna berhasil diperbarui',
        description: subjectReference(log, 'Pengguna'),
        tone: 'system',
      }
    case 'ROLE_PERMISSIONS_UPDATED':
      return {
        title: 'Hak akses berhasil diperbarui',
        description: subjectReference(log, 'Peran'),
        tone: 'system',
      }
    default:
      return {
        title: log.action.replaceAll('_', ' ').toLocaleLowerCase('id-ID'),
        description: subjectReference(log, log.subject_type ?? 'Aktivitas sistem'),
        tone: 'system',
      }
  }
}
