/**
 * Cliente da API ADAB — mesma origem em produção (/api),
 * proxy do Vite em dev (ver vite.config.ts).
 *
 * Autenticação por cookie de sessão; mutações enviam X-CSRF-Token
 * obtido em login() ou me().
 */

const API_URL = import.meta.env.VITE_API_URL ?? '/api';

let csrfToken: string | null = null;
export const setCsrfToken = (t: string | null) => { csrfToken = t; };

export class ApiError extends Error {
  constructor(public code: string, message: string, public status: number) {
    super(message);
  }
}

interface ApiEnvelope<T> {
  data?: T;
  error?: { code: string; message: string };
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  if (!headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json');
  }
  if (csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes((options.method ?? 'GET').toUpperCase())) {
    headers.set('X-CSRF-Token', csrfToken);
  }

  const res = await fetch(`${API_URL}${path}`, {
    credentials: 'same-origin',
    ...options,
    headers,
  });

  const body = (await res.json().catch(() => ({}))) as ApiEnvelope<T>;
  if (!res.ok || body.error) {
    throw new ApiError(body.error?.code ?? 'ERRO', body.error?.message ?? 'Erro na requisição.', res.status);
  }
  return body.data as T;
}

// ---------------------------------------------------------------------
// Auth

export interface Usuario {
  id: number;
  pessoa_id: number;
  nome: string;
  email: string;
  perfil: 'admin' | 'secretaria' | 'dirigente' | 'ebd_admin' | 'ebd_professor' | 'lider_departamento';
}

export const auth = {
  async login(email: string, senha: string) {
    const data = await apiFetch<{ usuario: Usuario; csrf: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, senha }),
    });
    setCsrfToken(data.csrf);
    return data.usuario;
  },

  async logout() {
    await apiFetch<{ ok: true }>('/auth/logout', { method: 'POST' });
    setCsrfToken(null);
  },

  async me() {
    const data = await apiFetch<{ usuario: Usuario; csrf: string }>('/auth/me');
    setCsrfToken(data.csrf);
    return data.usuario;
  },

  async recuperar(email: string) {
    return apiFetch<{ mensagem: string }>('/auth/recuperar', {
      method: 'POST',
      body: JSON.stringify({ email }),
    });
  },

  async redefinir(token: string, senha: string) {
    return apiFetch<{ ok: true }>('/auth/redefinir', {
      method: 'POST',
      body: JSON.stringify({ token, senha }),
    });
  },
};

// ---------------------------------------------------------------------
// Público (site)

export interface ItemProgramacao {
  tipo: 'culto' | 'evento';
  titulo: string;
  data: string;
  horario_inicio: string | null;
  data_fim: string | null;
  categoria: string;
}

export interface EventoPublico {
  id: number;
  nome: string;
  tipo: string;
  data_inicio: string;
  data_fim: string | null;
  local: string | null;
  descricao: string | null;
  banner_url: string | null;
}

export const publicApi = {
  programacao: () => apiFetch<ItemProgramacao[]>('/public/programacao'),
  eventos: () => apiFetch<EventoPublico[]>('/public/eventos'),
};
