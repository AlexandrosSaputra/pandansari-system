import { useState } from 'react'
import { Button, Notice, PageHeader } from '../../components/ui'
import type { OperationalUnit } from '../../types/api'
import { useAuth } from '../auth/useAuth'
import { UnitItemsView } from '../items/UnitItemsView'
import { UnitForm } from './UnitForm'
export function UnitsPage() {
  const auth = useAuth()
  const [editing, setEditing] = useState<OperationalUnit | 'new' | null>(null)
  const [savedMessage, setSavedMessage] = useState('')
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
              onClick={() => {
                setSavedMessage('')
                setEditing('new')
              }}
            >
              + Tambah Outlet
            </Button>
          )
        }
      />
      {savedMessage && <Notice>{savedMessage}</Notice>}
      <UnitItemsView
        manage={manageItems}
        manageUnits={manageUnits}
        onEditUnit={(unit) => {
          setSavedMessage('')
          setEditing(unit)
        }}
        onSaved={setSavedMessage}
      />
      {editing && (
        <UnitForm
          unit={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            setSavedMessage('Outlet / Ticketing berhasil disimpan.')
          }}
        />
      )}
    </>
  )
}
