import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormField, Input, Modal, Select } from '../../components/ui'
import { SaveForm } from '../../components/ui/SaveForm'
import { useSave } from '../../lib/queries'
import type { OperationalUnit } from '../../types/api'
const schema = z.object({
  code: z.string().trim().min(1, 'Kode wajib diisi.').max(255),
  name: z.string().trim().min(1, 'Nama wajib diisi.').max(255),
  type: z.enum(['OUTLET', 'TICKETING']),
  scanner_enabled: z.boolean(),
  is_active: z.boolean(),
})
export function UnitForm({
  unit,
  onClose,
  onSaved,
}: {
  unit?: OperationalUnit
  onClose: () => void
  onSaved: () => void
}) {
  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: unit?.code ?? '',
      name: unit?.name ?? '',
      type: unit?.type ?? 'OUTLET',
      scanner_enabled: unit?.scanner_enabled ?? false,
      is_active: unit?.is_active ?? true,
    },
  })
  const unitType = useWatch({ control: form.control, name: 'type' })
  const mutation = useSave<z.infer<typeof schema>>(
    '/operational-units',
    unit?.id,
    ['auth', '/transaction-catalog'],
  )
  return (
    <Modal
      title={unit ? 'Edit Outlet / Ticketing' : 'Tambah Outlet / Ticketing'}
      onClose={onClose}
      busy={mutation.isPending}
    >
      <SaveForm
        form={form}
        mutation={mutation}
        onClose={onClose}
        onSaved={onSaved}
        wasActive={unit?.is_active}
        successMessage={
          unit
            ? 'Outlet / Ticketing berhasil diperbarui.'
            : 'Outlet / Ticketing berhasil ditambahkan.'
        }
      >
        <FormField label="Kode" error={form.formState.errors.code?.message}>
          <Input {...form.register('code')} />
        </FormField>
        <FormField label="Nama" error={form.formState.errors.name?.message}>
          <Input {...form.register('name')} />
        </FormField>
        <FormField label="Jenis">
          <Select
            {...form.register('type', {
              onChange: (e) => {
                if (e.target.value === 'TICKETING')
                  form.setValue('scanner_enabled', false)
              },
            })}
          >
            <option value="OUTLET">Outlet</option>
            <option value="TICKETING">Ticketing</option>
          </Select>
        </FormField>
        <label className="check-field">
          <input
            type="checkbox"
            disabled={unitType === 'TICKETING'}
            {...form.register('scanner_enabled')}
          />
          Input barcode diaktifkan
        </label>
        <label className="check-field">
          <input type="checkbox" {...form.register('is_active')} />
          Aktif
        </label>
      </SaveForm>
    </Modal>
  )
}
