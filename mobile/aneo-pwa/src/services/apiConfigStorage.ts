import type { ApiConfig } from '../types';
import { normalizeApiConfig } from './apiClient';

const API_CONFIG_STORAGE_KEY = 'aneo_pwa_api_config_v1';

function isApiConfig(value: unknown): value is ApiConfig {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const candidate = value as Record<string, unknown>;
  return typeof candidate.baseUrl === 'string' && typeof candidate.token === 'string';
}

export async function loadStoredApiConfig(): Promise<ApiConfig | null> {
  try {
    const raw = window.localStorage.getItem(API_CONFIG_STORAGE_KEY);
    if (!raw) {
      return null;
    }

    const parsed: unknown = JSON.parse(raw);
    if (!isApiConfig(parsed)) {
      return null;
    }

    const normalized = normalizeApiConfig(parsed);
    if (!normalized.baseUrl || !normalized.token) {
      return null;
    }

    return normalized;
  } catch {
    return null;
  }
}

export async function saveStoredApiConfig(config: ApiConfig): Promise<void> {
  const normalized = normalizeApiConfig(config);
  if (!normalized.baseUrl || !normalized.token) {
    return;
  }

  window.localStorage.setItem(API_CONFIG_STORAGE_KEY, JSON.stringify(normalized));
}

export async function clearStoredApiConfig(): Promise<void> {
  window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
}
