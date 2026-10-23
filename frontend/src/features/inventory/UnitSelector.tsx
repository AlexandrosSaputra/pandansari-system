import { ResourcePicker } from '../../components/ui/ResourcePicker'
import { FormField } from '../../components/ui'
import type { OperationalUnit, UnitType } from '../../types/api'
import { useAuth } from '../auth/useAuth'
export function UnitSelector({
  value,
  name,
  type,
  onChange,
}: {
  value?: number
  name?: string
  type?: UnitType
  onChange: (unit: OperationalUnit) => void
}) {
  const auth = useAuth()
  if (!auth.hasPermission('operational-unit.view'))
    return (
      <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Pilihan unit belum tersedia untuk akun ini. Minta Superadmin
        mengaktifkan akses melihat unit operasional pada Role & Permission.
      </p>
    )
  return (
    <FormField label="Unit operasional">
      <ResourcePicker<OperationalUnit>
        path="/operational-units"
        params={{ is_active: 1, type }}
        value={value}
        selectedLabel={name}
        identify={(row) => row.id}
        describe={(row) => `${row.code} · ${row.name}`}
        onChange={onChange}
      />
    </FormField>
  )
}
