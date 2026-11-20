import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { apiError } from '../../lib/errors'
import { notifications } from '../../lib/notifications'
import { getResource, keys, useInvalidate } from '../../lib/queries'
import type { Resource, Transaction } from '../../types/api'
import { getSessionRevision, useAuth } from '../auth/useAuth'
type CartAction =
  | { type: 'add'; item_id: number }
  | { type: 'quantity'; id: number; quantity: number }
  | { type: 'remove'; id: number }
  | { type: 'clear' }
  | { type: 'checkout' }

export const CART_SYNC_INTERVAL_MS = 1_000

export function getCartSyncInterval(
  scannerEnabled: boolean,
  status: Transaction['status'] | undefined,
  actionPending: boolean,
): number | false {
  return scannerEnabled && status === 'CART' && !actionPending
    ? CART_SYNC_INTERVAL_MS
    : false
}

export function getCurrentCartSyncPath(
  scannerEnabled: boolean,
  transaction: Transaction | undefined,
): string | null {
  return scannerEnabled && transaction?.status === 'CART'
    ? `/transactions/${transaction.id}`
    : null
}

export function isScannerCheckoutTransition(
  current: Transaction,
  refreshed: Transaction,
): boolean {
  return current.id === refreshed.id &&
    current.status === 'CART' &&
    refreshed.status === 'PAID'
}

export function usePosCart(unitId: number, scannerEnabled = false) {
  const auth = useAuth()
  const client = useQueryClient()
  const invalidate = useInvalidate()
  const key = keys.cart(auth.user!.id, unitId)
  const revision = getSessionRevision()
  const actionPending = useRef(false)
  const [scannerCheckoutTransactionId, setScannerCheckoutTransactionId] =
    useState<number | null>(null)
  const checkoutAffectedPaths = [
    '/transactions',
    '/transaction-catalog',
    `/operational-units/${unitId}/inventory`,
    '/inventory/movements',
  ]
  const cart = useQuery({
    queryKey: key,
    queryFn: async ({ signal }) => {
      const current = client.getQueryData<Transaction>(key)
      const syncPath = getCurrentCartSyncPath(scannerEnabled, current)

      if (syncPath) {
        const refreshed = await getResource<Transaction>(syncPath, signal)
        if (isScannerCheckoutTransition(current!, refreshed)) {
          setScannerCheckoutTransactionId(refreshed.id)
          await invalidate(checkoutAffectedPaths)
        }

        return refreshed
      }

      return (
        await api.post<Resource<Transaction>>(
          '/transactions/cart',
          auth.isSuperadmin ? { operational_unit_id: unitId } : {},
          { signal },
        )
      ).data.data
    },
    enabled: unitId > 0,
    staleTime: Infinity,
    retry: false,
    refetchInterval: (query) =>
      getCartSyncInterval(
        scannerEnabled,
        query.state.data?.status,
        actionPending.current,
      ),
    refetchOnWindowFocus: scannerEnabled,
    refetchOnReconnect: scannerEnabled,
  })
  const action = useMutation({
    scope: { id: `cart-${auth.user!.id}-${unitId}` },
    onMutate: async (operation) => {
      actionPending.current = true
      if (operation.type === 'checkout') {
        setScannerCheckoutTransactionId(null)
      }
      await client.cancelQueries({ queryKey: key, exact: true })
    },
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
        case 'clear':
          return (await api.delete<Resource<Transaction>>(`${path}/items`))
            .data.data
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
        await invalidate(checkoutAffectedPaths)
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
    onSettled: () => {
      actionPending.current = false
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
        setScannerCheckoutTransactionId(null)
        action.reset()
        client.setQueryData(key, transaction)
      }
    },
    onError: (error) => notifications.error(apiError(error).message),
  })
  return {
    cart,
    action,
    newCart,
    scannerCheckoutTransactionId,
  }
}
