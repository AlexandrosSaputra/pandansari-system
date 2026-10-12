import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  ErrorState,
  FormField,
  Input,
  Modal,
  Select,
} from '../../components/ui'
import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { SaveForm } from '../../components/ui/SaveForm'
import { useList, useSave } from '../../lib/queries'
import type { OperationalUnit, Role, User } from '../../types/api'
const schema = z
  .object({
    name: z.string().trim().min(1, 'Nama wajib diisi.').max(255),
    email: z.email('Email tidak valid.').max(255),
    password: z.string(),
    password_confirmation: z.string(),
    role_id: z.number().int().positive('Pilih role.'),
    operational_unit_id: z.number().int().positive().nullable(),
    is_active: z.boolean(),
  })
  .superRefine((value, ctx) => {
    if (
      value.password &&
      (value.password.length < 12 ||
        !/[a-z]/.test(value.password) ||
        !/[A-Z]/.test(value.password) ||
        !/\d/.test(value.password))
    )
      ctx.addIssue({
        code: 'custom',
        path: ['password'],
        message: 'Minimal 12 karakter, huruf besar, huruf kecil, dan angka.',
      })
    if (value.password !== value.password_confirmation)
      ctx.addIssue({
        code: 'custom',
        path: ['password_confirmation'],
        message: 'Konfirmasi kata sandi tidak sama.',
      })
  })
type Values = z.infer<typeof schema>
export function UserForm({
  user,
  onClose,
  onSaved,
}: {
  user?: User
  onClose: () => void
  onSaved: () => void
}) {
  const roles = useList<Role>('/roles', { per_page: 100 })
  const [unitLabel, setUnitLabel] = useState(user?.operational_unit?.name ?? '')
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: user?.name ?? '',
      email: user?.email ?? '',
      password: '',
      password_confirmation: '',
      role_id: user?.role?.id ?? 0,
      operational_unit_id: user?.operational_unit?.id ?? null,
      is_active: user?.is_active ?? true,
    },
  })
  const [roleId, operationalUnitId] = useWatch({
    control: form.control,
    name: ['role_id', 'operational_unit_id'],
  })
  const selectedRole = roles.data?.data.find((role) => role.id === roleId)
  const save = useSave<Partial<Values>>('/users', user?.id, [
    '/activity-logs',
    'auth',
  ])
  const mutation = {
    ...save,
    mutateAsync: async (values: Values) => {
      if (!user && !values.password) {
        form.setError('password', { message: 'Kata sandi wajib diisi.' })
        throw new Error('Kata sandi wajib diisi.')
      }
      if (selectedRole?.slug === 'admin' && !values.operational_unit_id) {
        form.setError('operational_unit_id', {
          message: 'Unit wajib dipilih untuk Admin.',
        })
        throw new Error('Unit wajib dipilih.')
      }
      const body: Partial<Values> = {
        ...values,
        operational_unit_id:
          selectedRole?.slug === 'admin' ? values.operational_unit_id : null,
      }
      if (!values.password) {
        delete body.password
        delete body.password_confirmation
      }
      return save.mutateAsync(body)
    },
  }
  const errors = form.formState.errors
  return (
    <Modal
      title={user ? 'Edit Pengguna' : 'Tambah Pengguna'}
      onClose={onClose}
      busy={save.isPending}
    >
      <SaveForm
        form={form}
        mutation={mutation}
        onClose={onClose}
        onSaved={onSaved}
        wasActive={user?.is_active}
        blocked={!roles.data || !!roles.error}
      >
        <div className="form-grid">
          <FormField label="Nama" error={errors.name?.message}>
            <Input {...form.register('name')} />
          </FormField>
          <FormField label="Email" error={errors.email?.message}>
            <Input
              type="email"
              autoComplete="off"
              {...form.register('email')}
            />
          </FormField>
          <FormField
            label={user ? 'Kata sandi baru (opsional)' : 'Kata sandi'}
            error={errors.password?.message}
            hint="Minimal 12 karakter, huruf besar/kecil dan angka."
          >
            <Input
              type="password"
              autoComplete="new-password"
              {...form.register('password')}
            />
          </FormField>
          <FormField
            label="Konfirmasi kata sandi"
            error={errors.password_confirmation?.message}
          >
            <Input
              type="password"
              autoComplete="new-password"
              {...form.register('password_confirmation')}
            />
          </FormField>
          <FormField label="Role" error={errors.role_id?.message}>
            <Select
              {...form.register('role_id', {
                valueAsNumber: true,
                onChange: () => {
                  form.setValue('operational_unit_id', null)
                  setUnitLabel('')
                },
              })}
            >
              <option value={0}>Pilih role</option>
              {roles.data?.data
                .filter((role) => role.is_active)
                .map((role) => (
                  <option key={role.id} value={role.id}>
                    {role.name}
                  </option>
                ))}
            </Select>
          </FormField>
          <div>
            <FormField
              label="Unit operasional"
              error={errors.operational_unit_id?.message}
            >
              <ResourcePicker<OperationalUnit>
                path="/operational-units"
                params={{ is_active: 1 }}
                value={operationalUnitId ?? undefined}
                selectedLabel={unitLabel}
                enabled={selectedRole?.slug === 'admin'}
                identify={(row) => row.id}
                describe={(row) => `${row.code} · ${row.name}`}
                onChange={(row) => {
                  form.setValue('operational_unit_id', row.id, {
                    shouldValidate: true,
                  })
                  setUnitLabel(row.name)
                }}
              />
            </FormField>
          </div>
        </div>
        {roles.error && (
          <ErrorState
            error={roles.error}
            retry={() => {
              void roles.refetch()
            }}
          />
        )}
        <label className="check-field">
          <input type="checkbox" {...form.register('is_active')} />
          Aktif
        </label>
      </SaveForm>
    </Modal>
  )
}
