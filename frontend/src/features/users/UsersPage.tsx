import { useState } from 'react'
import {
  Badge,
  Button,
  ListContent,
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
  const manage = auth.isSuperadmin && auth.hasPermission('user.manage')
  return (
    <>
      {manage && (
        <div className="page-actions" aria-label="Aksi halaman">
          <Button onClick={() => setEditing('new')}>+ Tambah Pengguna</Button>
        </div>
      )}
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
          { title: 'Username', render: (row) => row.username },
          { title: 'Email', render: (row) => row.email },
          {
            title: 'Role',
            render: (row) => row.assignments?.length
              ? row.assignments.map((assignment) => assignment.role.name).join(', ')
              : row.role?.name ?? '—',
          },
          {
            title: 'Unit operasional',
            render: (row) => {
              const units = (row.assignments ?? [])
                .map((assignment) => assignment.operational_unit?.name)
                .filter(Boolean)
              return units.length ? units.join(', ') : '—'
            },
          },
          { title: 'Status', render: (row) => <Badge value={row.is_active} /> },
          {
            title: 'Aksi',
            render: (row) =>
              manage ? (
                <Button
                  variant="ghost"
                  onClick={() => setEditing(row)}
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
          onSaved={() => setEditing(null)}
        />
      )}
    </>
  )
}
