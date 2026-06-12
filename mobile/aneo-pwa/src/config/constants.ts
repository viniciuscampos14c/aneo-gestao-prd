const fallbackApiBaseUrl = 'https://erp-hml.aneobrasil.com.br/api.php';

export const DEFAULT_API_BASE_URL = import.meta.env.VITE_ANEO_API_BASE_URL || fallbackApiBaseUrl;
export const API_MAX_PAGES = 25;
export const API_PER_PAGE = 200;
export const AUTO_REFRESH_MS = 5 * 60 * 1000;
