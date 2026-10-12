import { useState } from 'react'
import { ResourcePicker } from './ResourcePicker'
interface NamedRecord {
  id: number
  name: string
  code?: string
  item_code?: string
}
export function EntityFilter({
  path,
  value,
  onChange,
}: {
  path: string
  value: string
  onChange: (value: string) => void
}) {
  const [selected, setSelected] = useState<NamedRecord>()
  return (
    <ResourcePicker<NamedRecord>
      path={path}
      value={Number(value) || undefined}
      selectedLabel={selected?.id === Number(value) ? selected.name : undefined}
      identify={(row) => row.id}
      describe={(row) =>
        `${row.code ?? row.item_code ?? ''} ${row.name}`.trim()
      }
      onChange={(row) => {
        setSelected(row)
        onChange(String(row.id))
      }}
    />
  )
}
