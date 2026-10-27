import { Badge, ListContent } from '../../components/ui'
import { FilterBar } from '../../components/ui/Filters'
import { dateFilters, enumOptions } from '../../components/ui/filterDefinitions'
import { useFilters } from '../../hooks/useFilters'
import { useList } from '../../lib/queries'
import type { StockMovement } from '../../types/api'
import { formatDateTime, formatNumber, label } from '../../utils/format'
export function MovementsPage() {
  const filters = useFilters()
  const query = useList<StockMovement>('/inventory/movements', filters.params)
  return (
    <>
      <FilterBar
        filters={filters}
        search={false}
        definitions={[
          { name: 'inventory_location_id', label: 'ID lokasi', type: 'number' },
          { name: 'item_id', label: 'ID item', type: 'number' },
          {
            name: 'movement_type',
            label: 'Jenis pergerakan',
            options: enumOptions(
              'STOCK_IN',
              'STOCK_OUT',
              'TRANSFER_IN',
              'TRANSFER_OUT',
              'SALE',
            ),
          },
          {
            name: 'direction',
            label: 'Arah',
            options: enumOptions('IN', 'OUT'),
          },
          { name: 'performed_by', label: 'ID pelaku', type: 'number' },
          ...dateFilters,
        ]}
      />
      <ListContent
        query={query}
        rowKey={(row) => row.id}
        empty="Belum ada pergerakan stok"
        onPage={(p) => filters.set('page', String(p))}
        onSize={(size) => filters.set('per_page', size)}
        columns={[
          { title: 'Waktu', render: (row) => formatDateTime(row.created_at) },
          {
            title: 'Lokasi',
            render: (row) => (
              <>
                {row.location.name}
                <small className="block text-slate-400">
                  #{row.location.id}
                </small>
              </>
            ),
          },
          { title: 'Item', render: (row) => row.item.name },
          { title: 'Arah', render: (row) => <Badge value={row.direction} /> },
          { title: 'Jenis', render: (row) => label(row.movement_type) },
          { title: 'Jumlah', render: (row) => formatNumber(row.quantity) },
          { title: 'Pelaku', render: (row) => row.performer.name },
          { title: 'Catatan', render: (row) => row.note ?? '—' },
          {
            title: 'Referensi',
            render: (row) =>
              row.reference_type
                ? `${row.reference_type} #${row.reference_id}`
                : '—',
          },
        ]}
      />
    </>
  )
}
