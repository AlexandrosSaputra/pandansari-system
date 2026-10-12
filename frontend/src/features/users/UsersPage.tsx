import { useState } from 'react'
import {
  Badge,
  Button,
  ListContent,
  Notice,
  PageHeader,
} from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { statusFilter } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { User } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { UserForm } from './UserForm'
export function UsersPage() {
  const auth = useAuth()
  const filters = useFilters()
  const query = useList<User>('/users', filters.params)
  const [editing, setEditing] = useState<User | 'new' | null>(null)
  const [saved, setSaved] = useState(false)
  const manage = auth.isSuperadmin && auth.hasPermission('user.manage')
  return (
    <>
      <PageHeader
        title="Pengguna"
        description="Akun, peran, dan penempatan unit operasional."
        actions={
          manage && (
            <Button
              onClick={() => {
                setSaved(false)
                setEditing('new')
              }}
            >
              + Tambah Pengguna
            </Button>
          )
        }
      />
      {saved && <Notice>Pengguna berhasil disimpan.</Notice>}
      <FilterBar
        filters={filters}
        definitions={[
          {
            name: 'role',
            label: 'Role',
            options: [
              { value: 'admin', label: 'Admin' },
              { value: 'warehouse', label: 'Gudang' },
              { value: 'superadmin', label: 'Superadmin' },
            ],
          },
          {
            name: 'operational_unit_id',
            label: 'ID unit operasional',
            type: 'number',
          },
          statusFilter,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada pengguna"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          { title: 'Nama', render: (row) => <strong>{row.name}</strong> },
          { title: 'Email', render: (row) => row.email },
          { title: 'Role', render: (row) => row.role?.name ?? '—' },
          {
            title: 'Unit operasional',
            render: (row) => row.operational_unit?.name ?? '—',
          },
          { title: 'Status', render: (row) => <Badge value={row.is_active} /> },
          {
            title: 'Aksi',
            render: (row) =>
              manage ? (
                <Button
                  variant="ghost"
                  onClick={() => {
                    setSaved(false)
                    setEditing(row)
                  }}
                >
                  Edit
                </Button>
              ) : (
                '—'
              ),
          },
        ]}
      />
      {editing && (
        <UserForm
          user={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            setSaved(true)
          }}
        />
      )}
    </>
  )
}
