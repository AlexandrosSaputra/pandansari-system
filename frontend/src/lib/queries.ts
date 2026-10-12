import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './api'
import type { Page, QueryParams, Resource } from '../types/api'

export const keys = {
  auth: ['auth', 'me'] as const,
  list: (path: string, params: QueryParams = {}) =>
    [path, 'list', params] as const,
  detail: (path: string) => [path, 'detail'] as const,
  cart: (user: number, unit: number) => ['cart', user, unit] as const,
}
export async function getPage<T>(
  path: string,
  params: QueryParams = {},
  signal?: AbortSignal,
) {
  return (await api.get<Page<T>>(path, { params, signal })).data
}
export async function getResource<T>(path: string, signal?: AbortSignal) {
  return (await api.get<Resource<T>>(path, { signal })).data.data
}
export function useList<T>(
  path: string,
  params: QueryParams = {},
  enabled = true,
) {
  return useQuery({
    queryKey: keys.list(path, params),
    queryFn: ({ signal }) => getPage<T>(path, params, signal),
    enabled,
    placeholderData: (previous, previousQuery) =>
      previousQuery?.queryKey[0] === path ? previous : undefined,
  })
}
export function useDetail<T>(path: string, enabled = true) {
  return useQuery({
    queryKey: keys.detail(path),
    queryFn: ({ signal }) => getResource<T>(path, signal),
    enabled,
  })
}
// Invalidate only the affected resource families, including their detail endpoints.
export function useInvalidate() {
  const client = useQueryClient()
  return (paths: string[]) =>
    client.invalidateQueries({
      predicate: (query) =>
        typeof query.queryKey[0] === 'string' &&
        paths.some(
          (path) =>
            query.queryKey[0] === path ||
            (query.queryKey[0] as string).startsWith(`${path}/`),
        ),
    })
}
export function useSave<T>(
  path: string,
  id: number | undefined,
  affected: string[],
) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: async (body: T) =>
      id ? api.patch(`${path}/${id}`, body) : api.post(path, body),
    onSuccess: () => invalidate([path, ...affected]),
  })
}
