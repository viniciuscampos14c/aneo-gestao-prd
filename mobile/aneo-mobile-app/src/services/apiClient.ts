import { API_MAX_PAGES, API_PER_PAGE } from '../config/constants';
import type { ApiConfig, ApiEnvelope, ApiMeta } from '../types';

type QueryValue = string | number | boolean | null | undefined;

function sanitizeBaseUrl(baseUrl: string): string {
  const trimmed = baseUrl.trim().replace(/\s+/g, '');
  const noSlash = trimmed.replace(/\/+$/, '');

  if (noSlash.endsWith('/api.php')) {
    return noSlash;
  }
  if (noSlash.endsWith('api.php')) {
    return noSlash;
  }

  return `${noSlash}/api.php`;
}

function buildUrl(baseUrl: string, resource: string, query: Record<string, QueryValue>): string {
  const url = new URL(sanitizeBaseUrl(baseUrl));
  url.searchParams.set('r', resource);

  for (const [key, value] of Object.entries(query)) {
    if (value === null || value === undefined || value === '') {
      continue;
    }
    url.searchParams.set(key, String(value));
  }

  return url.toString();
}

export function normalizeApiConfig(config: ApiConfig): ApiConfig {
  return {
    baseUrl: sanitizeBaseUrl(config.baseUrl),
    token: config.token.trim(),
  };
}

async function parseApiResponse<TData>(response: Response): Promise<ApiEnvelope<TData>> {
  const raw = await response.text();
  let payload: ApiEnvelope<TData> | null = null;

  try {
    payload = JSON.parse(raw) as ApiEnvelope<TData>;
  } catch {
    payload = null;
  }

  if (!response.ok) {
    const message = payload?.message ?? `Erro HTTP ${response.status}`;
    throw new Error(message);
  }

  if (!payload || payload.ok !== true) {
    throw new Error(payload?.message ?? 'Resposta invalida da API.');
  }

  return payload;
}

export async function apiGet<TData>(
  config: ApiConfig,
  resource: string,
  query: Record<string, QueryValue> = {}
): Promise<ApiEnvelope<TData>> {
  const finalConfig = normalizeApiConfig(config);
  const url = buildUrl(finalConfig.baseUrl, resource, query);

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${finalConfig.token}`,
      Accept: 'application/json',
    },
  });

  return parseApiResponse<TData>(response);
}

export async function apiPost<TData, TBody extends Record<string, unknown>>(
  config: ApiConfig,
  resource: string,
  body: TBody
): Promise<ApiEnvelope<TData>> {
  const finalConfig = normalizeApiConfig(config);
  const url = buildUrl(finalConfig.baseUrl, resource, {});

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${finalConfig.token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });

  return parseApiResponse<TData>(response);
}

export async function fetchAllPages<TItem>(
  config: ApiConfig,
  resource: string,
  query: Record<string, QueryValue> = {}
): Promise<{ rows: TItem[]; meta: ApiMeta }> {
  let currentPage = 1;
  let totalPages = 1;
  const rows: TItem[] = [];
  let lastMeta: ApiMeta = {
    total: 0,
    per_page: API_PER_PAGE,
    page: 1,
    pages: 1,
  };

  while (currentPage <= totalPages && currentPage <= API_MAX_PAGES) {
    const response = await apiGet<TItem[]>(config, resource, {
      ...query,
      page: currentPage,
      per_page: API_PER_PAGE,
    });

    const chunk = Array.isArray(response.data) ? response.data : [];
    rows.push(...chunk);

    if (response.meta) {
      lastMeta = response.meta;
      totalPages = Math.max(1, response.meta.pages || 1);
    } else {
      totalPages = 1;
    }

    currentPage += 1;
  }

  return { rows, meta: lastMeta };
}

export async function testApiConnection(config: ApiConfig): Promise<void> {
  const finalConfig = normalizeApiConfig(config);

  await apiGet<unknown[]>(finalConfig, 'students', { per_page: 1, page: 1 });
  await apiGet<unknown[]>(finalConfig, 'invoices', { per_page: 1, page: 1 });
}
