import { useState } from 'react'
import {
  useFieldArray,
  useForm,
  useWatch,
  type UseFormReturn,
} from 'react-hook-form'
import { useMutation, useQuery } from '@tanstack/react-query'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ErrorState,
  FormField,
  Input,
  Modal,
} from '../../components/ui'
import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { SaveForm } from '../../components/ui/SaveForm'
import { api } from '../../lib/api'
import { getPage, useInvalidate } from '../../lib/queries'
import type { InventoryBalance, OperationalUnit } from '../../types/api'
import { UnitSelector } from '../inventory/UnitSelector'
import { formatNumber } from '../../utils/format'
const schema = z
  .object({
    destination_operational_unit_id: z
      .number()
      .int()
      .positive('Pilih unit tujuan.'),
    items: z
      .array(
        z.object({
          item_id: z.number().int().positive('Pilih produk.'),
          quantity: z.number().int().positive('Jumlah harus lebih dari nol.'),
        }),
      )
      .min(1, 'Tambahkan minimal satu produk.'),
  })
  .superRefine((values, ctx) => {
    const ids = new Set<number>()
    values.items.forEach((row, index) => {
      if (ids.has(row.item_id))
        ctx.addIssue({
          code: 'custom',
          path: ['items', index, 'item_id'],
          message: 'Produk sudah dipilih.',
        })
      ids.add(row.item_id)
    })
  })
type Values = z.infer<typeof schema>
async function warehouseBalance(item: InventoryBalance, signal?: AbortSignal) {
  let page = 1
  while (true) {
    const result = await getPage<InventoryBalance>(
      '/inventory/warehouse',
      { search: item.item.item_code, is_active: 1, page, per_page: 15 },
      signal,
    )
    const match = result.data.find((row) => row.item.id === item.item.id)
    if (match) return match
    if (page >= result.meta.last_page)
      throw new Error('Produk tidak ditemukan di gudang.')
    page += 1
  }
}
function TransferLine({
  index,
  unitId,
  form,
  remove,
  selected,
  onSelect,
  excludedIds,
}: {
  index: number
  unitId: number
  form: UseFormReturn<Values>
  remove: () => void
  selected?: InventoryBalance
  onSelect: (row: InventoryBalance) => void
  excludedIds: number[]
}) {
  const itemId = useWatch({
    control: form.control,
    name: `items.${index}.item_id`,
  })
  const balance = useQuery({
    queryKey: ['/inventory/warehouse', 'balance', selected?.item.id],
    queryFn: ({ signal }) => warehouseBalance(selected!, signal),
    enabled: !!selected,
  })
  return (
    <div className="rounded-lg border border-slate-200 p-4">
      <div className="grid gap-3 sm:grid-cols-[1fr_110px_auto]">
        <FormField
          label={`Produk ${index + 1}`}
          error={form.formState.errors.items?.[index]?.item_id?.message}
        >
          <ResourcePicker<InventoryBalance>
            path={`/operational-units/${unitId}/inventory`}
            enabled={!!unitId}
            value={itemId}
            selectedLabel={selected?.item.name}
            identify={(row) => row.item.id}
            isOptionDisabled={(row) => excludedIds.includes(row.item.id)}
            describe={(row) => `${row.item.item_code} · ${row.item.name}`}
            onChange={(row) => {
              form.setValue(`items.${index}.item_id`, row.item.id, {
                shouldValidate: true,
              })
              onSelect(row)
            }}
          />
        </FormField>
        <FormField
          label="Jumlah"
          error={form.formState.errors.items?.[index]?.quantity?.message}
        >
          <Input
            type="number"
            min={1}
            max={balance.data?.available_quantity}
            {...form.register(`items.${index}.quantity`, {
              valueAsNumber: true,
            })}
          />
        </FormField>
        <Button
          className="self-end"
          variant="ghost"
          aria-label={`Hapus produk ${index + 1}`}
          onClick={remove}
        >
          Hapus
        </Button>
      </div>
      {selected && (
        <p className="mt-2 text-xs text-slate-500">
          Tersedia di gudang:{' '}
          {balance.isPending
            ? 'Memuat…'
            : formatNumber(balance.data?.available_quantity)}
        </p>
      )}
      {balance.error && (
        <ErrorState
          error={balance.error}
          retry={() => {
            void balance.refetch()
          }}
        />
      )}
    </div>
  )
}
export function TransferForm({
  onClose,
  onSaved,
}: {
  onClose: () => void
  onSaved: () => void
}) {
  const [unit, setUnit] = useState<OperationalUnit>()
  const [selected, setSelected] = useState<Record<string, InventoryBalance>>({})
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      destination_operational_unit_id: 0,
      items: [{ item_id: 0, quantity: 1 }],
    },
  })
  const rows = useFieldArray({ control: form.control, name: 'items' })
  const currentItems = useWatch({ control: form.control, name: 'items' })
  const invalidate = useInvalidate()
  const mutation = useMutation({
    mutationFn: async (values: Values) => {
      const balances = await Promise.all(
        rows.fields.map((row) =>
          selected[row.id]
            ? warehouseBalance(selected[row.id])
            : Promise.reject(new Error('Pilih produk.')),
        ),
      )
      let invalid = false
      balances.forEach((balance, i) => {
        if (values.items[i].quantity > balance.available_quantity) {
          invalid = true
          form.setError(`items.${i}.quantity`, {
            message: 'Jumlah melebihi stok tersedia.',
          })
        }
      })
      if (invalid) throw new Error('Stok tidak mencukupi.')
      return api.post('/stock-transfers', values)
    },
    onSuccess: () =>
      invalidate([
        '/stock-transfers',
        '/inventory/warehouse',
        '/inventory/movements',
      ]),
  })
  return (
    <Modal
      title="Buat Transfer Stok"
      onClose={onClose}
      busy={mutation.isPending}
      wide
    >
      <SaveForm
        form={form}
        mutation={mutation}
        onClose={onClose}
        onSaved={onSaved}
      >
        <p className="text-sm text-slate-500">
          Sumber: Gudang Utama. Stok akan direservasi hingga transfer diterima
          atau ditolak.
        </p>
        <UnitSelector
          value={unit?.id}
          name={unit?.name}
          onChange={(value) => {
            setUnit(value)
            setSelected({})
            form.setValue('destination_operational_unit_id', value.id, {
              shouldValidate: true,
            })
            rows.replace([{ item_id: 0, quantity: 1 }])
          }}
        />
        {form.formState.errors.destination_operational_unit_id && (
          <p role="alert" className="text-sm text-red-700">
            {form.formState.errors.destination_operational_unit_id.message}
          </p>
        )}
        <div className="space-y-3">
          {rows.fields.map((row, i) => (
            <TransferLine
              key={row.id}
              index={i}
              unitId={unit?.id ?? 0}
              form={form}
              selected={selected[row.id]}
              excludedIds={currentItems
                .filter((_, index) => index !== i)
                .map((item) => item.item_id)}
              onSelect={(value) =>
                setSelected((previous) => ({ ...previous, [row.id]: value }))
              }
              remove={() => rows.remove(i)}
            />
          ))}
        </div>
        {form.formState.errors.items?.message && (
          <p className="text-sm text-red-700">
            {form.formState.errors.items.message}
          </p>
        )}
        <Button
          variant="secondary"
          disabled={!unit}
          onClick={() => rows.append({ item_id: 0, quantity: 1 })}
        >
          + Tambah Produk
        </Button>
      </SaveForm>
    </Modal>
  )
}
