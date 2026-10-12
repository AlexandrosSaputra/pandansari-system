import { QueryClient } from '@tanstack/react-query'
import { apiError } from './errors'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60_000,
      gcTime: 5 * 60_000,
      retry: (count, error) =>
        count < 1 &&
        ![401, 403, 404, 409, 419, 422].includes(apiError(error).status),
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
    },
    mutations: {
      retry: 0,
    },
  },
})
