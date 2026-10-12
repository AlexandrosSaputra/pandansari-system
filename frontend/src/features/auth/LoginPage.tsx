import { Navigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ErrorState,
  FormField,
  Input,
  Skeleton,
} from '../../components/ui'
import { setFormErrors } from '../../lib/errors'
import { LoginScene } from './LoginScene'
import { useAuth } from './useAuth'

const schema = z.object({
  email: z.email('Masukkan email yang valid.'),
  password: z.string().min(1, 'Masukkan kata sandi.'),
})

export function LoginPage() {
  const auth = useAuth()
  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
  })

  if (auth.isLoading)
    return (
      <div className="login-shell login-shell-loading">
        <Skeleton rows={4} />
      </div>
    )
  if (auth.isAuthenticated) return <Navigate to="/dashboard" replace />

  return (
    <div className="login-shell">
      <section className="login-showcase" aria-label="Pandansari System">
        <LoginScene />
        <div className="login-showcase-copy">
          <div className="login-brand-mark" aria-hidden="true">PS</div>
          <div>
            <p className="login-brand-name">Pandansari System</p>
            <p>Kelola operasional dan persediaan dalam satu ruang kerja.</p>
          </div>
        </div>
      </section>

      <main className="login-card" aria-labelledby="login-heading">
        <div className="login-card-brand">
          <div className="login-brand-mark">PS</div>
          <p className="eyebrow">PANDANSARI SYSTEM</p>
        </div>
        <h1 id="login-heading">Selamat datang kembali</h1>
        <p className="login-subtitle">Masuk untuk mengelola operasional Anda.</p>
        <form
          className="login-form"
          onSubmit={form.handleSubmit(async (values) => {
            try {
              await auth.login(values)
              form.reset()
            } catch (error) {
              setFormErrors(error, form.setError)
            }
          })}
        >
          <FormField label="Email" error={form.formState.errors.email?.message}>
            <span className="login-input-wrap">
              <Input type="email" autoComplete="username" placeholder="Masukkan alamat email" {...form.register('email')} />
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" aria-hidden="true">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <path d="m3 7 9 6 9-6" />
              </svg>
            </span>
          </FormField>
          <FormField label="Kata sandi" error={form.formState.errors.password?.message}>
            <span className="login-input-wrap">
              <Input type="password" autoComplete="current-password" placeholder="Masukkan kata sandi" {...form.register('password')} />
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" aria-hidden="true">
                <rect x="4" y="10" width="16" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
              </svg>
            </span>
          </FormField>
          {auth.loginMutation.error && <ErrorState error={auth.loginMutation.error} />}
          <Button type="submit" className="login-submit" busy={auth.loginMutation.isPending}>
            Masuk
          </Button>
        </form>
        <p className="login-footer">Ruang kerja operasional Pandansari</p>
      </main>
    </div>
  )
}
