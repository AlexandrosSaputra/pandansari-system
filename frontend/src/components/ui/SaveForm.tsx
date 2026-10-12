import { useState, type ReactNode } from 'react'
import type { FieldValues, UseFormReturn } from 'react-hook-form'
import type { UseMutationResult } from '@tanstack/react-query'
import { Button, ConfirmDialog, ErrorState } from './index'
import { setFormErrors } from '../../lib/errors'
export function SaveForm<T extends FieldValues>({
  form,
  mutation,
  children,
  onClose,
  onSaved,
  wasActive,
  blocked = false,
}: {
  form: UseFormReturn<T>
  mutation: Pick<
    UseMutationResult<unknown, Error, T>,
    'mutateAsync' | 'isPending' | 'error'
  >
  children: ReactNode
  onClose: () => void
  onSaved: () => void
  wasActive?: boolean
  blocked?: boolean
}) {
  const [pending, setPending] = useState<T | null>(null)
  const save = async (values: T) => {
    try {
      await mutation.mutateAsync(values)
      onSaved()
    } catch (error) {
      setPending(null)
      setFormErrors(error, form.setError)
    }
  }
  return (
    <>
      <form
        onSubmit={form.handleSubmit((values) => {
          if (blocked || mutation.isPending) return
          if (wasActive && values.is_active === false) setPending(values)
          else void save(values)
        })}
      >
        <fieldset disabled={mutation.isPending} className="space-y-4">
          {children}
        </fieldset>
        {mutation.error && <ErrorState error={mutation.error} />}
        <div className="form-actions">
          <Button
            variant="secondary"
            disabled={mutation.isPending}
            onClick={onClose}
          >
            Batal
          </Button>
          <Button type="submit" busy={mutation.isPending} disabled={blocked}>
            Simpan
          </Button>
        </div>
      </form>
      {pending && (
        <ConfirmDialog
          title="Nonaktifkan data?"
          busy={mutation.isPending}
          onClose={() => setPending(null)}
          onConfirm={() => {
            void save(pending)
          }}
          danger
        >
          Data ini akan dinonaktifkan. Pastikan perubahan sudah sesuai.
        </ConfirmDialog>
      )}
    </>
  )
}
