import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import logoKebun from '../../assets/logo-kebun.png'
import {
  Button,
  ErrorState,
  FormField,
  Input,
  Notice,
} from '../../components/ui'
import { api, sessionApi } from '../../lib/api'
import { setFormErrors } from '../../lib/errors'
import { LoginScene } from './LoginScene'

const requestSchema = z.object({
  username: z.string().trim().min(1, 'Masukkan username.'),
})

const resetSchema = z
  .object({
    username: z.string().trim().min(1, 'Masukkan username.'),
    otp: z
      .string()
      .trim()
      .length(6, 'Kode OTP harus terdiri dari 6 karakter.')
      .regex(
        /^[A-HJ-NP-Z2-9]+$/i,
        'Gunakan huruf dan angka pada kode OTP yang diterima.',
      ),
    password: z
      .string()
      .min(12, 'Kata sandi minimal 12 karakter.')
      .regex(/[a-z]/, 'Kata sandi harus memiliki huruf kecil.')
      .regex(/[A-Z]/, 'Kata sandi harus memiliki huruf besar.')
      .regex(/\d/, 'Kata sandi harus memiliki angka.'),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Konfirmasi kata sandi tidak sama.',
  })

type RequestValues = z.infer<typeof requestSchema>
type ResetValues = z.infer<typeof resetSchema>
type ApiMessage = { message: string }

export function ForgotPasswordPage() {
  const location = useLocation()
  const initialUsername =
    typeof location.state?.username === 'string'
      ? location.state.username.trim()
      : ''
  const [step, setStep] = useState<'request' | 'reset' | 'done'>('request')
  const [message, setMessage] = useState('')
  const [requestError, setRequestError] = useState<unknown>(null)
  const [resetError, setResetError] = useState<unknown>(null)
  const [resendSeconds, setResendSeconds] = useState(0)
  const [isNewPasswordVisible, setIsNewPasswordVisible] = useState(false)
  const [isConfirmationPasswordVisible, setIsConfirmationPasswordVisible] =
    useState(false)
  const requestForm = useForm<RequestValues>({
    resolver: zodResolver(requestSchema),
    defaultValues: { username: initialUsername },
  })
  const resetForm = useForm<ResetValues>({
    resolver: zodResolver(resetSchema),
    defaultValues: {
      username: initialUsername,
      otp: '',
      password: '',
      password_confirmation: '',
    },
  })

  useEffect(() => {
    if (resendSeconds <= 0) return

    const timer = window.setInterval(() => {
      setResendSeconds((seconds) => Math.max(0, seconds - 1))
    }, 1000)

    return () => window.clearInterval(timer)
  }, [resendSeconds])

  async function requestOtp(username: string) {
    await sessionApi.get('/sanctum/csrf-cookie')
    const response = await api.post<ApiMessage>('/auth/password/forgot', {
      username,
    })
    const normalizedUsername = username.trim().toLowerCase()
    resetForm.setValue('username', normalizedUsername)
    resetForm.setValue('otp', '')
    setMessage(response.data.message)
    setResendSeconds(60)
    setStep('reset')
  }

  const otpField = resetForm.register('otp')

  return (
    <div className="login-shell">
      <div className="login-stage">
        <section className="login-showcase" aria-label="Pandansari System">
          <LoginScene />
        </section>

        <main className="login-card" aria-labelledby="password-reset-heading">
          <div className="login-card-brand">
            <img
              className="login-brand-logo"
              src={logoKebun}
              alt="Logo Kebun Pandansari"
            />
            <p className="eyebrow">PANDANSARI SYSTEM</p>
          </div>

          <h1 id="password-reset-heading">
            {step === 'done' ? 'Kata sandi diperbarui' : 'Lupa password'}
          </h1>
          <p className="login-subtitle">
            {step === 'request' &&
              'Masukkan username. Kode OTP akan dikirim ke email pemulihan superadmin.'}
            {step === 'reset' &&
              'Masukkan kode OTP 6 karakter yang diberikan superadmin, lalu buat kata sandi baru.'}
            {step === 'done' &&
              'Gunakan kata sandi baru Anda untuk masuk kembali ke sistem.'}
          </p>

          {step === 'request' && (
            <form
              className="login-form"
              onSubmit={requestForm.handleSubmit(async ({ username }) => {
                setRequestError(null)
                try {
                  await requestOtp(username)
                } catch (error) {
                  setRequestError(error)
                  setFormErrors(error, requestForm.setError)
                }
              })}
            >
              <FormField
                label="Username"
                error={requestForm.formState.errors.username?.message}
              >
                <span className="login-input-wrap">
                  <Input
                    autoComplete="username"
                    placeholder="Masukkan username"
                    autoFocus
                    {...requestForm.register('username')}
                  />
                  <svg
                    className="login-input-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.9"
                    aria-hidden="true"
                  >
                    <circle cx="12" cy="8" r="3.25" />
                    <path d="M5.5 20a6.5 6.5 0 0 1 13 0" />
                  </svg>
                </span>
              </FormField>
              {requestError !== null && <ErrorState error={requestError} />}
              <Button
                type="submit"
                className="login-submit"
                busy={requestForm.formState.isSubmitting}
              >
                Kirim kode OTP
                <span className="login-submit-arrow" aria-hidden="true">
                  →
                </span>
              </Button>
              <Link className="login-link text-center" to="/login">
                Kembali ke halaman login
              </Link>
            </form>
          )}

          {step === 'reset' && (
            <form
              className="login-form"
              onSubmit={resetForm.handleSubmit(async (values) => {
                setResetError(null)
                try {
                  const response = await api.post<ApiMessage>(
                    '/auth/password/reset',
                    {
                      ...values,
                      otp: values.otp.toUpperCase(),
                    },
                  )
                  setMessage(response.data.message)
                  setStep('done')
                  resetForm.reset()
                } catch (error) {
                  setResetError(error)
                  setFormErrors(error, resetForm.setError)
                }
              })}
            >
              <Notice>{message}</Notice>
              <FormField
                label="Username"
                error={resetForm.formState.errors.username?.message}
              >
                <Input
                  readOnly
                  autoComplete="username"
                  {...resetForm.register('username')}
                />
              </FormField>
              <FormField
                label="Kode OTP"
                hint="6 karakter huruf kapital dan angka; maksimal 3 percobaan."
                error={resetForm.formState.errors.otp?.message}
              >
                <Input
                  {...otpField}
                  className="otp-input"
                  inputMode="text"
                  autoComplete="one-time-code"
                  autoCapitalize="characters"
                  maxLength={6}
                  placeholder="ABC234"
                  onChange={(event) => {
                    event.target.value = event.target.value
                      .toUpperCase()
                      .replace(/[^A-HJ-NP-Z2-9]/g, '')
                    void otpField.onChange(event)
                  }}
                />
              </FormField>
              <FormField
                label="Kata sandi baru"
                hint="Minimal 12 karakter, huruf besar, huruf kecil, dan angka."
                error={resetForm.formState.errors.password?.message}
              >
                <span className="login-input-wrap">
                  <Input
                    type={isNewPasswordVisible ? 'text' : 'password'}
                    autoComplete="new-password"
                    className="login-password-input"
                    {...resetForm.register('password')}
                  />
                  <PasswordVisibilityToggle
                    visible={isNewPasswordVisible}
                    onClick={() => setIsNewPasswordVisible((visible) => !visible)}
                  />
                </span>
              </FormField>
              <FormField
                label="Konfirmasi kata sandi"
                error={
                  resetForm.formState.errors.password_confirmation?.message
                }
              >
                <span className="login-input-wrap">
                  <Input
                    type={isConfirmationPasswordVisible ? 'text' : 'password'}
                    autoComplete="new-password"
                    className="login-password-input"
                    {...resetForm.register('password_confirmation')}
                  />
                  <PasswordVisibilityToggle
                    visible={isConfirmationPasswordVisible}
                    onClick={() =>
                      setIsConfirmationPasswordVisible((visible) => !visible)
                    }
                  />
                </span>
              </FormField>
              {resetError !== null && <ErrorState error={resetError} />}
              <Button
                type="submit"
                className="login-submit"
                busy={resetForm.formState.isSubmitting}
              >
                Perbarui kata sandi
                <span className="login-submit-arrow" aria-hidden="true">
                  →
                </span>
              </Button>
              <div className="login-secondary-actions">
                <Button
                  variant="ghost"
                  disabled={resendSeconds > 0}
                  onClick={async () => {
                    setResetError(null)
                    try {
                      await requestOtp(resetForm.getValues('username'))
                    } catch (error) {
                      setResetError(error)
                    }
                  }}
                >
                  {resendSeconds > 0
                    ? `Kirim ulang dalam ${resendSeconds} detik`
                    : 'Kirim ulang OTP'}
                </Button>
                <Button
                  variant="ghost"
                  onClick={() => {
                    requestForm.setValue(
                      'username',
                      resetForm.getValues('username'),
                    )
                    setStep('request')
                    setResetError(null)
                  }}
                >
                  Ganti username
                </Button>
              </div>
            </form>
          )}

          {step === 'done' && (
            <div className="login-form">
              <Notice>{message}</Notice>
              <Link className="btn btn-primary login-submit" to="/login">
                Kembali ke halaman login
              </Link>
            </div>
          )}

          <p className="login-footer">Ruang kerja operasional Pandansari</p>
        </main>
      </div>
    </div>
  )
}

function PasswordVisibilityToggle({
  visible,
  onClick,
}: {
  visible: boolean
  onClick: () => void
}) {
  return (
    <button
      type="button"
      className="password-visibility-toggle"
      aria-label={visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
      aria-pressed={visible}
      onClick={onClick}
    >
      {visible ? (
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.9"
          aria-hidden="true"
        >
          <path d="m3 3 18 18" />
          <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
          <path d="M9.9 5.1A10.7 10.7 0 0 1 12 4.9c5.3 0 8.5 4.7 9.1 5.7a1 1 0 0 1 0 1c-.4.7-1.8 2.7-4.2 4" />
          <path d="M6.6 6.6C4.5 8 3.2 10 2.9 10.6a1 1 0 0 0 0 1C3.5 12.6 6.7 17.3 12 17.3c.8 0 1.5-.1 2.2-.3" />
        </svg>
      ) : (
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.9"
          aria-hidden="true"
        >
          <path d="M2.9 12a1 1 0 0 1 0-1C3.5 10 6.7 5.3 12 5.3s8.5 4.7 9.1 5.7a1 1 0 0 1 0 1C20.5 13 17.3 17.7 12 17.7S3.5 13 2.9 12Z" />
          <circle cx="12" cy="11.5" r="3" />
        </svg>
      )}
    </button>
  )
}
