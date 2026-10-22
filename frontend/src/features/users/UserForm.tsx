import { useState } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ErrorState, FormField, Input, Modal, Select } from '../../components/ui'
import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { SaveForm } from '../../components/ui/SaveForm'
import { useList, useSave } from '../../lib/queries'
import type { OperationalUnit, Role, User } from '../../types/api'

const assignmentSchema = z.object({
  role_id: z.number().int().positive('Pilih role.'),
  operational_unit_id: z.number().int().positive().nullable(),
  is_active: z.boolean(),
})
const schema = z
  .object({
    name: z.string().trim().min(1, 'Nama wajib diisi.').max(255),
    username: z.string().trim().min(3, 'Username minimal 3 karakter.').max(50).regex(/^[a-zA-Z0-9_-]+$/, 'Gunakan huruf, angka, tanda hubung, atau garis bawah.'),
    email: z.email('Email tidak valid.').max(255),
    password: z.string(),
    password_confirmation: z.string(),
    assignments: z.array(assignmentSchema).min(1, 'Tambahkan minimal satu role.'),
    is_active: z.boolean(),
  })
  .superRefine((value, ctx) => {
    if (value.password && (value.password.length < 12 || !/[a-z]/.test(value.password) || !/[A-Z]/.test(value.password) || !/\d/.test(value.password)))
      ctx.addIssue({ code: 'custom', path: ['password'], message: 'Minimal 12 karakter, huruf besar, huruf kecil, dan angka.' })
    if (value.password !== value.password_confirmation)
      ctx.addIssue({ code: 'custom', path: ['password_confirmation'], message: 'Konfirmasi kata sandi tidak sama.' })
  })

type Values = z.infer<typeof schema>

export function UserForm({ user, onClose, onSaved }: { user?: User; onClose: () => void; onSaved: () => void }) {
  const roles = useList<Role>('/roles', { per_page: 100 })
  const [unitLabels, setUnitLabels] = useState<Record<number, string>>(
    Object.fromEntries((user?.assignments ?? []).map((assignment, index) => [index, assignment.operational_unit?.name ?? ''])),
  )
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: user?.name ?? '', username: user?.username ?? '', email: user?.email ?? '', password: '', password_confirmation: '',
      assignments: user?.assignments?.length
        ? user.assignments.map((assignment) => ({ role_id: assignment.role.id, operational_unit_id: assignment.operational_unit?.id ?? null, is_active: assignment.is_active }))
        : user?.role
          ? [{ role_id: user.role.id, operational_unit_id: user.operational_unit?.id ?? null, is_active: true }]
        : [{ role_id: 0, operational_unit_id: null, is_active: true }],
      is_active: user?.is_active ?? true,
    },
  })
  const fields = useFieldArray({ control: form.control, name: 'assignments' })
  const assignments = useWatch({ control: form.control, name: 'assignments' })
  const save = useSave<Partial<Values>>('/users', user?.id, ['/activity-logs', 'auth'])
  const mutation = {
    ...save,
    mutateAsync: async (values: Values) => {
      if (!user && !values.password) {
        form.setError('password', { message: 'Kata sandi wajib diisi.' })
        throw new Error('Kata sandi wajib diisi.')
      }
      let invalid = false
      const normalized = values.assignments.map((assignment, index) => {
        const role = roles.data?.data.find((item) => item.id === assignment.role_id)
        if (role?.slug === 'admin' && !assignment.operational_unit_id) {
          form.setError(`assignments.${index}.operational_unit_id`, { message: 'Unit wajib dipilih untuk Admin.' })
          invalid = true
        }
        return { ...assignment, operational_unit_id: role?.slug === 'admin' ? assignment.operational_unit_id : null }
      })
      if (invalid) throw new Error('Lengkapi unit untuk role Admin.')
      const body: Partial<Values> = { ...values, assignments: normalized }
      if (!values.password) {
        delete body.password
        delete body.password_confirmation
      }
      return save.mutateAsync(body)
    },
  }
  const errors = form.formState.errors

  return (
    <Modal title={user ? 'Edit Pengguna' : 'Tambah Pengguna'} onClose={onClose} busy={save.isPending}>
      <SaveForm form={form} mutation={mutation} onClose={onClose} onSaved={onSaved} wasActive={user?.is_active} blocked={!roles.data || !!roles.error}>
        <div className="form-grid">
          <FormField label="Nama" error={errors.name?.message}><Input {...form.register('name')} /></FormField>
          <FormField label="Username" error={errors.username?.message}><Input autoComplete="username" {...form.register('username')} /></FormField>
          <FormField label="Email" error={errors.email?.message}><Input type="email" autoComplete="off" {...form.register('email')} /></FormField>
          <FormField label={user ? 'Kata sandi baru (opsional)' : 'Kata sandi'} error={errors.password?.message} hint="Minimal 12 karakter, huruf besar/kecil dan angka."><Input type="password" autoComplete="new-password" {...form.register('password')} /></FormField>
          <FormField label="Konfirmasi kata sandi" error={errors.password_confirmation?.message}><Input type="password" autoComplete="new-password" {...form.register('password_confirmation')} /></FormField>
        </div>
        <div className="space-y-3">
          <div className="flex items-center justify-between gap-3">
            <div><h3 className="font-semibold">Role dan unit</h3><p className="text-sm text-slate-500">User memilih salah satu assignment untuk setiap sesi.</p></div>
            <Button variant="secondary" onClick={() => fields.append({ role_id: 0, operational_unit_id: null, is_active: true })}>+ Tambah role</Button>
          </div>
          {fields.fields.map((field, index) => {
            const selectedRole = roles.data?.data.find((role) => role.id === assignments[index]?.role_id)
            return (
              <section key={field.id} className="rounded-xl border border-slate-200 p-4">
                <div className="form-grid">
                  <FormField label="Role" error={errors.assignments?.[index]?.role_id?.message}>
                    <Select {...form.register(`assignments.${index}.role_id`, { valueAsNumber: true, onChange: () => {
                      form.setValue(`assignments.${index}.operational_unit_id`, null)
                      setUnitLabels((current) => ({ ...current, [index]: '' }))
                    } })}>
                      <option value={0}>Pilih role</option>
                      {roles.data?.data.filter((role) => role.is_active).map((role) => <option key={role.id} value={role.id}>{role.name}</option>)}
                    </Select>
                  </FormField>
                  <FormField label="Unit operasional" error={errors.assignments?.[index]?.operational_unit_id?.message}>
                    <ResourcePicker<OperationalUnit> path="/operational-units" params={{ is_active: 1 }} value={assignments[index]?.operational_unit_id ?? undefined} selectedLabel={unitLabels[index] ?? ''} enabled={selectedRole?.slug === 'admin'} identify={(row) => row.id} describe={(row) => `${row.code} · ${row.name}`} onChange={(row) => {
                      form.setValue(`assignments.${index}.operational_unit_id`, row.id, { shouldValidate: true })
                      setUnitLabels((current) => ({ ...current, [index]: row.name }))
                    }} />
                  </FormField>
                </div>
                <div className="mt-3 flex items-center justify-between gap-3">
                  <label className="check-field"><input type="checkbox" {...form.register(`assignments.${index}.is_active`)} />Assignment aktif</label>
                  {fields.fields.length > 1 && <Button variant="danger" onClick={() => fields.remove(index)}>Hapus</Button>}
                </div>
              </section>
            )
          })}
          {typeof errors.assignments?.message === 'string' && <small role="alert" className="text-red-700">{errors.assignments.message}</small>}
        </div>
        {roles.error && <ErrorState error={roles.error} retry={() => void roles.refetch()} />}
        <label className="check-field"><input type="checkbox" {...form.register('is_active')} />Akun aktif</label>
      </SaveForm>
    </Modal>
  )
}
