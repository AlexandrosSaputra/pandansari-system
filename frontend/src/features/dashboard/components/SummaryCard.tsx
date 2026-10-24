import type { ReactNode } from 'react'
import { Card, ErrorState, Skeleton } from '../../../components/ui'
import { formatNumber } from '../../../utils/format'
export function SummaryCard({
  title,
  tone = 'inventory',
  query,
  children,
}: {
  title: string
  tone?: 'inventory' | 'attention' | 'success'
  query: {
    data?: { meta: { total: number } }
    isPending: boolean
    error: unknown
    refetch: () => unknown
  }
  children: ReactNode
}) {
  return (
    <Card className={`dashboard-summary dashboard-summary-${tone} p-5`}>
      <p className="dashboard-summary-label text-sm font-semibold">{title}</p>
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
