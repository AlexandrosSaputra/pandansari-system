import { useState } from 'react'
import { Button, ListContent, Modal, PageHeader } from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { dateFilters } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { ActivityLog } from '../../types/api'
import { formatDateTime } from '../../utils/format'
export function ActivityLogsPage() {
  const filters = useFilters()
  const query = useList<ActivityLog>('/activity-logs', filters.params)
  const [selected, setSelected] = useState<ActivityLog | null>(null)
  return (
    <>
      <PageHeader
        title="Log Aktivitas"
        description="Jejak perubahan dan aktivitas pengguna."
      />
      <FilterBar
        filters={filters}
        search={false}
        definitions={[
          { name: 'actor_id', label: 'ID pelaku', type: 'number' },
          { name: 'action', label: 'Aksi' },
          { name: 'subject_type', label: 'Jenis subjek' },
          ...dateFilters,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada aktivitas"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          { title: 'Waktu', render: (row) => formatDateTime(row.created_at) },
          { title: 'Pelaku', render: (row) => row.actor?.name ?? 'Sistem' },
          { title: 'Aksi', render: (row) => row.action },
          { title: 'Jenis subjek', render: (row) => row.subject_type ?? '—' },
          { title: 'ID subjek', render: (row) => row.subject_id ?? '—' },
          {
            title: 'Detail',
            render: (row) => (
              <Button variant="ghost" onClick={() => setSelected(row)}>
                Lihat detail
              </Button>
            ),
          },
        ]}
      />
      {selected && (
        <Modal title="Detail Aktivitas" onClose={() => setSelected(null)}>
          <dl className="detail-grid">
            <div>
              <dt>Waktu</dt>
              <dd>{formatDateTime(selected.created_at)}</dd>
            </div>
            <div>
              <dt>Pelaku</dt>
              <dd>{selected.actor?.name ?? 'Sistem'}</dd>
            </div>
            <div>
              <dt>Aksi</dt>
              <dd>{selected.action}</dd>
            </div>
            <div>
              <dt>Subjek</dt>
              <dd>
                {selected.subject_type} #{selected.subject_id}
              </dd>
            </div>
          </dl>
          <h2 className="mb-3">Metadata</h2>
          <pre className="max-h-96 overflow-auto rounded-lg bg-slate-50 p-4 text-xs whitespace-pre-wrap break-all">
            {JSON.stringify(selected.metadata, null, 2) ??
              'Tidak ada metadata.'}
          </pre>
        </Modal>
      )}
    </>
  )
}
