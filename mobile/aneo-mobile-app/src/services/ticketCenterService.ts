import type { ApiConfig, ApiTicket } from '../types';
import { fetchAllPages } from './apiClient';

export async function loadTicketsFromApi(config: ApiConfig): Promise<ApiTicket[]> {
  const response = await fetchAllPages<ApiTicket>(config, 'tickets');

  return response.rows.sort((left, right) => {
    const leftDate = String(left.updated_at ?? left.created_at ?? '');
    const rightDate = String(right.updated_at ?? right.created_at ?? '');
    if (leftDate === rightDate) return 0;
    return leftDate > rightDate ? -1 : 1;
  });
}
