import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormField, Input, Modal, Select } from '../../components/ui'
import { SaveForm } from '../../components/ui/SaveForm'
import { useSave } from '../../lib/queries'
import type { OperationalUnit } from '../../types/api'
import { generateUnitCode, unitCodePrefix } from './unitCode'
const schema = z.object({
  code: z.string(),
  name: z.string().trim().min(1, 'Nama wajib diisi.').max(255),
  type: z.enum(['OUTLET', 'TICKETING']),
  scanner_enabled: z.boolean(),
  is_active: z.boolean(),
})
type Values = z.infer<typeof schema>

export function UnitForm({
  unit,
  onClose,
  onSaved,
}: {
  unit?: OperationalUnit
  onClose: () => void
  onSaved: () => void
}) {
  const form = useForm<Values>({
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
  const save = useSave<Values | Omit<Values, 'code'>>(
    '/operational-units',
    unit?.id,
    ['auth', '/transaction-catalog'],
  )
  const mutation = {
    ...save,
    mutateAsync: (values: Values) => {
      const { code, ...editableValues } = values

      return save.mutateAsync(
        unit
          ? editableValues
          : {
              ...editableValues,
              code: code || generateUnitCode(values.name),
            },
      )
    },
  }
  return (
    <Modal
      title={unit ? 'Edit Outlet / Ticketing' : 'Tambah Outlet / Ticketing'}
      onClose={onClose}
      busy={save.isPending}
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
        <FormField
          label="Kode"
          error={form.formState.errors.code?.message}
          hint="Kode dibuat otomatis oleh sistem dengan format 2 huruf dan 4 angka."
        >
          <Input
            placeholder="Dibuat otomatis (contoh: AB1234)"
            className="cursor-not-allowed bg-slate-100 text-slate-500"
            {...form.register('code')}
            readOnly
            aria-readonly="true"
          />
        </FormField>
        <FormField label="Nama" error={form.formState.errors.name?.message}>
          <Input
            placeholder="Masukkan nama outlet atau ticketing"
            {...form.register('name', {
              onBlur: (event) => {
                const name = event.target.value.trim()

                if (!unit && name) {
                  const code = form.getValues('code')
                  const prefix = unitCodePrefix(name)

                  if (!code || !code.startsWith(prefix)) {
                    form.setValue('code', generateUnitCode(name), {
                      shouldValidate: true,
                    })
                  }
                }
              },
            })}
          />
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
