import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Button,
  Card,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Select,
  Skeleton,
  Table,
  type Column,
} from '../../components/ui'
import { useTopbarOverride } from '../../components/common/topbarOverride'
import { api } from '../../lib/api'
import { getPage, getResource, keys } from '../../lib/queries'
import type {
  ForecastHorizon,
  ForecastOptions,
  ForecastResult,
  ForecastRun,
  Resource,
} from '../../types/api'
import { formatDate, formatDateTime, formatNumber } from '../../utils/format'

const horizonLabels: Record<ForecastHorizon, string> = {
  next_week: 'Minggu depan',
  next_month: 'Bulan depan',
  custom: 'Sampai tanggal tertentu',
}

const modelLabels: Record<string, string> = {
  lag_1: 'Minggu terakhir',
  seasonal_52: 'Musiman 52 minggu',
}

export function ForecastsPage() {
  const queryClient = useQueryClient()
  const [unitId, setUnitId] = useState('')
  const [horizon, setHorizon] = useState<ForecastHorizon>('next_week')
  const [targetDate, setTargetDate] = useState('')
  const options = useQuery({
    queryKey: keys.detail('/forecasts/options'),
    queryFn: ({ signal }) =>
      getResource<ForecastOptions>('/forecasts/options', signal),
  })
  const history = useQuery({
    queryKey: keys.list('/forecasts', { per_page: 10 }),
    queryFn: ({ signal }) =>
      getPage<ForecastRun>('/forecasts', { per_page: 10 }, signal),
  })
  const generate = useMutation({
    mutationFn: async () => {
      const response = await api.post<Resource<ForecastRun>>('/forecasts', {
        operational_unit_id: Number(effectiveUnitId),
        horizon,
        target_date: horizon === 'custom' ? targetDate : null,
      })

      return response.data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['/forecasts'] })
    },
  })

  useTopbarOverride({
    label: 'AI Insight',
    description:
      'Prediksi permintaan mingguan dan rekomendasi restock untuk keputusan Superadmin.',
  })

  const effectiveUnitId = unitId || String(options.data?.units[0]?.id ?? '')
  const selectedUnit = options.data?.units.find(
    (unit) => unit.id === Number(effectiveUnitId),
  )
  const activeRun = generate.data ?? history.data?.data[0]
  const totals = useMemo(
    () => ({
      predicted:
        activeRun?.results.reduce(
          (sum, result) => sum + result.predicted_units,
          0,
        ) ?? 0,
      restock:
        activeRun?.results.reduce(
          (sum, result) => sum + result.recommended_restock,
          0,
        ) ?? 0,
      products:
        activeRun?.results.filter((result) => result.recommended_restock > 0)
          .length ?? 0,
    }),
    [activeRun],
  )
  const columns: Column<ForecastResult>[] = [
    {
      title: 'Produk',
      render: (row) => (
        <div>
          <strong>{row.item.name}</strong>
          <small className="block text-slate-500">{row.item.item_code}</small>
        </div>
      ),
    },
    { title: 'Model', render: (row) => modelLabels[row.model] ?? row.model },
    {
      title: 'Prediksi',
      render: (row) =>
        `${formatNumber(row.predicted_units)} ${row.item.uom ?? 'unit'}`,
    },
    {
      title: 'Stok tersedia',
      render: (row) => formatNumber(row.available_quantity),
    },
    {
      title: 'Stok minimum',
      render: (row) => formatNumber(row.minimum_stock),
    },
    {
      title: 'Rekomendasi restock',
      render: (row) => (
        <strong
          className={
            row.recommended_restock > 0
              ? 'text-orange-700'
              : 'text-emerald-700'
          }
        >
          {formatNumber(row.recommended_restock)}
        </strong>
      ),
    },
    { title: 'WAPE', render: (row) => `${row.wape.toFixed(1)}%` },
  ]

  if (options.isPending) return <Skeleton rows={7} />
  if (options.error)
    return (
      <ErrorState error={options.error} retry={() => void options.refetch()} />
    )
  if (!options.data?.units.length)
    return (
      <EmptyState message="Data historis belum diimpor">
        <p className="mt-2 text-sm text-slate-500">
          Jalankan importer histori penjualan sebelum membuat prediksi.
        </p>
      </EmptyState>
    )

  return (
    <div className="space-y-6">
      <Card className="p-5">
        <form
          className="grid gap-4 lg:grid-cols-[1.3fr_1fr_1fr_auto] lg:items-end"
          onSubmit={(event) => {
            event.preventDefault()
            generate.mutate()
          }}
        >
          <FormField
            label="Unit operasional"
            hint={
              selectedUnit
                ? `${selectedUnit.history_weeks} minggu · ${selectedUnit.product_count} produk · data sampai ${formatDate(selectedUnit.history_end)}`
                : undefined
            }
          >
            <Select
              value={effectiveUnitId}
              onChange={(event) => setUnitId(event.target.value)}
            >
              {options.data.units.map((unit) => (
                <option value={unit.id} key={unit.id}>
                  {unit.name}
                </option>
              ))}
            </Select>
          </FormField>
          <FormField label="Horizon prediksi">
            <Select
              value={horizon}
              onChange={(event) =>
                setHorizon(event.target.value as ForecastHorizon)
              }
            >
              {Object.entries(horizonLabels).map(([value, text]) => (
                <option value={value} key={value}>
                  {text}
                </option>
              ))}
            </Select>
          </FormField>
          {horizon === 'custom' ? (
            <FormField label="Tanggal target">
              <Input
                required
                type="date"
                value={targetDate}
                onChange={(event) => setTargetDate(event.target.value)}
              />
            </FormField>
          ) : (
            <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
              Data mingguan dialokasikan proporsional saat periode memotong
              minggu.
            </div>
          )}
          <Button
            type="submit"
            busy={generate.isPending}
            disabled={!effectiveUnitId || (horizon === 'custom' && !targetDate)}
          >
            Buat Prediksi
          </Button>
        </form>
        {generate.error && (
          <div className="mt-4">
            <ErrorState error={generate.error} />
          </div>
        )}
        {selectedUnit?.data_freshness.requires_warning && (
          <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Data penjualan perlu diperbarui.</strong>{' '}
            Histori terakhir {formatDate(selectedUnit.history_end)} atau{' '}
            {formatNumber(selectedUnit.data_freshness.age_days)} hari sebelum
            hari ini. Hasil prediksi tetap dapat dibuat, tetapi harus ditinjau
            lebih ketat sebelum pengadaan.
          </div>
        )}
      </Card>

      {history.isPending && !activeRun ? (
        <Skeleton rows={6} />
      ) : history.error && !activeRun ? (
        <ErrorState
          error={history.error}
          retry={() => void history.refetch()}
        />
      ) : activeRun ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard
              label="Periode"
              value={`${formatDate(activeRun.forecast_start)}–${formatDate(activeRun.forecast_end)}`}
            />
            <MetricCard
              label="Prediksi permintaan"
              value={`${formatNumber(totals.predicted)} unit`}
            />
            <MetricCard
              label="Rekomendasi restock"
              value={`${formatNumber(totals.restock)} unit`}
            />
            <MetricCard
              label="Produk perlu restock"
              value={formatNumber(totals.products)}
            />
          </div>

          {activeRun.data_freshness.requires_warning && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <strong>Peringatan kualitas data:</strong> terdapat{' '}
              {formatNumber(activeRun.data_freshness.gap_weeks)} minggu tanpa
              observasi antara histori terakhir dan periode target. Perbarui
              data penjualan dan buat ulang prediksi sebelum menyetujui
              rekomendasi restock.
            </div>
          )}

          <Card className="p-5">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2>Ringkasan prediksi {activeRun.operational_unit.name}</h2>
                <p className="mt-1 text-sm text-slate-500">
                  Dibuat {formatDateTime(activeRun.created_at)} · histori sampai{' '}
                  {formatDate(activeRun.history_through)} · WAPE agregat{' '}
                  {activeRun.metrics.wape.toFixed(1)}%
                </p>
              </div>
              <span className="badge badge-neutral">
                Penjelasan:{' '}
                {activeRun.explanation_provider === 'none'
                  ? 'sistem'
                  : activeRun.explanation_provider}
              </span>
            </div>
            {activeRun.explanation && (
              <div className="grid gap-5 lg:grid-cols-3">
                <div>
                  <h3 className="mb-2 text-sm font-semibold">Kesimpulan</h3>
                  <p className="text-sm text-slate-700">
                    {activeRun.explanation.summary}
                  </p>
                </div>
                <InsightList
                  title="Risiko"
                  items={activeRun.explanation.risks}
                />
                <InsightList
                  title="Tindakan"
                  items={activeRun.explanation.actions}
                />
              </div>
            )}
          </Card>

          <Card className="overflow-hidden">
            <Table
              rows={activeRun.results}
              columns={columns}
              rowKey={(row) => row.id}
              empty="Tidak ada hasil prediksi"
            />
          </Card>
        </>
      ) : (
        <EmptyState message="Belum ada prediksi">
          <p className="mt-2 text-sm text-slate-500">
            Pilih unit dan horizon, lalu buat prediksi pertama.
          </p>
        </EmptyState>
      )}
    </div>
  )
}

function MetricCard({ label, value }: { label: string; value: string }) {
  return (
    <Card className="p-5">
      <p className="text-sm text-slate-500">{label}</p>
      <strong className="mt-2 block text-lg text-slate-900">{value}</strong>
    </Card>
  )
}

function InsightList({ title, items }: { title: string; items: string[] }) {
  return (
    <div>
      <h3 className="mb-2 text-sm font-semibold">{title}</h3>
      <ul className="list-disc space-y-1 pl-5 text-sm text-slate-700">
        {items.map((item) => (
          <li key={item}>{item}</li>
        ))}
      </ul>
    </div>
  )
}
