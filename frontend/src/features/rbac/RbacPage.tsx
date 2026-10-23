import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import {
  Button,
  Card,
  ConfirmDialog,
  ErrorState,
  PageHeader,
  Pagination,
  Skeleton,
} from '../../components/ui'
import { api } from '../../lib/api'
import { apiError } from '../../lib/errors'
import { notifications } from '../../lib/notifications'
import { useInvalidate, useList } from '../../lib/queries'
import { queryClient } from '../../lib/queryClient'
import { keys } from '../../lib/queries'
import type { Permission, Role } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { permissionLabels } from './permissionLabels'
function PermissionEditor({ role }: { role: Role }) {
  const auth = useAuth()
  const [page, setPage] = useState(1)
  const permissions = useList<Permission>('/permissions', {
    per_page: 100,
    page,
  })
  const [selected, setSelected] = useState(() => new Set(role.permissions))
  const [confirm, setConfirm] = useState(false)
  const invalidate = useInvalidate()
  const editable =
    auth.isSuperadmin &&
    auth.hasPermission('rbac.manage') &&
    role.slug !== 'superadmin'
  const mutation = useMutation({
    mutationFn: () =>
      api.put(`/roles/${role.id}/permissions`, { permissions: [...selected] }),
    onSuccess: async () => {
      setConfirm(false)
      notifications.success('Permission berhasil disimpan.')
      await Promise.all([
        invalidate(['/roles']),
        queryClient.invalidateQueries({ queryKey: keys.auth }),
      ])
    },
    onError: (error) => notifications.error(apiError(error).message),
  })
  const groups = [
    ...new Set(
      permissions.data?.data.map((permission) => permission.slug.split('.')[0]),
    ),
  ]
  return (
    <Card className="p-5">
      <div className="mb-5 flex items-center justify-between">
        <div>
          <h2>{role.name}</h2>
          <p className="mt-1 text-sm text-slate-500">
            {role.slug === 'superadmin'
              ? 'Seluruh permission Superadmin dikelola oleh sistem.'
              : 'Atur akses fitur untuk role ini.'}
          </p>
        </div>
        {editable && (
          <Button
            onClick={() => setConfirm(true)}
            disabled={permissions.isPending}
          >
            Simpan
          </Button>
        )}
      </div>
      {permissions.error && (
        <ErrorState
          error={permissions.error}
          retry={() => {
            void permissions.refetch()
          }}
        />
      )}
      {permissions.isPending ? (
        <Skeleton />
      ) : (
        <div className="grid gap-5 md:grid-cols-2">
          {groups.map((group) => (
            <fieldset
              key={group}
              disabled={!editable || mutation.isPending}
              className="rounded-lg border border-slate-200 p-4"
            >
              <legend className="px-2 text-sm font-semibold capitalize">
                {group}
              </legend>
              <div className="space-y-3">
                {permissions.data?.data
                  .filter((permission) =>
                    permission.slug.startsWith(`${group}.`),
                  )
                  .map((permission) => (
                    <label
                      key={permission.id}
                      className="check-field items-start"
                    >
                      <input
                        type="checkbox"
                        checked={selected.has(permission.slug)}
                        onChange={(e) => {
                          setSelected((previous) => {
                            const next = new Set(previous)
                            if (e.target.checked) next.add(permission.slug)
                            else next.delete(permission.slug)
                            return next
                          })
                        }}
                      />
                      <span>
                        <span className="block text-xs font-medium">
                          {permission.slug}
                        </span>
                        <span className="text-xs text-slate-500">
                          {permissionLabels[permission.slug] ??
                            permission.description ??
                            permission.name}
                        </span>
                      </span>
                    </label>
                  ))}
              </div>
            </fieldset>
          ))}
        </div>
      )}
      <Pagination meta={permissions.data?.meta} onPage={setPage} />
      {confirm && (
        <ConfirmDialog
          title="Perbarui permission?"
          busy={mutation.isPending}
          error={mutation.error}
          onClose={() => setConfirm(false)}
          onConfirm={() => mutation.mutate()}
        >
          Perubahan akan memengaruhi akses semua pengguna dengan role{' '}
          {role.name}.
        </ConfirmDialog>
      )}
    </Card>
  )
}
export function RbacPage() {
  const [page, setPage] = useState(1)
  const roles = useList<Role>('/roles', { page, per_page: 15 })
  const [roleId, setRoleId] = useState<number>()
  const current =
    roles.data?.data.find((role) => role.id === roleId) ?? roles.data?.data[0]
  return (
    <>
      <PageHeader
        title="Role & Permission"
        description="Akses aplikasi berdasarkan tanggung jawab setiap pengguna."
      />
      {roles.error && (
        <ErrorState
          error={roles.error}
          retry={() => {
            void roles.refetch()
          }}
        />
      )}
      {roles.isPending ? (
        <Skeleton />
      ) : (
        <div className="grid items-start gap-5 lg:grid-cols-[220px_1fr]">
          <Card className="p-3">
            <div className="space-y-2">
              {roles.data?.data.map((role) => (
                <Button
                  className="w-full justify-start"
                  variant={current?.id === role.id ? 'primary' : 'ghost'}
                  key={role.id}
                  onClick={() => setRoleId(role.id)}
                >
                  {role.name}
                </Button>
              ))}
            </div>
            {(roles.data?.meta.last_page ?? 1) > 1 && (
              <Pagination meta={roles.data?.meta} onPage={setPage} />
            )}
          </Card>
          {current && <PermissionEditor key={current.id} role={current} />}
        </div>
      )}
    </>
  )
}
