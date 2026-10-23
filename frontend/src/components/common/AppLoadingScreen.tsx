import logoKebun from '../../assets/logo-kebun.png'

export function AppLoadingScreen() {
  return (
    <div
      role="status"
      aria-label="Memuat aplikasi"
      className="fixed inset-0 z-50 grid min-h-dvh place-items-center bg-slate-50 p-6"
    >
      <div className="flex flex-col items-center text-center">
        <div className="relative grid size-20 place-items-center">
          <span className="absolute inset-0 rounded-full border-2 border-emerald-100" />
          <span className="absolute inset-0 animate-spin rounded-full border-2 border-transparent border-r-emerald-200 border-t-emerald-600 motion-reduce:animate-none" />
          <span className="grid size-14 place-items-center rounded-full bg-white shadow-sm ring-1 ring-slate-200/80">
            <img
              className="size-9 object-contain"
              src={logoKebun}
              alt=""
              aria-hidden="true"
            />
          </span>
        </div>
        <p className="mt-5 text-sm font-bold tracking-tight text-slate-800">
          Pandansari
        </p>
        <p className="mt-1 text-xs font-medium text-slate-400">
          Menyiapkan ruang kerja…
        </p>
      </div>
    </div>
  )
}
