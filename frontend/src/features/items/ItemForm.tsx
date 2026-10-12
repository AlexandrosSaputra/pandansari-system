import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormField, Input, Modal, Select } from '../../components/ui'
import { SaveForm } from '../../components/ui/SaveForm'
import { useSave } from '../../lib/queries'
import type { Item } from '../../types/api'
const schema = z.object({
  item_code: z.string().trim().min(1, 'Kode wajib diisi.').max(255),
  barcode: z.string().max(255),
  name: z.string().trim().min(1, 'Nama wajib diisi.').max(255),
  type: z.enum(['PRODUCT', 'SERVICE']),
  uom: z.string().max(255),
  visitor_factor: z.number().int().min(0).max(4294967295),
  is_active: z.boolean(),
})
type Values = z.infer<typeof schema>
export function ItemForm({
  item,
  onClose,
  onSaved,
}: {
  item?: Item
  onClose: () => void
  onSaved: () => void
}) {
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      item_code: item?.item_code ?? '',
      barcode: item?.barcode ?? '',
      name: item?.name ?? '',
      type: item?.type ?? 'PRODUCT',
      uom: item?.uom ?? '',
      visitor_factor: item?.visitor_factor ?? 0,
      is_active: item?.is_active ?? true,
    },
  })
  const save = useSave<
    | Values
    | (Omit<Values, 'barcode' | 'uom'> & {
        barcode: string | null
        uom: string | null
      })
  >('/items', item?.id, [
    '/operational-units',
    '/transaction-catalog',
    '/inventory/warehouse',
  ])
  const mutation = {
    ...save,
    mutateAsync: (values: Values) =>
      save.mutateAsync({
        ...values,
        barcode: values.barcode || null,
        uom: values.uom || null,
        visitor_factor: values.type === 'PRODUCT' ? 0 : values.visitor_factor,
      }),
  }
  const itemType = useWatch({ control: form.control, name: 'type' })
  const errors = form.formState.errors
  return (
    <Modal
      title={item ? 'Edit Item' : 'Tambah Item'}
      onClose={onClose}
      busy={save.isPending}
    >
      <SaveForm
        form={form}
        mutation={mutation}
        onClose={onClose}
        onSaved={onSaved}
        wasActive={item?.is_active}
      >
        <div className="form-grid">
          <FormField label="Kode item" error={errors.item_code?.message}>
            <Input {...form.register('item_code')} />
          </FormField>
          <FormField label="Barcode" error={errors.barcode?.message}>
            <Input {...form.register('barcode')} />
          </FormField>
          <FormField label="Nama" error={errors.name?.message}>
            <Input {...form.register('name')} />
          </FormField>
          <FormField label="Jenis" error={errors.type?.message}>
            <Select
              {...form.register('type', {
                onChange: (e) => {
                  if (e.target.value === 'PRODUCT')
                    form.setValue('visitor_factor', 0)
                },
              })}
            >
              <option value="PRODUCT">Produk</option>
              <option value="SERVICE">Layanan</option>
            </Select>
          </FormField>
          <FormField label="Satuan (UOM)" error={errors.uom?.message}>
            <Input {...form.register('uom')} />
          </FormField>
          <FormField
            label="Faktor pengunjung"
            error={errors.visitor_factor?.message}
          >
            <Input
              type="number"
              min={0}
              disabled={itemType === 'PRODUCT'}
              {...form.register('visitor_factor', { valueAsNumber: true })}
            />
          </FormField>
        </div>
        <label className="check-field">
          <input type="checkbox" {...form.register('is_active')} />
          Aktif
        </label>
      </SaveForm>
    </Modal>
  )
}
