import {
  createContext,
  useContext,
  useEffect,
  type Dispatch,
  type SetStateAction,
} from 'react'

export interface TopbarOverride {
  label?: string
  description?: string
}

export const TopbarOverrideContext = createContext<
  Dispatch<SetStateAction<TopbarOverride | undefined>> | undefined
>(undefined)

export function useTopbarOverride({
  label,
  description,
}: TopbarOverride) {
  const setOverride = useContext(TopbarOverrideContext)

  useEffect(() => {
    if (!setOverride) return

    setOverride({ label, description })
    return () => setOverride(undefined)
  }, [description, label, setOverride])
}
