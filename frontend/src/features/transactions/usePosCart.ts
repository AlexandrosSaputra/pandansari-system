import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { apiError } from '../../lib/errors'
import { notifications } from '../../lib/notifications'
import { getResource, keys, useInvalidate } from '../../lib/queries'
import type { Resource, Transaction } from '../../types/api'
import { getSessionRevision, useAuth } from '../auth/useAuth'
type CartAction =
  | { type: 'add'; item_id: number }
  | { type: 'scan'; barcode: string }
  | { type: 'quantity'; id: number; quantity: number }
  | { type: 'remove'; id: number }
  | { type: 'checkout' }
export function usePosCart(unitId: number) {
  const auth = useAuth()
  const client = useQueryClient()
  const invalidate = useInvalidate()
  const key = keys.cart(auth.user!.id, unitId)
  const revision = getSessionRevision()
  const cart = useQuery({
    queryKey: key,
    queryFn: async () =>
      (
        await api.post<Resource<Transaction>>(
          '/transactions/cart',
          auth.isSuperadmin ? { operational_unit_id: unitId } : {},
        )
      ).data.data,
    enabled: unitId > 0,
    staleTime: Infinity,
    retry: false,
    refetchOnReconnect: false,
  })
  const action = useMutation({
    scope: { id: `cart-${auth.user!.id}-${unitId}` },
    mutationFn: async (operation: CartAction) => {
      const current = client.getQueryData<Transaction>(key)
      if (!current || current.status !== 'CART')
        throw new Error('Keranjang tidak dapat diubah.')
      const path = `/transactions/${current.id}`
      switch (operation.type) {
        case 'add':
          return (
            await api.post<Resource<Transaction>>(`${path}/items`, {
              input_method: 'MANUAL',
              item_id: operation.item_id,
              quantity: 1,
            })
          ).data.data
        case 'scan':
          return (
            await api.post<Resource<Transaction>>(`${path}/items`, {
              input_method: 'SCANNER',
              barcode: operation.barcode,
              quantity: 1,
            })
          ).data.data
        case 'quantity':
          return (
            await api.patch<Resource<Transaction>>(
              `${path}/items/${operation.id}`,
              { quantity: operation.quantity },
            )
          ).data.data
        case 'remove':
          return (
            await api.delete<Resource<Transaction>>(
              `${path}/items/${operation.id}`,
            )
          ).data.data
        case 'checkout':
          return (await api.post<Resource<Transaction>>(`${path}/checkout`))
            .data.data
      }
    },
    onSuccess: async (transaction, operation) => {
      if (revision !== getSessionRevision()) return
      client.setQueryData(key, transaction)
      if (operation.type === 'checkout') {
        notifications.success(
          `Pembayaran dikonfirmasi. Transaksi ${transaction.transaction_number} selesai.`,
        )
        await invalidate([
          '/transactions',
          '/transaction-catalog',
          `/operational-units/${unitId}/inventory`,
          '/inventory/movements',
        ])
      }
    },
    onError: async (error) => {
      notifications.error(apiError(error).message)
      if (apiError(error).status !== 409 || revision !== getSessionRevision())
        return
      await invalidate([
        '/transaction-catalog',
        `/operational-units/${unitId}/inventory`,
      ])
      const current = client.getQueryData<Transaction>(key)
      // A failed checkout keeps the same cart and its lines; refresh it only via the read endpoint.
      if (current && auth.hasPermission('transaction.view')) {
        try {
          const fresh = await getResource<Transaction>(
            `/transactions/${current.id}`,
          )
          if (revision === getSessionRevision()) client.setQueryData(key, fresh)
        } catch {
          /* Preserve the last confirmed cart when refresh fails. */
        }
      }
    },
  })
  const newCart = useMutation({
    mutationFn: async () =>
      (
        await api.post<Resource<Transaction>>(
          '/transactions/cart',
          auth.isSuperadmin ? { operational_unit_id: unitId } : {},
        )
      ).data.data,
    onSuccess: (transaction) => {
      if (revision === getSessionRevision()) {
        action.reset()
        client.setQueryData(key, transaction)
      }
    },
    onError: (error) => notifications.error(apiError(error).message),
  })
  return { cart, action, newCart }
}
