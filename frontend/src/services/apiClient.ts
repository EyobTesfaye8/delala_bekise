export class ApiError extends Error {
  status: number;
  errorCode?: string;
  errors?: Record<string, string[]>;
  lockedUntil?: string;
  requestId?: string;

  constructor(
    message: string,
    status: number,
    data?: {
      error_code?: string;
      errors?: Record<string, string[]>;
      locked_until?: string;
      [key: string]: unknown;
    },
    requestId?: string
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errorCode = data?.error_code;
    this.errors = data?.errors;
    this.lockedUntil = data?.locked_until;
    this.requestId = requestId;
  }
}

const TOKEN_KEY = 'delala_auth_token';
const API_BASE_URL = import.meta.env.VITE_API_URL || '/api/v1';

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string): void => localStorage.setItem(TOKEN_KEY, token),
  clear: (): void => localStorage.removeItem(TOKEN_KEY),
};

function generateUUID(): string {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

export async function apiRequest<T = unknown>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const url = `${API_BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
  const token = tokenStorage.get();
  const requestId = generateUUID();

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Request-ID': requestId,
    ...(options.headers || {}),
  };

  if (token) {
    (headers as Record<string, string>)['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(url, {
    ...options,
    headers,
  });

  const responseRequestId = response.headers.get('x-request-id') || requestId;

  let responseData: any = null;
  const contentType = response.headers.get('content-type');
  if (contentType && contentType.includes('application/json')) {
    try {
      responseData = await response.json();
    } catch {
      responseData = null;
    }
  }

  if (!response.ok) {
    let errorMessage = responseData?.message;
    if (responseData?.errors && typeof responseData.errors === 'object') {
      const fieldErrors = Object.values(responseData.errors)
        .flat()
        .filter((val): val is string => typeof val === 'string' && val.length > 0);
      if (fieldErrors.length > 0) {
        errorMessage = fieldErrors.join(' ');
      }
    }
    if (!errorMessage) {
      errorMessage = `Request failed with status ${response.status}`;
    }

    throw new ApiError(errorMessage, response.status, responseData, responseRequestId);
  }

  return responseData as T;
}
