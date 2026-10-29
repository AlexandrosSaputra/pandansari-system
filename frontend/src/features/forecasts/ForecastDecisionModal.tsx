import { useMemo } from 'react'
import { useMutation } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import {
  Badge,
  Button,
  ErrorState,
  FormField,
  Input,
  Modal,
  Select,
  Textarea,
} from '../../components/ui'
import { api } from '../../lib/api'
import { apiError, setFormErrors } from '../../lib/errors'
import { notifications } from '../../lib/notifications'
import type { ForecastResult, ForecastRun, Resource } from '../../types/api'
import { formatDateTime, formatNumber } from '../../utils/format'

function decisionSchema(recommendedRestock: number) {
  return z
    .object({
      status: z.enum(['APPROVED', 'REJECTED']),
      final_restock_quantity: z
        .number({ error: 'Jumlah akhir wajib diisi.' })
        .int('Jumlah harus berupa bilangan bulat.')
        .min(0, 'Jumlah tidak boleh negatif.'),
      reason: z.string().trim().max(2000, 'Alasan maksimal 2.000 karakter.'),
    })
    .superRefine((values, context) => {
      const reasonRequired =
        values.status === 'REJECTED' ||
        values.final_restock_quantity !== recommendedRestock

      if (reasonRequired && values.reason.length === 0) {
        context.addIssue({
          code: 'custom',
          path: ['reason'],
          message:
            values.status === 'REJECTED'
              ? 'Alasan penolakan wajib diisi.'
              : 'Alasan wajib diisi ketika jumlah restock dikoreksi.',
        })
      }
    })
}

export function ForecastDecisionModal({
  result,
  onClose,
  onSaved,
}: {
  result: ForecastResult
  onClose: () => void
  onSaved: (run: ForecastRun) => void
}) {
  const schema = useMemo(
    () => decisionSchema(result.recommended_restock),
    [result.recommended_restock],
  )
  type DecisionValues = z.infer<typeof schema>
  const form = useForm<DecisionValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      status:
        result.decision_status === 'REJECTED' ? 'REJECTED' : 'APPROVED',
      final_restock_quantity:
        result.final_restock_quantity ?? result.recommended_restock,
      reason: '',
    },
  })
  const status = useWatch({ control: form.control, name: 'status' })
  const save = useMutation({
    mutationFn: async (values: DecisionValues) => {
      const response = await api.post<Resource<ForecastRun>>(
        `/forecasts/results/${result.id}/decisions`,
        {
          status: values.status,
          final_restock_quantity:
            values.status === 'APPROVED'
              ? values.final_restock_quantity
              : null,
          reason: values.reason.trim() || null,
        },
      )

      return response.data.data
    },
    onSuccess: (run) => {
      notifications.success('Keputusan restock berhasil disimpan.')
      onSaved(run)
    },
    onError: (error) => {
      notifications.error(apiError(error).message)
      setFormErrors(error, form.setError)
    },
  })

  return (
    <Modal
      title={`Keputusan restock ${result.item.name}`}
      onClose={onClose}
      busy={save.isPending}
      wide
    >
      <div className="grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-3">
        <div>
          <small className="text-slate-500">Rekomendasi sistem</small>
          <strong className="block">
            {formatNumber(result.recommended_restock)} {result.item.uom ?? 'unit'}
          </strong>
        </div>
        <div>
          <small className="text-slate-500">Stok tersedia</small>
          <strong className="block">
            {formatNumber(result.available_quantity)}
          </strong>
        </div>
        <div>
          <small className="text-slate-500">Status saat ini</small>
          <span className="mt-1 block">
            <Badge value={result.decision_status} />
          </span>
        </div>
      </div>

      <form
        className="mt-5 space-y-4"
        onSubmit={form.handleSubmit((values) => save.mutate(values))}
      >
        <fieldset disabled={save.isPending} className="space-y-4">
          <FormField
            label="Keputusan"
            error={form.formState.errors.status?.message}
          >
            <Select {...form.register('status')}>
              <option value="APPROVED">Setujui rekomendasi</option>
              <option value="REJECTED">Tolak rekomendasi</option>
            </Select>
          </FormField>
          {status === 'APPROVED' && (
            <FormField
              label="Jumlah restock final"
              hint="Jumlah boleh dikoreksi tanpa mengubah stok secara otomatis."
              error={form.formState.errors.final_restock_quantity?.message}
            >
              <Input
                type="number"
                min={0}
                max={4294967295}
                step={1}
                {...form.register('final_restock_quantity', {
                  valueAsNumber: true,
                })}
              />
            </FormField>
          )}
          <FormField
            label="Alasan"
            hint={
              status === 'REJECTED'
                ? 'Wajib diisi untuk penolakan.'
                : 'Wajib diisi jika jumlah final berbeda dari rekomendasi.'
            }
            error={form.formState.errors.reason?.message}
          >
            <Textarea {...form.register('reason')} rows={3} />
          </FormField>
        </fieldset>
        {save.error && <ErrorState error={save.error} />}
        <div className="form-actions">
          <Button
            variant="secondary"
            disabled={save.isPending}
            onClick={onClose}
          >
            Batal
          </Button>
          <Button type="submit" busy={save.isPending}>
            Simpan keputusan
          </Button>
        </div>
      </form>

      <div className="mt-6 border-t border-slate-200 pt-5">
        <h3 className="text-sm font-semibold">Riwayat keputusan</h3>
        {result.decision_history.length === 0 ? (
          <p className="mt-2 text-sm text-slate-500">
            Belum ada keputusan untuk rekomendasi ini.
          </p>
        ) : (
          <ol className="mt-3 space-y-3">
            {result.decision_history.map((decision) => (
              <li
                className="rounded-lg border border-slate-200 p-3 text-sm"
                key={decision.id}
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <Badge value={decision.status} />
                  <span className="text-slate-500">
                    {formatDateTime(decision.decided_at)}
                  </span>
                </div>
                <p className="mt-2 text-slate-700">
                  {decision.status === 'APPROVED'
                    ? `Jumlah final ${formatNumber(decision.final_restock_quantity)} unit dari rekomendasi ${formatNumber(decision.recommended_restock_snapshot)} unit.`
                    : `Rekomendasi ${formatNumber(decision.recommended_restock_snapshot)} unit ditolak.`}
                </p>
                {decision.reason && (
                  <p className="mt-1 text-slate-600">Alasan: {decision.reason}</p>
                )}
                <small className="mt-2 block text-slate-500">
                  Oleh {decision.decided_by.name}
                </small>
              </li>
            ))}
          </ol>
        )}
      </div>
    </Modal>
  )
}
