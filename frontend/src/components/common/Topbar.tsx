export function Topbar() {
  return (
    <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
      <div className="flex items-center gap-3">
        <div className="grid size-9 place-items-center rounded-xl bg-emerald-600 text-sm font-bold text-white lg:hidden">
          PS
        </div>
        <div>
          <p className="text-sm font-semibold text-slate-900">Pandansari System</p>
          <p className="hidden text-xs text-slate-500 sm:block">Administrative workspace</p>
        </div>
      </div>

      <span className="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
        Development
      </span>
    </header>
  )
}
