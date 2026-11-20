import { useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormField, Input, Modal, Select } from '../../components/ui'
import { SaveForm } from '../../components/ui/SaveForm'
import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { useSave } from '../../lib/queries'
import type { Item, UnitItem } from '../../types/api'
import { formatRupiahInput, parseRupiahInput } from '../../utils/format'
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
  safety_stock_mode: z.enum(['MANUAL', 'CALCULATED']),
  target_service_level: z
    .number()
    .refine(
      (value) => [90, 95, 97.5, 99].includes(value),
      'Pilih target service level yang tersedia.',
    ),
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
      safety_stock_mode: item?.safety_stock_mode ?? 'MANUAL',
      target_service_level: item?.target_service_level ?? 95,
      supplier_lead_time_days: item?.supplier_lead_time_days ?? 0,
      order_pack_size: item?.order_pack_size ?? 1,
      is_active: item?.is_active ?? true,
    },
  })
  const itemId = useWatch({ control: form.control, name: 'item_id' })
  const safetyStockMode = useWatch({
    control: form.control,
    name: 'safety_stock_mode',
  })
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
        safety_stock_mode:
          selected?.type === 'SERVICE' ? 'MANUAL' : values.safety_stock_mode,
        target_service_level:
          selected?.type === 'SERVICE' ? 95 : values.target_service_level,
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
                  form.setValue('safety_stock_mode', 'MANUAL')
                  form.setValue('target_service_level', 95)
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
          <Controller
            control={form.control}
            name="selling_price"
            render={({ field }) => (
              <span className="currency-input">
                <span className="currency-input-prefix" aria-hidden="true">
                  Rp
                </span>
                <Input
                  {...field}
                  inputMode="decimal"
                  value={formatRupiahInput(field.value)}
                  onChange={(event) =>
                    field.onChange(parseRupiahInput(event.target.value))
                  }
                  onBlur={() => {
                    if (field.value.endsWith('.')) {
                      field.onChange(field.value.slice(0, -1))
                    }
                    field.onBlur()
                  }}
                />
              </span>
            )}
          />
        </FormField>
        <FormField
          label="Metode safety stock"
          error={form.formState.errors.safety_stock_mode?.message}
        >
          <Select
            disabled={selected?.type === 'SERVICE'}
            {...form.register('safety_stock_mode')}
          >
            <option value="MANUAL">Manual dari stok minimum</option>
            <option value="CALCULATED">Dihitung dari error forecast</option>
          </Select>
        </FormField>
        {safetyStockMode === 'CALCULATED' && (
          <FormField
            label="Target service level"
            hint="Semakin tinggi target, semakin besar cadangan stok."
            error={form.formState.errors.target_service_level?.message}
          >
            <Select
              {...form.register('target_service_level', {
                valueAsNumber: true,
              })}
            >
              <option value={90}>90%</option>
              <option value={95}>95%</option>
              <option value={97.5}>97,5%</option>
              <option value={99}>99%</option>
            </Select>
          </FormField>
        )}
        <FormField
          label="Safety stock manual (stok minimum)"
          hint={
            safetyStockMode === 'CALCULATED'
              ? 'Tidak dipakai dalam rumus forecast, tetapi tetap menjadi ambang indikator stok.'
              : undefined
          }
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
