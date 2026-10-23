import { useState } from 'react'
import { Button, PageHeader } from '../../components/ui'
import type { OperationalUnit } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { UnitItemsView } from '../items/UnitItemsView'
import { UnitForm } from './UnitForm'
export function UnitsPage() {
  const auth = useAuth()
  const [editing, setEditing] = useState<OperationalUnit | 'new' | null>(null)
  const manageUnits =
    auth.isSuperadmin && auth.hasPermission('operational-unit.manage')
  const manageItems = auth.isSuperadmin && auth.hasPermission('item.manage')
  return (
    <>
      <PageHeader
        title="Outlet"
        description="Kelola outlet, ticketing, dan item yang tersedia pada setiap lokasi."
        actions={
          manageUnits && (
            <Button
              onClick={() => setEditing('new')}
            >
              + Tambah Outlet
            </Button>
          )
        }
      />
      <UnitItemsView
        manage={manageItems}
        manageUnits={manageUnits}
        onEditUnit={(unit) => setEditing(unit)}
      />
      {editing && (
        <UnitForm
          unit={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
          onSaved={() => setEditing(null)}
        />
      )}
    </>
  )
}
