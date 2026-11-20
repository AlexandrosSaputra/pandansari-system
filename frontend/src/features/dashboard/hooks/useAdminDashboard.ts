import { useQuery } from '@tanstack/react-query'
import { getPage, getResource } from '../../../lib/queries'
import type { InventoryBalance, Money, Transaction } from '../../../types/api'

export interface AdminSales {
  period: { from: string; to: string; days: number; timezone: string }
  revenue: Money
  transactions: number
  average_transaction: Money
  items_sold: number
  visitors: number
  daily: { date: string; revenue: Money; transactions: number }[]
  top_items: {
    id: number
    name: string
    uom: string | null
    quantity: number
    revenue: Money
  }[]
  recent_transactions: Transaction[]
  generated_at: string
}

export function useAdminSales(
  unitId: number | undefined,
  days: number,
  enabled: boolean,
) {
  return useQuery({
    // The transaction family is invalidated by checkout and cart mutations.
    queryKey: ['/transactions', 'admin-dashboard', unitId, days],
    queryFn: ({ signal }) =>
      getResource<AdminSales>(`/dashboard/admin/sales?days=${days}`, signal),
    enabled: enabled && !!unitId,
    refetchOnWindowFocus: true,
  })
}

export async function getDashboardInventory(
  path: string,
  signal?: AbortSignal,
) {
  const first = await getPage<InventoryBalance>(path, { per_page: 100 }, signal)
  const rows = [...first.data]
  // Never present the first page's stock counts as the whole unit's inventory.
  for (let page = 2; page <= first.meta.last_page; page += 1) {
    rows.push(
      ...(
        await getPage<InventoryBalance>(path, { per_page: 100, page }, signal)
      ).data,
    )
  }
  return rows
}

export function useAdminInventory(
  unitId: number | undefined,
  enabled: boolean,
) {
  const path = `/operational-units/${unitId}/inventory`
  return useQuery({
    queryKey: [path, 'dashboard'],
    queryFn: ({ signal }) => getDashboardInventory(path, signal),
    enabled: enabled && !!unitId,
    refetchOnWindowFocus: true,
  })
}
