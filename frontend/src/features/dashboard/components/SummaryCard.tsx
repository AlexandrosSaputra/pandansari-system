import type { ReactNode } from 'react'
import { Card, ErrorState, Skeleton } from '../../../components/ui'
import { formatNumber } from '../../../utils/format'
export function SummaryCard({
  title,
  query,
  children,
}: {
  title: string
  query: {
    data?: { meta: { total: number } }
    isPending: boolean
    error: unknown
    refetch: () => unknown
  }
  children: ReactNode
}) {
  return (
    <Card className="p-5">
      <p className="text-sm text-slate-500">{title}</p>
      {query.isPending ? (
        <Skeleton rows={1} />
      ) : query.error ? (
        <ErrorState
          error={query.error}
          retry={() => {
            void query.refetch()
          }}
        />
      ) : (
        <p className="my-3 text-3xl font-semibold">
          {formatNumber(query.data?.meta.total)}
        </p>
      )}
      <div className="text-xs text-slate-400">{children}</div>
    </Card>
  )
}
