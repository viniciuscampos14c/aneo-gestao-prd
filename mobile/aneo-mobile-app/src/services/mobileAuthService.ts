import type { ApiConfig } from '../types';
import { apiPostPublic, normalizeApiBaseUrl, normalizeApiConfig, testApiConnection } from './apiClient';

type MobileAuthPayload = {
  login: string;
  password: string;
  company_id?: number;
};

type MobileAuthResponse = {
  token?: string;
  base_url?: string;
  user?: {
    id?: number;
    name?: string;
    email?: string;
    role?: string;
  };
  company?: {
    id?: number;
    name?: string;
  };
};

type ConnectMobileInput = {
  baseUrl: string;
  login: string;
  password: string;
  companyId?: number;
};

function requiredText(value: string, label: string): string {
  const clean = value.trim();
  if (!clean) {
    throw new Error(`Informe ${label}.`);
  }

  return clean;
}

export async function connectWithMobileCredentials(input: ConnectMobileInput): Promise<ApiConfig> {
  const baseUrl = normalizeApiBaseUrl(requiredText(input.baseUrl, 'a URL da API'));
  const login = requiredText(input.login, 'usuario/email');
  const password = requiredText(input.password, 'a senha');

  const payload: MobileAuthPayload = {
    login,
    password,
  };

  if (input.companyId && input.companyId > 0) {
    payload.company_id = input.companyId;
  }

  const response = await apiPostPublic<MobileAuthResponse, MobileAuthPayload>(
    baseUrl,
    'mobile-auth',
    payload
  );

  const token = String(response.data?.token ?? '').trim();
  if (!token) {
    throw new Error('API nao retornou token para o app.');
  }

  const apiFromServer = String(response.data?.base_url ?? '').trim();
  const config = normalizeApiConfig({
    baseUrl: apiFromServer || baseUrl,
    token,
  });

  await testApiConnection(config);
  return config;
}
