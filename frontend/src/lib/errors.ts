import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
interface ErrorBody {
  message?: string
  errors?: Record<string, string[]>
}
export function apiError(error: unknown) {
  if (!axios.isAxiosError<ErrorBody>(error))
    return {
      status: 0,
      message: 'Terjadi kesalahan. Silakan coba kembali.',
      fields: {} as Record<string, string[]>,
    }
  const status = error.response?.status ?? 0
  const messages: Record<number, string> = {
    0: 'Tidak dapat terhubung ke server. Periksa koneksi Anda.',
    401: 'Sesi berakhir atau username dan kata sandi tidak sesuai.',
    403: 'Anda tidak memiliki izin untuk mengakses data ini.',
    404: 'Data tidak ditemukan.',
    419: 'Sesi keamanan berakhir. Muat ulang sesi dan coba kembali.',
    429: 'Terlalu banyak permintaan. Coba kembali beberapa saat lagi.',
  }
  return {
    status,
    message:
      status >= 500
        ? 'Server mengalami gangguan. Silakan coba kembali.'
        : (messages[status] ??
          error.response?.data?.message ??
          'Permintaan gagal diproses.'),
    fields: error.response?.data?.errors ?? {},
  }
}
export function setFormErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
) {
  Object.entries(apiError(error).fields).forEach(([name, messages]) =>
    setError(name as Path<T>, { type: 'server', message: messages[0] }),
  )
}
