import { api } from '../../../lib/api'

export interface HealthResponse {
  status: 'ok'
  application: string
}

export async function getHealth(): Promise<HealthResponse> {
  const response = await api.get<HealthResponse>('/health')

  return response.data
}
