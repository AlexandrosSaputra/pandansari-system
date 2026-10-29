import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Badge,
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
import { ForecastDecisionModal } from './ForecastDecisionModal'

const horizonLabels: Record<ForecastHorizon, string> = {
  next_week: 'Minggu depan',
  next_month: 'Bulan depan',
  custom: 'Sampai tanggal tertentu',
}

const modelLabels: Record<string, string> = {
  lag_1: 'Minggu terakhir',
  moving_average_4: 'Rata-rata 4 minggu',
  seasonal_52: 'Musiman 52 minggu',
  seasonal_average_3: 'Rata-rata musiman 3 tahun',
}

export function ForecastsPage() {
  const queryClient = useQueryClient()
  const [unitId, setUnitId] = useState('')
  const [horizon, setHorizon] = useState<ForecastHorizon>('next_week')
  const [targetDate, setTargetDate] = useState('')
  const [decisionRun, setDecisionRun] = useState<ForecastRun | null>(null)
  const [selectedResult, setSelectedResult] =
    useState<ForecastResult | null>(null)
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
      setDecisionRun(null)
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
  const activeRun = decisionRun ?? generate.data ?? history.data?.data[0]
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
      title: 'Safety stock',
      render: (row) => (
        <div>
          <span>{formatNumber(row.safety_stock)}</span>
          <small className="block text-slate-500">
            {row.safety_stock_mode === 'CALCULATED'
              ? `Terhitung · target ${row.target_service_level}%`
              : 'Manual'}
          </small>
        </div>
      ),
    },
    {
      title: 'Kebijakan pesan',
      render: (row) => (
        <div>
          <span>{formatNumber(row.order_pack_size)} unit/kemasan</span>
          <small className="block text-slate-500">
            Lead time {formatNumber(row.supplier_lead_time_days)} hari
          </small>
        </div>
      ),
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
          {row.recommended_restock !== row.unrounded_restock && (
            <small className="block font-normal text-slate-500">
              dari {formatNumber(row.unrounded_restock)} unit
            </small>
          )}
        </strong>
      ),
    },
    {
      title: 'Pesan paling lambat',
      render: (row) =>
        row.recommended_restock === 0 || !row.order_by_date ? (
          '—'
        ) : (
          <div>
            <span>{formatDate(row.order_by_date)}</span>
            {row.order_status === 'overdue' && (
              <small className="block font-medium text-red-700">
                Terlambat
              </small>
            )}
            {row.order_status === 'due_today' && (
              <small className="block font-medium text-amber-700">
                Pesan hari ini
              </small>
            )}
          </div>
        ),
    },
    {
      title: 'Keputusan owner',
      render: (row) => (
        <div className="flex min-w-28 flex-col items-start gap-2">
          <Badge value={row.decision_status} />
          {row.decision_status === 'APPROVED' && (
            <small className="text-slate-600">
              Final {formatNumber(row.final_restock_quantity)} unit
            </small>
          )}
          <Button
            variant="secondary"
            className="px-2.5 py-1.5 text-xs"
            onClick={() => setSelectedResult(row)}
          >
            {row.decision_status === 'PENDING' ? 'Putuskan' : 'Tinjau'}
          </Button>
        </div>
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
        {options.data.explanation_provider === 'ollama' && (
          <div
            role="status"
            className={`mb-4 rounded-lg border p-3 text-sm ${
              options.data.explanation_runtime.status === 'ready'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                : 'border-amber-200 bg-amber-50 text-amber-900'
            }`}
          >
            <strong>
              Ollama {options.data.explanation_runtime.status === 'ready'
                ? 'terhubung, model tersedia'
                : options.data.explanation_runtime.status === 'model_missing'
                  ? 'model belum tersedia'
                  : 'tidak terhubung'}
            </strong>{' '}
            {options.data.explanation_runtime.model &&
              `(${options.data.explanation_runtime.model}).`}{' '}
            {options.data.explanation_runtime.status !== 'ready' &&
              'Jika belum tersedia saat prediksi dibuat, penjelasan sistem akan digunakan.'}{' '}
            <Button
              variant="ghost"
              className="px-2 py-1 text-xs"
              busy={options.isFetching}
              onClick={() => void options.refetch()}
            >
              Periksa ulang
            </Button>
          </div>
        )}
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
                {activeRun.explanation_status === 'fallback'
                  ? `sistem (fallback ${activeRun.explanation_provider})`
                  : activeRun.explanation_provider === 'none'
                    ? 'sistem'
                    : activeRun.explanation_provider}
              </span>
            </div>
            {Object.keys(activeRun.metrics.candidate_metrics ?? {}).length >
              0 && (
              <div className="mb-5 flex flex-wrap gap-2">
                {Object.keys(modelLabels).map((model) => {
                  const metrics = activeRun.metrics.candidate_metrics?.[model]

                  return metrics ? (
                    <span
                      className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600"
                      key={model}
                    >
                      {modelLabels[model] ?? model}: WAPE{' '}
                      {metrics.wape.toFixed(1)}%
                    </span>
                  ) : null
                })}
              </div>
            )}
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
      {selectedResult && (
        <ForecastDecisionModal
          result={selectedResult}
          onClose={() => setSelectedResult(null)}
          onSaved={(run) => {
            setDecisionRun(run)
            setSelectedResult(null)
            void queryClient.invalidateQueries({ queryKey: ['/forecasts'] })
          }}
        />
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
