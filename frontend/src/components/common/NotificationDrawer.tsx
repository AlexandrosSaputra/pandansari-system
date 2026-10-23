import { Link } from 'react-router-dom'
import { Drawer, EmptyState, ErrorState, Skeleton } from '../ui'
import { presentActivity } from '../../features/account/activityPresentation'
import { useList } from '../../lib/queries'
import type { ActivityLog } from '../../types/api'

function ActivityIcon({
  tone,
}: {
  tone: ReturnType<typeof presentActivity>['tone']
}) {
  const path = {
    account: (
      <>
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21a8 8 0 0 1 16 0" />
      </>
    ),
    inventory: (
      <>
        <path d="m4 7 8-4 8 4-8 4Z" />
        <path d="m4 7 8 4 8-4M4 7v10l8 4 8-4V7M12 11v10" />
      </>
    ),
    transaction: (
      <>
        <path d="M6 2h12v20l-3-2-3 2-3-2-3 2Z" />
        <path d="M9 7h6M9 11h6M9 15h4" />
      </>
    ),
    transfer: (
      <>
        <path d="M7 7h11l-3-3M17 17H6l3 3M18 7l-3 3M6 17l3-3" />
      </>
    ),
    system: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 8v4l3 2" />
      </>
    ),
  }[tone]

  return (
    <span className={`notification-item-icon is-${tone}`}>
      <svg
        aria-hidden="true"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="1.8"
        viewBox="0 0 24 24"
      >
        {path}
      </svg>
    </span>
  )
}

function dateHeading(value: string | null): string {
  if (!value) return 'Waktu tidak tersedia'
  const date = new Date(value)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  const key = date.toLocaleDateString('en-CA')

  if (key === today.toLocaleDateString('en-CA')) return 'Hari ini'
  if (key === yesterday.toLocaleDateString('en-CA')) return 'Kemarin'
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(date)
}

function timeLabel(value: string | null): string {
  return value
    ? new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
      }).format(new Date(value))
    : '—'
}

export function NotificationDrawer({ onClose }: { onClose: () => void }) {
  const query = useList<ActivityLog>('/auth/activity-logs', { per_page: 20 })
  const activities = query.data?.data ?? []
  const grouped = activities.reduce<Map<string, ActivityLog[]>>(
    (groups, activity) => {
      const heading = dateHeading(activity.created_at)
      groups.set(heading, [...(groups.get(heading) ?? []), activity])
      return groups
    },
    new Map(),
  )

  return (
    <Drawer title="Riwayat Notifikasi" onClose={onClose} side="right">
      <div className="notification-panel-intro">
        <p>Aktivitas terbaru yang tercatat untuk akun Anda.</p>
        <span>{query.data?.meta.total ?? 0} aktivitas tercatat</span>
      </div>

      {query.isPending ? (
        <Skeleton rows={6} />
      ) : query.error ? (
        <div className="p-4">
          <ErrorState error={query.error} retry={() => void query.refetch()} />
        </div>
      ) : activities.length === 0 ? (
        <EmptyState message="Belum ada riwayat aktivitas." />
      ) : (
        <div className="notification-groups">
          {[...grouped.entries()].map(([heading, rows]) => (
            <section key={heading} className="notification-group">
              <h3>{heading}</h3>
              <ol>
                {rows.map((activity) => {
                  const presentation = presentActivity(activity)
                  return (
                    <li key={activity.id} className="notification-item">
                      <ActivityIcon tone={presentation.tone} />
                      <div className="min-w-0 flex-1">
                        <div className="notification-item-heading">
                          <p>{presentation.title}</p>
                          <time dateTime={activity.created_at ?? undefined}>
                            {timeLabel(activity.created_at)}
                          </time>
                        </div>
                        <p className="notification-item-description">
                          {presentation.description}
                        </p>
                      </div>
                    </li>
                  )
                })}
              </ol>
            </section>
          ))}
        </div>
      )}

      <div className="notification-panel-footer">
        <Link to="/account/activity" onClick={onClose}>
          Lihat semua aktivitas
          <span aria-hidden="true">→</span>
        </Link>
      </div>
    </Drawer>
  )
}
