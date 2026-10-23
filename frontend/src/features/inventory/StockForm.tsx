import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useMutation } from '@tanstack/react-query'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormField, Input, Modal, Textarea } from '../../components/ui'
import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { SaveForm } from '../../components/ui/SaveForm'
import { api } from '../../lib/api'
import { useInvalidate } from '../../lib/queries'
import type { InventoryBalance } from '../../types/api'
import { formatNumber } from '../../utils/format'
const schema = z.object({
  item_id: z.number().int().positive('Pilih item.'),
  quantity: z.number().int().positive('Jumlah harus lebih dari nol.'),
  note: z.string().max(2000),
})
export function StockForm({
  direction,
  onClose,
  onSaved,
}: {
  direction: 'in' | 'out'
  onClose: () => void
  onSaved: () => void
}) {
  const [selected, setSelected] = useState<InventoryBalance>()
  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: { item_id: 0, quantity: 1, note: '' },
  })
  const itemId = useWatch({ control: form.control, name: 'item_id' })
  const invalidate = useInvalidate()
  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) =>
      api.post(`/inventory/warehouse/stock-${direction}`, values),
    onSuccess: () =>
      invalidate(['/inventory/warehouse', '/inventory/movements']),
  })
  const mutation = {
    ...save,
    mutateAsync: async (values: z.infer<typeof schema>) => {
      if (
        direction === 'out' &&
        (!selected || values.quantity > selected.available_quantity)
      ) {
        form.setError('quantity', { message: 'Jumlah melebihi stok tersedia.' })
        throw new Error('Jumlah melebihi stok tersedia.')
      }
      return save.mutateAsync(values)
    },
  }
  return (
    <Modal
      title={direction === 'in' ? 'Stok Masuk' : 'Stok Keluar'}
      onClose={onClose}
      busy={save.isPending}
    >
      <SaveForm
        form={form}
        mutation={mutation}
        onClose={onClose}
        onSaved={onSaved}
        successMessage="Perubahan stok berhasil dicatat."
      >
        <FormField
          label="Produk"
          error={form.formState.errors.item_id?.message}
        >
          <ResourcePicker<InventoryBalance>
            path="/inventory/warehouse"
            params={{ is_active: 1 }}
            value={itemId}
            selectedLabel={selected?.item.name}
            identify={(row) => row.item.id}
            describe={(row) =>
              `${row.item.item_code} · ${row.item.name} · tersedia ${row.available_quantity}`
            }
            onChange={(row) => {
              setSelected(row)
              form.setValue('item_id', row.item.id, { shouldValidate: true })
            }}
          />
        </FormField>
        {selected && (
          <dl className="detail-grid rounded-lg bg-slate-50 p-3">
            <div>
              <dt>Stok fisik</dt>
              <dd>{formatNumber(selected.quantity_on_hand)}</dd>
            </div>
            <div>
              <dt>Direservasi</dt>
              <dd>{formatNumber(selected.quantity_reserved)}</dd>
            </div>
            <div>
              <dt>Tersedia</dt>
              <dd>{formatNumber(selected.available_quantity)}</dd>
            </div>
          </dl>
        )}
        <FormField
          label="Jumlah"
          error={form.formState.errors.quantity?.message}
        >
          <Input
            type="number"
            min={1}
            max={direction === 'out' ? selected?.available_quantity : undefined}
            {...form.register('quantity', { valueAsNumber: true })}
          />
        </FormField>
        <FormField
          label="Catatan (opsional)"
          error={form.formState.errors.note?.message}
        >
          <Textarea {...form.register('note')} />
        </FormField>
      </SaveForm>
    </Modal>
  )
}
