import { useSearchParams } from 'react-router-dom'
import type { QueryParams } from '../types/api'
export function useFilters() {
  const [search, setSearch] = useSearchParams()
  const params: QueryParams = Object.fromEntries(search)
  params.page = Math.max(1, Number(search.get('page')) || 1)
  params.per_page = [15, 30, 50, 100].includes(Number(search.get('per_page')))
    ? Number(search.get('per_page'))
    : 15
  const set = (name: string, value: string) =>
    setSearch(
      (previous) => {
        const next = new URLSearchParams(previous)
        if (value) next.set(name, value)
        else next.delete(name)
        if (name !== 'page') next.delete('page')
        return next
      },
      { replace: true },
    )
  return {
    params,
    set,
    value: (name: string) => search.get(name) ?? '',
    reset: () => setSearch({}),
  }
}
