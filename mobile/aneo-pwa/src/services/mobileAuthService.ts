import type { ApiConfig } from '../types';
import { apiPostPublic, normalizeApiBaseUrl, normalizeApiConfig, testApiConnection } from './apiClient';

type MobileAuthPayload = {
  login: string;
  password: string;
  company_id?: number;
};

type MobileCompanyRaw = {
  id?: number;
  name?: string;
  is_default?: boolean | number;
};

type MobileAuthResponse = {
  auth_status?: string;
  message?: string;
  token?: string;
  base_url?: string;
  companies?: MobileCompanyRaw[];
};

type ConnectMobileInput = {
  baseUrl: string;
  login: string;
  password: string;
  companyId?: number;
};

export type MobileCompanyOption = {
  id: number;
  name: string;
  isDefault: boolean;
};

export type MobileConnectResult =
  | {
      status: 'connected';
      config: ApiConfig;
    }
  | {
      status: 'company_required';
      companies: MobileCompanyOption[];
      message: string;
    };

function requiredText(value: string, label: string): string {
  const clean = value.trim();
  if (!clean) {
    throw new Error(`Informe ${label}.`);
  }

  return clean;
}

function normalizeCompanies(rows: MobileCompanyRaw[] | undefined): MobileCompanyOption[] {
  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .map((row) => {
      const id = Number(row.id ?? 0);
      return {
        id,
        name: String(row.name ?? '').trim(),
        isDefault: row.is_default === true || row.is_default === 1,
      };
    })
    .filter((row) => row.id > 0 && row.name !== '');
}

export async function connectWithMobileCredentials(input: ConnectMobileInput): Promise<MobileConnectResult> {
  const baseUrl = normalizeApiBaseUrl(requiredText(input.baseUrl, 'a URL da API'));
  const login = requiredText(input.login, 'usuário/e-mail');
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

  const status = String(response.data?.auth_status ?? '').trim();
  if (status === 'company_required') {
    const companies = normalizeCompanies(response.data?.companies);
    if (companies.length === 0) {
      throw new Error('Não foi possível carregar as empresas deste usuário.');
    }

    return {
      status: 'company_required',
      companies,
      message:
        String(response.data?.message ?? '').trim() ||
        'Este usuário possui mais de uma empresa. Selecione uma para continuar.',
    };
  }

  const token = String(response.data?.token ?? '').trim();
  if (!token) {
    throw new Error('API não retornou token para o app.');
  }

  const apiFromServer = String(response.data?.base_url ?? '').trim();
  const config = normalizeApiConfig({
    baseUrl: apiFromServer || baseUrl,
    token,
  });

  await testApiConnection(config);

  return {
    status: 'connected',
    config,
  };
}
