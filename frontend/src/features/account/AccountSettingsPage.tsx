import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import {
  Button,
  Card,
  FormField,
  Input,
  PageHeader,
} from '../../components/ui'
import { api } from '../../lib/api'
import { apiError, setFormErrors } from '../../lib/errors'
import { notifications } from '../../lib/notifications'
import { keys } from '../../lib/queries'
import { queryClient } from '../../lib/queryClient'
import type { AuthUser, Resource } from '../../types/api'
import { useAuth } from '../auth/useAuth'

const profileSchema = z.object({
  name: z.string().trim().min(1, 'Nama wajib diisi.').max(255),
  username: z
    .string()
    .trim()
    .min(3, 'Username minimal 3 karakter.')
    .max(50)
    .regex(
      /^[a-zA-Z0-9_-]+$/,
      'Gunakan huruf, angka, tanda hubung, atau garis bawah.',
    ),
  email: z.email('Email tidak valid.').max(255),
})

const passwordSchema = z
  .object({
    current_password: z.string().min(1, 'Masukkan kata sandi saat ini.'),
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

type ProfileValues = z.infer<typeof profileSchema>
type PasswordValues = z.infer<typeof passwordSchema>

export function AccountSettingsPage() {
  const auth = useAuth()
  const user = auth.user
  const profileForm = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    values: {
      name: user?.name ?? '',
      username: user?.username ?? '',
      email: user?.email ?? '',
    },
  })
  const passwordForm = useForm<PasswordValues>({
    resolver: zodResolver(passwordSchema),
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  })

  const profileMutation = useMutation({
    mutationFn: async (values: ProfileValues) =>
      (await api.patch<Resource<AuthUser>>('/auth/profile', values)).data.data,
    onSuccess: (updatedUser) => {
      queryClient.setQueryData(keys.auth, updatedUser)
      profileForm.reset({
        name: updatedUser.name,
        username: updatedUser.username,
        email: updatedUser.email,
      })
      notifications.success('Profil berhasil diperbarui.')
    },
  })
  const passwordMutation = useMutation({
    mutationFn: async (values: PasswordValues) =>
      (await api.put<{ message: string }>('/auth/password', values)).data,
    onSuccess: (response) => {
      passwordForm.reset()
      notifications.success(response.message)
    },
  })

  return (
    <>
      <PageHeader
        title="Pengaturan Akun"
        description="Kelola informasi akun dan keamanan kata sandi Anda."
      />

      <div className="grid items-start gap-6 xl:grid-cols-2">
        <Card className="p-6">
          <h2>Informasi akun</h2>
          <p className="mt-1 text-sm text-slate-500">
            Perubahan hanya berlaku untuk identitas akun Anda.
          </p>
          <form
            className="mt-5 space-y-4"
            onSubmit={profileForm.handleSubmit(async (values) => {
              try {
                await profileMutation.mutateAsync(values)
              } catch (error) {
                notifications.error(apiError(error).message)
                setFormErrors(error, profileForm.setError)
              }
            })}
          >
            <FormField
              label="Nama lengkap"
              error={profileForm.formState.errors.name?.message}
            >
              <Input autoComplete="name" {...profileForm.register('name')} />
            </FormField>
            <FormField
              label="Username"
              error={profileForm.formState.errors.username?.message}
            >
              <Input
                autoComplete="username"
                {...profileForm.register('username')}
              />
            </FormField>
            <FormField
              label="Email"
              error={profileForm.formState.errors.email?.message}
            >
              <Input
                type="email"
                autoComplete="email"
                {...profileForm.register('email')}
              />
            </FormField>
            <div className="form-actions">
              <Button
                type="submit"
                busy={profileMutation.isPending}
                disabled={!profileForm.formState.isDirty}
              >
                Simpan profil
              </Button>
            </div>
          </form>
        </Card>

        <Card className="p-6">
          <h2>Ganti kata sandi</h2>
          <p className="mt-1 text-sm text-slate-500">
            Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, dan
            angka.
          </p>
          <form
            className="mt-5 space-y-4"
            onSubmit={passwordForm.handleSubmit(async (values) => {
              try {
                await passwordMutation.mutateAsync(values)
              } catch (error) {
                notifications.error(apiError(error).message)
                setFormErrors(error, passwordForm.setError)
              }
            })}
          >
            <FormField
              label="Kata sandi saat ini"
              error={passwordForm.formState.errors.current_password?.message}
            >
              <Input
                type="password"
                autoComplete="current-password"
                {...passwordForm.register('current_password')}
              />
            </FormField>
            <FormField
              label="Kata sandi baru"
              error={passwordForm.formState.errors.password?.message}
            >
              <Input
                type="password"
                autoComplete="new-password"
                {...passwordForm.register('password')}
              />
            </FormField>
            <FormField
              label="Konfirmasi kata sandi baru"
              error={
                passwordForm.formState.errors.password_confirmation?.message
              }
            >
              <Input
                type="password"
                autoComplete="new-password"
                {...passwordForm.register('password_confirmation')}
              />
            </FormField>
            <div className="form-actions">
              <Button type="submit" busy={passwordMutation.isPending}>
                Perbarui kata sandi
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </>
  )
}
