import type { ApiConfig, ApiPaymentMethod } from '../types';
import { apiGet } from './apiClient';

export async function loadPaymentMethodsFromApi(config: ApiConfig): Promise<ApiPaymentMethod[]> {
  const response = await apiGet<ApiPaymentMethod[]>(config, 'payment_methods', {
    is_active: 1,
  });

  return Array.isArray(response.data) ? response.data : [];
}
