import type { Dispatch, ReactNode, SetStateAction } from 'react'
import {
  TopbarOverrideContext,
  type TopbarOverride,
} from './topbarOverride'

export function TopbarOverrideProvider({
  children,
  setOverride,
}: {
  children: ReactNode
  setOverride: Dispatch<SetStateAction<TopbarOverride | undefined>>
}) {
  return (
    <TopbarOverrideContext value={setOverride}>
      {children}
    </TopbarOverrideContext>
  )
}
