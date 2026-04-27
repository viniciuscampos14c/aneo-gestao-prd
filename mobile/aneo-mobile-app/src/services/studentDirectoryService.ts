import type { ApiConfig, ApiStudent } from '../types';
import { fetchAllPages } from './apiClient';

export async function loadStudentsFromApi(config: ApiConfig): Promise<ApiStudent[]> {
  const response = await fetchAllPages<ApiStudent>(config, 'students');

  return response.rows.sort((left, right) =>
    String(left.full_name ?? '').localeCompare(String(right.full_name ?? ''), 'pt-BR')
  );
}
