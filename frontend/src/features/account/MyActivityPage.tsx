import { useState } from 'react'
import { Button, ListContent, Modal, PageHeader } from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { dateFilters } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { ActivityLog } from '../../types/api'
import { formatDateTime } from '../../utils/format'

const activityLabels: Record<string, string> = {
  AUTH_LOGIN_SUCCESS: 'Masuk ke sistem',
  AUTH_LOGOUT: 'Keluar dari sistem',
  AUTH_ASSIGNMENT_SELECTED: 'Mengganti unit atau role aktif',
  AUTH_LOGIN_LOCKED: 'Akun dikunci karena percobaan masuk gagal',
  ACCOUNT_PROFILE_UPDATED: 'Memperbarui profil',
  ACCOUNT_PASSWORD_UPDATED: 'Mengganti kata sandi',
}

export function MyActivityPage() {
  const filters = useFilters()
  const query = useList<ActivityLog>('/auth/activity-logs', filters.params)
  const [selected, setSelected] = useState<ActivityLog | null>(null)

  return (
    <>
      <PageHeader
        title="Log Aktivitas Saya"
        description="Riwayat aktivitas keamanan dan operasional yang dilakukan oleh akun Anda."
      />
      <FilterBar
        filters={filters}
        search={false}
        definitions={[
          { name: 'action', label: 'Aksi' },
          { name: 'subject_type', label: 'Jenis subjek' },
          ...dateFilters,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada aktivitas untuk akun ini"
        onPage={(page) => filters.set('page', String(page))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          { title: 'Waktu', render: (row) => formatDateTime(row.created_at) },
          {
            title: 'Aktivitas',
            render: (row) => activityLabels[row.action] ?? row.action,
          },
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
        <Modal title="Detail Aktivitas Saya" onClose={() => setSelected(null)}>
          <dl className="detail-grid">
            <div>
              <dt>Waktu</dt>
              <dd>{formatDateTime(selected.created_at)}</dd>
            </div>
            <div>
              <dt>Aktivitas</dt>
              <dd>{activityLabels[selected.action] ?? selected.action}</dd>
            </div>
            <div>
              <dt>Subjek</dt>
              <dd>{selected.subject_type ?? '—'}</dd>
            </div>
            <div>
              <dt>ID subjek</dt>
              <dd>{selected.subject_id ?? '—'}</dd>
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
