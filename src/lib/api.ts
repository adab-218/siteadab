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

/**
 * Contrato de listas paginadas (ver dev/docs/06):
 *   GET /recurso?pagina=2&por_pagina=20&busca=x
 */
export interface RespostaPaginada<T> {
  itens: T[];
  pagina: number;
  por_pagina: number;
  total: number;
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

// ---------------------------------------------------------------------
// Membros (M1)

export type StatusMembro = 'ativo' | 'afastado' | 'transferido' | 'falecido' | 'disciplinado';

export interface MembroListItem {
  pessoa_id: number;
  nome_completo: string;
  telefone: string | null;
  email: string | null;
  status: StatusMembro;
  data_status: string | null;
  congregacao: string | null;
  cargo_nome: string | null;
}

export interface PessoaPayload {
  nome_completo: string;
  nome_social?: string | null;
  data_nascimento?: string | null;
  sexo?: 'M' | 'F' | null;
  estado_civil?: string | null;
  conjuge_id?: number | null;
  cpf?: string | null;
  telefone?: string | null;
  email?: string | null;
  cep?: string | null;
  logradouro?: string | null;
  numero?: string | null;
  complemento?: string | null;
  bairro?: string | null;
  cidade?: string | null;
  uf?: string | null;
  observacoes?: string | null;
}

export interface MembroPayload {
  numero_carteirinha?: string | null;
  data_conversao?: string | null;
  data_batismo?: string | null;
  batizado_espirito_santo?: boolean;
  congregacao?: string | null;
  procedencia?: string | null;
  cargo_id?: number | null;
}

export interface MembroDetalhe extends PessoaPayload {
  pessoa_id: number;
  status: StatusMembro;
  data_status: string | null;
  cargo_nome: string | null;
  numero_carteirinha: string | null;
  data_conversao: string | null;
  data_batismo: string | null;
  batizado_espirito_santo: number;
  congregacao: string | null;
  procedencia: string | null;
  cargo_id: number | null;
  departamentos?: { departamento_id: number; nome: string; funcao: string | null; data_inicio: string | null }[];
}

export const membrosApi = {
  listar(params: { q?: string; status?: string; departamento_id?: number; pagina?: number; por_pagina?: number } = {}) {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') {
        qs.set(k, String(v));
      }
    }
    return apiFetch<RespostaPaginada<MembroListItem>>(`/membros?${qs}`);
  },
  ver: (id: number) => apiFetch<MembroDetalhe>(`/membros/${id}`),
  criar: (pessoa: PessoaPayload, membro: MembroPayload) =>
    apiFetch<MembroDetalhe>('/membros', {
      method: 'POST',
      body: JSON.stringify({ pessoa, membro }),
    }),
  atualizar: (id: number, pessoa: PessoaPayload, membro: MembroPayload) =>
    apiFetch<MembroDetalhe>(`/membros/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ pessoa, membro }),
    }),
  mudarStatus: (id: number, status: StatusMembro, data_status?: string) =>
    apiFetch<MembroDetalhe>(`/membros/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status, data_status }),
    }),
  remover: (id: number) =>
    apiFetch<{ ok: true }>(`/membros/${id}`, { method: 'DELETE' }),
  aniversariantes: (mes?: number) =>
    apiFetch<{ mes: number; itens: Aniversariante[] }>(
      `/membros/aniversariantes${mes ? `?mes=${mes}` : ''}`
    ),
};

export interface Aniversariante {
  pessoa_id: number;
  nome_completo: string;
  data_nascimento: string;
  dia: number;
}

// ---------------------------------------------------------------------
// Departamentos (M2) e catálogos

export interface Cargo {
  id: number;
  nome: string;
  ordem: number;
}

export interface Departamento {
  id: number;
  nome: string;
  descricao: string | null;
  lider_id: number | null;
  lider_nome: string | null;
  ativo: number;
  total_membros: number;
  membros?: DepartamentoMembro[];
  vinculo?: 'inserido' | 'reativado';
}

export interface DepartamentoMembro {
  pessoa_id: number;
  nome_completo: string;
  funcao: string | null;
  data_inicio: string | null;
}

export const cargosApi = {
  listar: () => apiFetch<Cargo[]>('/cargos'),
};

export const departamentosApi = {
  listar: (apenasAtivos?: boolean) =>
    apiFetch<Departamento[]>(`/departamentos${apenasAtivos !== undefined ? `?ativo=${apenasAtivos}` : ''}`),
  ver: (id: number) => apiFetch<Departamento>(`/departamentos/${id}`),
  criar: (dados: { nome: string; descricao?: string; lider_id?: number }) =>
    apiFetch<Departamento>('/departamentos', { method: 'POST', body: JSON.stringify(dados) }),
  atualizar: (id: number, dados: { nome?: string; descricao?: string; lider_id?: number | null; ativo?: boolean }) =>
    apiFetch<Departamento>(`/departamentos/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  remover: (id: number) =>
    apiFetch<{ ok: true }>(`/departamentos/${id}`, { method: 'DELETE' }),
  adicionarMembro: (id: number, pessoa_id: number, funcao?: string, data_inicio?: string) =>
    apiFetch<Departamento>(`/departamentos/${id}/membros`, {
      method: 'POST',
      body: JSON.stringify({ pessoa_id, funcao, data_inicio }),
    }),
  removerMembro: (id: number, pessoa_id: number) =>
    apiFetch<{ ok: true }>(`/departamentos/${id}/membros/${pessoa_id}`, { method: 'DELETE' }),
};

// ---------------------------------------------------------------------
// Usuários (T2.2 — somente admin)

export interface UsuarioListItem {
  id: number;
  pessoa_id: number;
  nome_completo: string;
  email: string;
  perfil: Usuario['perfil'];
  ativo: number;
  ultimo_login_em: string | null;
}

export const usuariosApi = {
  listar: (q?: string) =>
    apiFetch<UsuarioListItem[]>(`/usuarios${q ? `?q=${encodeURIComponent(q)}` : ''}`),
  criar: (dados: { pessoa_id: number; email: string; senha: string; perfil: Usuario['perfil'] }) =>
    apiFetch<UsuarioListItem>('/usuarios', { method: 'POST', body: JSON.stringify(dados) }),
  atualizar: (id: number, dados: { email?: string; perfil?: Usuario['perfil']; ativo?: boolean }) =>
    apiFetch<UsuarioListItem>(`/usuarios/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  senha: (id: number, senha: string) =>
    apiFetch<{ ok: true }>(`/usuarios/${id}/senha`, {
      method: 'PATCH',
      body: JSON.stringify({ senha }),
    }),
  remover: (id: number) =>
    apiFetch<{ ok: true }>(`/usuarios/${id}`, { method: 'DELETE' }),
};

// ---------------------------------------------------------------------
// Cultos (M3)

export interface TipoCulto {
  id: number;
  nome: string;
  dia_semana_sugerido: number | null;
  horario_padrao: string | null;
  ativo: number;
}

export interface Culto {
  id: number;
  tipo_culto_id: number;
  tipo_nome: string;
  data: string;
  horario_inicio: string | null;
  dirigente_id: number | null;
  dirigente_nome: string | null;
  pregador_id: number | null;
  pregador_nome: string | null;
  tema: string | null;
  total_presentes: number | null;
  total_visitantes: number | null;
  total_decisoes: number | null;
  observacoes: string | null;
}

export interface CultoPayload {
  tipo_culto_id: number;
  data: string;
  horario_inicio?: string | null;
  dirigente_id?: number | null;
  pregador_id?: number | null;
  tema?: string | null;
  total_presentes?: number | null;
  total_visitantes?: number | null;
  total_decisoes?: number | null;
  observacoes?: string | null;
}

export const tiposCultoApi = {
  listar: (apenasAtivos?: boolean) =>
    apiFetch<TipoCulto[]>(`/tipos-culto${apenasAtivos !== undefined ? `?ativo=${apenasAtivos}` : ''}`),
  criar: (dados: { nome: string; dia_semana_sugerido?: number | null; horario_padrao?: string | null }) =>
    apiFetch<TipoCulto>('/tipos-culto', { method: 'POST', body: JSON.stringify(dados) }),
  atualizar: (id: number, dados: { nome?: string; dia_semana_sugerido?: number | null; horario_padrao?: string | null; ativo?: boolean }) =>
    apiFetch<TipoCulto>(`/tipos-culto/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  desativar: (id: number) =>
    apiFetch<{ ok: true }>(`/tipos-culto/${id}`, { method: 'DELETE' }),
};

export const cultosApi = {
  listar(params: { de?: string; ate?: string; tipo_culto_id?: number; pagina?: number; por_pagina?: number } = {}) {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') {
        qs.set(k, String(v));
      }
    }
    return apiFetch<RespostaPaginada<Culto>>(`/cultos?${qs}`);
  },
  ver: (id: number) => apiFetch<Culto>(`/cultos/${id}`),
  criar: (dados: CultoPayload) =>
    apiFetch<Culto>('/cultos', { method: 'POST', body: JSON.stringify(dados) }),
  atualizar: (id: number, dados: Partial<CultoPayload>) =>
    apiFetch<Culto>(`/cultos/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  remover: (id: number) =>
    apiFetch<{ ok: true }>(`/cultos/${id}`, { method: 'DELETE' }),
};

// ---------------------------------------------------------------------
// Eventos (M4)

export type EventoTipo = 'congresso' | 'festividade' | 'culto_especial' | 'data_comemorativa' | 'outro';
export type EventoStatus = 'planejado' | 'confirmado' | 'realizado' | 'cancelado';

export interface EventoAtividade {
  id: number;
  evento_id: number;
  data: string;
  horario_inicio: string | null;
  horario_fim: string | null;
  titulo: string;
  responsavel_id: number | null;
  responsavel_nome?: string | null;
  descricao: string | null;
}

export interface Evento {
  id: number;
  nome: string;
  tipo: EventoTipo;
  data_inicio: string;
  data_fim: string | null;
  local: string | null;
  descricao: string | null;
  banner_url: string | null;
  publico: number;
  status: EventoStatus;
  total_atividades?: number;
  atividades?: EventoAtividade[];
}

export interface EventoPayload {
  nome: string;
  tipo: EventoTipo;
  data_inicio: string;
  data_fim?: string | null;
  local?: string | null;
  descricao?: string | null;
  banner_url?: string | null;
  publico?: boolean;
  status?: EventoStatus;
}

export const eventosApi = {
  listar(params: { de?: string; ate?: string; tipo?: string; status?: string; publico?: boolean; pagina?: number; por_pagina?: number } = {}) {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') {
        qs.set(k, String(v));
      }
    }
    return apiFetch<RespostaPaginada<Evento>>(`/eventos?${qs}`);
  },
  ver: (id: number) => apiFetch<Evento>(`/eventos/${id}`),
  criar: (dados: EventoPayload) =>
    apiFetch<Evento>('/eventos', { method: 'POST', body: JSON.stringify(dados) }),
  atualizar: (id: number, dados: Partial<EventoPayload>) =>
    apiFetch<Evento>(`/eventos/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  remover: (id: number) =>
    apiFetch<{ ok: true }>(`/eventos/${id}`, { method: 'DELETE' }),

  criarAtividade: (id: number, dados: Partial<EventoAtividade>) =>
    apiFetch<EventoAtividade>(`/eventos/${id}/atividades`, {
      method: 'POST',
      body: JSON.stringify(dados),
    }),
  atualizarAtividade: (id: number, aid: number, dados: Partial<EventoAtividade>) =>
    apiFetch<EventoAtividade>(`/eventos/${id}/atividades/${aid}`, {
      method: 'PUT',
      body: JSON.stringify(dados),
    }),
  removerAtividade: (id: number, aid: number) =>
    apiFetch<{ ok: true }>(`/eventos/${id}/atividades/${aid}`, { method: 'DELETE' }),
};
