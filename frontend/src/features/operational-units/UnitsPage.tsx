import { useState } from 'react'
import { Button } from '../../components/ui'
import type { OperationalUnit } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { UnitItemsView } from '../items/UnitItemsView'
import { UnitForm } from './UnitForm'

function PlusIcon() {
  return (
    <svg
      aria-hidden="true"
      className="size-4"
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      <path d="M12 5v14M5 12h14" />
    </svg>
  )
}

export function UnitsPage() {
  const auth = useAuth()
  const [editing, setEditing] = useState<OperationalUnit | 'new' | null>(null)
  const manageUnits =
    auth.isSuperadmin && auth.hasPermission('operational-unit.manage')
  const manageItems = auth.isSuperadmin && auth.hasPermission('item.manage')
  return (
    <>
      {manageUnits && (
        <div className="page-actions" aria-label="Aksi halaman">
          <Button
            className="rounded-xl px-4 shadow-sm shadow-emerald-700/20"
            onClick={() => setEditing('new')}
          >
            <PlusIcon />
            Tambah Outlet
          </Button>
        </div>
      )}
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
