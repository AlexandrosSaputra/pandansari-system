import type { FilterDefinition } from './Filters'
import { label } from '../../utils/format'
export const enumOptions = (...values: string[]) =>
  values.map((value) => ({ value, label: label(value) }))
export const statusFilter: FilterDefinition = {
  name: 'is_active',
  label: 'Status',
  options: [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
  ],
}
export const dateFilters: FilterDefinition[] = [
  { name: 'date_from', label: 'Dari tanggal', type: 'date' },
  { name: 'date_to', label: 'Sampai tanggal', type: 'date' },
]
