import logoKebun from '../../assets/logo-kebun.png'
import { Link, Navigate } from 'react-router-dom'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
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
  username: z.string().trim().min(1, 'Masukkan username.'),
  password: z.string().min(1, 'Masukkan kata sandi.'),
})

export function LoginPage() {
  const auth = useAuth()
  const [isPasswordVisible, setIsPasswordVisible] = useState(false)
  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
  })
  const username = useWatch({ control: form.control, name: 'username' })

  if (auth.isLoading)
    return (
      <div className="login-shell login-shell-loading">
        <Skeleton rows={4} />
      </div>
    )
  if (auth.isAuthenticated) return <Navigate to="/dashboard" replace />

  return (
    <div className="login-shell">
      <div className="login-stage">
        <section className="login-showcase" aria-label="Pandansari System">
          <LoginScene />
        </section>

        <main className="login-card" aria-labelledby="login-heading">
          <div className="login-card-brand">
            <img className="login-brand-logo" src={logoKebun} alt="Logo Kebun Pandansari" />
            <p className="eyebrow">PANDANSARI SYSTEM</p>
          </div>
          <h1 id="login-heading">
            {auth.isAwaitingAssignment ? 'Pilih ruang kerja' : 'Selamat datang kembali'}
          </h1>
          <p className="login-subtitle">
            {auth.isAwaitingAssignment
              ? 'Pilih peran dan unit yang ingin digunakan pada sesi ini.'
              : 'Masuk untuk mengelola operasional Anda.'}
          </p>
          {auth.isAwaitingAssignment ? (
            <div className="login-form" aria-label="Pilihan ruang kerja">
              {auth.user?.assignments.map((assignment) => (
                <Button
                  key={assignment.id}
                  variant="secondary"
                  className="w-full justify-start text-left"
                  busy={auth.assignmentMutation.isPending}
                  onClick={() => {
                    void auth.selectAssignment(assignment.id)
                  }}
                >
                  <span>
                    <strong className="block">{assignment.role.name}</strong>
                    <small className="text-slate-500">
                      {assignment.operational_unit?.name ?? 'Seluruh area sesuai peran'}
                    </small>
                  </span>
                </Button>
              ))}
              {auth.assignmentMutation.error && (
                <ErrorState error={auth.assignmentMutation.error} />
              )}
              <Button
                variant="ghost"
                className="w-full"
                busy={auth.logoutMutation.isPending}
                onClick={() => void auth.logout().catch(() => undefined)}
              >
                Kembali ke login
              </Button>
            </div>
          ) : (
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
            <FormField label="Username" error={form.formState.errors.username?.message}>
              <span className="login-input-wrap">
                <Input autoComplete="username" placeholder="Masukkan username" {...form.register('username')} />
                <svg className="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" aria-hidden="true">
                  <circle cx="12" cy="8" r="3.25" />
                  <path d="M5.5 20a6.5 6.5 0 0 1 13 0" />
                </svg>
              </span>
            </FormField>
            <FormField label="Kata sandi" error={form.formState.errors.password?.message}>
              <span className="login-input-wrap">
                <Input
                  type={isPasswordVisible ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="Masukkan kata sandi"
                  className="login-password-input"
                  {...form.register('password')}
                />
                <svg className="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" aria-hidden="true">
                  <rect x="4" y="10" width="16" height="10" rx="2" />
                  <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                </svg>
                <button
                  type="button"
                  className="password-visibility-toggle"
                  aria-label={isPasswordVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                  aria-pressed={isPasswordVisible}
                  onClick={() => setIsPasswordVisible((visible) => !visible)}
                >
                  {isPasswordVisible ? (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" aria-hidden="true">
                      <path d="m3 3 18 18" />
                      <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                      <path d="M9.9 5.1A10.7 10.7 0 0 1 12 4.9c5.3 0 8.5 4.7 9.1 5.7a1 1 0 0 1 0 1c-.4.7-1.8 2.7-4.2 4" />
                      <path d="M6.6 6.6C4.5 8 3.2 10 2.9 10.6a1 1 0 0 0 0 1C3.5 12.6 6.7 17.3 12 17.3c.8 0 1.5-.1 2.2-.3" />
                    </svg>
                  ) : (
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" aria-hidden="true">
                      <path d="M2.9 12a1 1 0 0 1 0-1C3.5 10 6.7 5.3 12 5.3s8.5 4.7 9.1 5.7a1 1 0 0 1 0 1C20.5 13 17.3 17.7 12 17.7S3.5 13 2.9 12Z" />
                      <circle cx="12" cy="11.5" r="3" />
                    </svg>
                  )}
                </button>
              </span>
            </FormField>
            <div className="login-helper-row">
              <Link
                className="login-link"
                to="/forgot-password"
                state={{ username: username ?? '' }}
              >
                Lupa password?
              </Link>
            </div>
            {auth.loginMutation.error && <ErrorState error={auth.loginMutation.error} />}
            <Button type="submit" className="login-submit" busy={auth.loginMutation.isPending}>
              Masuk
              <span className="login-submit-arrow" aria-hidden="true">→</span>
            </Button>
          </form>
          )}
          <p className="login-footer">Ruang kerja operasional Pandansari</p>
        </main>
      </div>
    </div>
  )
}
