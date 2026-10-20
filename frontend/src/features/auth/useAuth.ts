import { useMutation, useQuery } from '@tanstack/react-query'
import { api, sessionApi } from '../../lib/api'
import { apiError } from '../../lib/errors'
import { keys, getResource } from '../../lib/queries'
import { queryClient } from '../../lib/queryClient'
import type { AuthUser, Resource } from '../../types/api'

let sessionRevision = 0
function clearPrivateCache() {
  // Keep the auth query's observers attached so guards receive the new session immediately.
  queryClient.removeQueries({
    predicate: (query) => query.queryKey[0] !== keys.auth[0],
  })
  queryClient.getMutationCache().clear()
}
async function clearSession() {
  sessionRevision += 1
  await queryClient.cancelQueries()
  clearPrivateCache()
  queryClient.setQueryData(keys.auth, null)
}
api.interceptors.response.use(
  (response) => response,
  async (error: unknown) => {
    if (apiError(error).status === 401 && queryClient.getQueryData(keys.auth))
      await clearSession()
    return Promise.reject(error)
  },
)
export function useAuth() {
  const query = useQuery({
    queryKey: keys.auth,
    queryFn: ({ signal }) =>
      getResource<AuthUser | null>('/auth/session', signal),
    retry: false,
    staleTime: 60_000,
  })
  const loginMutation = useMutation({
    mutationFn: async (credentials: { email: string; password: string }) => {
      await sessionApi.get('/sanctum/csrf-cookie')
      const response = await api.post<Resource<AuthUser>>(
        '/auth/login',
        credentials,
      )

      return response.data.data
    },
    onSuccess: async (user) => {
      sessionRevision += 1
      await queryClient.cancelQueries()
      clearPrivateCache()
      queryClient.setQueryData(keys.auth, user)
    },
  })
  const logoutMutation = useMutation({
    mutationFn: async () => {
      await api.post('/auth/logout')
    },
    onSuccess: clearSession,
  })
  const assignmentMutation = useMutation({
    mutationFn: async (assignmentId: number) => {
      const response = await api.post<Resource<AuthUser>>('/auth/assignment', {
        assignment_id: assignmentId,
      })
      return response.data.data
    },
    onSuccess: async (user) => {
      sessionRevision += 1
      await queryClient.cancelQueries()
      clearPrivateCache()
      queryClient.setQueryData(keys.auth, user)
    },
  })
  return {
    user: query.data,
    isAuthenticated: !!query.data?.active_assignment,
    isAwaitingAssignment: !!query.data && !query.data.active_assignment,
    isLoading: query.isPending,
    error: query.error,
    retry: query.refetch,
    login: loginMutation.mutateAsync,
    logout: logoutMutation.mutateAsync,
    selectAssignment: assignmentMutation.mutateAsync,
    loginMutation,
    logoutMutation,
    assignmentMutation,
    hasPermission: (permission: string) =>
      !!query.data?.permissions.includes(permission),
    isSuperadmin: query.data?.role?.slug === 'superadmin',
    canWarehouse: ['warehouse', 'superadmin'].includes(
      query.data?.role?.slug ?? '',
    ),
  }
}
export const getSessionRevision = () => sessionRevision
