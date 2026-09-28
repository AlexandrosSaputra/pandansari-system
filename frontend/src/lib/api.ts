import axios from 'axios'

const apiUrl = import.meta.env.VITE_API_URL?.replace(/\/+$/, '') || '/api'

export const api = axios.create({
  baseURL: apiUrl,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  withXSRFToken: true,
})
