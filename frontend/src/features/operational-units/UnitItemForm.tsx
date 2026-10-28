import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormField, Input, Modal } from '../../components/ui'
import { SaveForm } from '../../components/ui/SaveForm'
import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { useSave } from '../../lib/queries'
import type { Item, UnitItem } from '../../types/api'
const schema = z.object({
  item_id: z.number().int().positive('Pilih item.'),
  selling_price: z
    .string()
    .regex(
      /^\d+(\.\d{1,2})?$/,
      'Harga harus berupa angka, maksimal dua desimal.',
    )
    .refine(
      (value) => Number(value) <= 9999999999999.99,
      'Harga terlalu besar.',
    ),
  minimum_stock: z.number().int().min(0).max(4294967295),
  supplier_lead_time_days: z.number().int().min(0).max(365),
  order_pack_size: z.number().int().min(1).max(4294967295),
  is_active: z.boolean(),
})
type Values = z.infer<typeof schema>
export function UnitItemForm({
  unitId,
  unitName,
  item,
  excludedItemIds = [],
  onClose,
  onSaved,
}: {
  unitId: number
  unitName?: string
  item?: UnitItem
  excludedItemIds?: number[]
  onClose: () => void
  onSaved: () => void
}) {
  const [selected, setSelected] = useState(item?.item)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      item_id: item?.item.id ?? 0,
      selling_price: String(item?.selling_price ?? '0'),
      minimum_stock: item?.minimum_stock ?? 0,
      supplier_lead_time_days: item?.supplier_lead_time_days ?? 0,
      order_pack_size: item?.order_pack_size ?? 1,
      is_active: item?.is_active ?? true,
    },
  })
  const itemId = useWatch({ control: form.control, name: 'item_id' })
  const save = useSave<Partial<Values>>(
    `/operational-units/${unitId}/items`,
    item?.id,
    [`/operational-units/${unitId}/inventory`, '/transaction-catalog'],
  )
  const mutation = {
    ...save,
    mutateAsync: (values: Values) => {
      const body: Partial<Values> = {
        ...values,
        minimum_stock: selected?.type === 'SERVICE' ? 0 : values.minimum_stock,
        supplier_lead_time_days:
          selected?.type === 'SERVICE' ? 0 : values.supplier_lead_time_days,
        order_pack_size:
          selected?.type === 'SERVICE' ? 1 : values.order_pack_size,
      }
      if (item) delete body.item_id
      return save.mutateAsync(body)
    },
  }
  return (
    <Modal
      title={`${item ? 'Edit' : 'Tambah'} Item Unit${unitName ? ` · ${unitName}` : ''}`}
      onClose={onClose}
      busy={save.isPending}
    >
      <SaveForm
        form={form}
        mutation={mutation}
        onClose={onClose}
        onSaved={onSaved}
        wasActive={item?.is_active}
        successMessage={
          item
            ? 'Item unit berhasil diperbarui.'
            : `Item berhasil ditambahkan ke ${unitName ?? 'unit'}.`
        }
      >
        <FormField label="Item" error={form.formState.errors.item_id?.message}>
          {item ? (
            <Input
              disabled
              value={`${item.item.item_code} · ${item.item.name}`}
            />
          ) : (
            <ResourcePicker<Item>
              path="/items"
              params={{ is_active: 1 }}
              value={itemId}
              selectedLabel={selected?.name}
              identify={(row) => row.id}
              describe={(row) => `${row.item_code} · ${row.name}`}
              isOptionDisabled={(row) => excludedItemIds.includes(row.id)}
              onChange={(row) => {
                setSelected(row)
                form.setValue('item_id', row.id, { shouldValidate: true })
                if (row.type === 'SERVICE') {
                  form.setValue('minimum_stock', 0)
                  form.setValue('supplier_lead_time_days', 0)
                  form.setValue('order_pack_size', 1)
                }
              }}
            />
          )}
        </FormField>
        <FormField
          label="Harga jual (Rp)"
          error={form.formState.errors.selling_price?.message}
        >
          <Input inputMode="decimal" {...form.register('selling_price')} />
        </FormField>
        <FormField
          label="Safety stock (stok minimum)"
          error={form.formState.errors.minimum_stock?.message}
        >
          <Input
            type="number"
            min={0}
            disabled={selected?.type === 'SERVICE'}
            {...form.register('minimum_stock', { valueAsNumber: true })}
          />
        </FormField>
        <FormField
          label="Lead time pemasok (hari)"
          error={form.formState.errors.supplier_lead_time_days?.message}
        >
          <Input
            type="number"
            min={0}
            max={365}
            disabled={selected?.type === 'SERVICE'}
            {...form.register('supplier_lead_time_days', {
              valueAsNumber: true,
            })}
          />
        </FormField>
        <FormField
          label="Ukuran kemasan pemesanan"
          hint="Rekomendasi dibulatkan ke kelipatan jumlah ini."
          error={form.formState.errors.order_pack_size?.message}
        >
          <Input
            type="number"
            min={1}
            disabled={selected?.type === 'SERVICE'}
            {...form.register('order_pack_size', { valueAsNumber: true })}
          />
        </FormField>
        <label className="check-field">
          <input type="checkbox" {...form.register('is_active')} />
          Aktif
        </label>
      </SaveForm>
    </Modal>
  )
}
