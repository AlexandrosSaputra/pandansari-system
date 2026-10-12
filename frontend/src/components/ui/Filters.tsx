import { useState } from 'react'
import { Button, FormField, Input, Select } from './index'
import type { useFilters } from '../../hooks/useFilters'
import { useAuth } from '../../features/auth/useAuth'
import { EntityFilter } from './EntityFilter'

const lookups: Record<string, { path: string; permission: string }> = {
  operational_unit_id: {
    path: '/operational-units',
    permission: 'operational-unit.view',
  },
  destination_operational_unit_id: {
    path: '/operational-units',
    permission: 'operational-unit.view',
  },
  actor_id: { path: '/users', permission: 'user.view' },
  created_by: { path: '/users', permission: 'user.view' },
  paid_by: { path: '/users', permission: 'user.view' },
  performed_by: { path: '/users', permission: 'user.view' },
  item_id: { path: '/items', permission: 'item.view' },
}
export interface FilterDefinition {
  name: string
  label: string
  type?: 'text' | 'number' | 'date'
  options?: { value: string; label: string }[]
}
function SearchInput({
  value,
  onChange,
  title,
  type,
}: {
  value: string
  onChange: (value: string) => void
  title: string
  type?: string
}) {
  const [draft, setDraft] = useState(value)
  return (
    <Input
      type={type ?? 'search'}
      min={type === 'number' ? 1 : undefined}
      aria-label={title}
      placeholder={title}
      value={draft}
      onChange={(e) => setDraft(e.target.value)}
      onBlur={() => {
        if (draft !== value) onChange(draft)
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter') {
          e.preventDefault()
          onChange(draft)
        }
      }}
    />
  )
}
export function FilterBar({
  filters,
  definitions,
  search = true,
}: {
  filters: ReturnType<typeof useFilters>
  definitions?: FilterDefinition[]
  search?: boolean
}) {
  const auth = useAuth()
  return (
    <div className="filter-bar">
      {search && (
        <FormField label="Pencarian">
          <SearchInput
            key={filters.value('search')}
            value={filters.value('search')}
            title="Cari, lalu Enter…"
            onChange={(value) => filters.set('search', value)}
          />
        </FormField>
      )}
      {definitions?.map((def) => (
        <FormField
          key={def.name}
          label={
            lookups[def.name] &&
            auth.hasPermission(lookups[def.name].permission)
              ? def.label.replace(/^ID /, '')
              : def.label
          }
        >
          {def.options ? (
            <Select
              value={filters.value(def.name)}
              onChange={(e) => filters.set(def.name, e.target.value)}
            >
              <option value="">Semua</option>
              {def.options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </Select>
          ) : lookups[def.name] &&
            auth.hasPermission(lookups[def.name].permission) ? (
            <EntityFilter
              path={lookups[def.name].path}
              value={filters.value(def.name)}
              onChange={(value) => filters.set(def.name, value)}
            />
          ) : (
            <SearchInput
              key={filters.value(def.name)}
              value={filters.value(def.name)}
              title={def.label}
              type={def.type}
              onChange={(value) => filters.set(def.name, value)}
            />
          )}
        </FormField>
      ))}
      <Button variant="ghost" onClick={filters.reset}>
        Reset filter
      </Button>
    </div>
  )
}
