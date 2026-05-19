import type { ApiConfig } from '../types';
import { normalizeApiConfig } from './apiClient';

const API_CONFIG_STORAGE_KEY = 'aneo_pwa_api_config_v1';
const SESSION_TIMEOUT_MS = 8 * 60 * 60 * 1000;

type StoredApiConfig = ApiConfig & {
  lastActivityAt: number;
};

function isApiConfig(value: unknown): value is ApiConfig {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const candidate = value as Record<string, unknown>;
  return typeof candidate.baseUrl === 'string' && typeof candidate.token === 'string';
}

function isStoredApiConfig(value: unknown): value is StoredApiConfig {
  if (!isApiConfig(value)) {
    return false;
  }

  const candidate = value as Record<string, unknown>;
  return typeof candidate.lastActivityAt === 'number' && Number.isFinite(candidate.lastActivityAt);
}

function buildStoredApiConfig(config: ApiConfig, lastActivityAt = Date.now()): StoredApiConfig | null {
  const normalized = normalizeApiConfig(config);
  if (!normalized.baseUrl || !normalized.token) {
    return null;
  }

  return {
    ...normalized,
    lastActivityAt,
  };
}

function isExpired(lastActivityAt: number): boolean {
  return Date.now() - lastActivityAt > SESSION_TIMEOUT_MS;
}

export async function loadStoredApiConfig(): Promise<ApiConfig | null> {
  try {
    const raw = window.localStorage.getItem(API_CONFIG_STORAGE_KEY);
    if (!raw) {
      return null;
    }

    const parsed: unknown = JSON.parse(raw);
    if (!isStoredApiConfig(parsed)) {
      window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
      return null;
    }

    if (isExpired(parsed.lastActivityAt)) {
      window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
      return null;
    }

    const stored = buildStoredApiConfig(parsed);
    if (!stored) {
      window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
      return null;
    }

    window.localStorage.setItem(API_CONFIG_STORAGE_KEY, JSON.stringify(stored));
    return normalizeApiConfig(stored);
  } catch {
    return null;
  }
}

export async function saveStoredApiConfig(config: ApiConfig): Promise<void> {
  const stored = buildStoredApiConfig(config);
  if (!stored) {
    return;
  }

  window.localStorage.setItem(API_CONFIG_STORAGE_KEY, JSON.stringify(stored));
}

export async function touchStoredApiConfigSession(): Promise<void> {
  try {
    const raw = window.localStorage.getItem(API_CONFIG_STORAGE_KEY);
    if (!raw) {
      return;
    }

    const parsed: unknown = JSON.parse(raw);
    if (!isStoredApiConfig(parsed)) {
      window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
      return;
    }

    if (isExpired(parsed.lastActivityAt)) {
      window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
      return;
    }

    const stored = buildStoredApiConfig(parsed);
    if (!stored) {
      window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
      return;
    }

    window.localStorage.setItem(API_CONFIG_STORAGE_KEY, JSON.stringify(stored));
  } catch {
    window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
  }
}

export async function clearStoredApiConfig(): Promise<void> {
  window.localStorage.removeItem(API_CONFIG_STORAGE_KEY);
}
