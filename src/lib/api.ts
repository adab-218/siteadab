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
  const metodo = (options.method ?? 'GET').toUpperCase();
  // Mod_Security (HostGator) responde 406 HTML em POST/PUT sem body —
  // ações sem payload (desistir, gerar-aulas) enviam JSON vazio.
  if (options.body === undefined && ['POST', 'PUT', 'PATCH'].includes(metodo)) {
    options.body = '{}';
  }
  const headers = new Headers(options.headers);
  // FormData: NÃO setar Content-Type — o browser gera
  // "multipart/form-data; boundary=..." e sem o boundary o PHP
  // não popula $_FILES (uploads).
  if (!headers.has('Content-Type') && options.body && !(options.body instanceof FormData)) {
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

/** Upload multipart (foto/banner) — FormData define o boundary sozinho. */
export function apiUpload<T>(path: string, arquivo: File): Promise<T> {
  const fd = new FormData();
  fd.append('arquivo', arquivo);
  return apiFetch<T>(path, { method: 'POST', body: fd });
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
  foto_url?: string | null;
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
  enviarFoto: (id: number, arquivo: File) =>
    apiUpload<MembroDetalhe>(`/membros/${id}/foto`, arquivo),
  removerFoto: (id: number) =>
    apiFetch<MembroDetalhe>(`/membros/${id}/foto`, { method: 'DELETE' }),
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
  criar: (dados: { nome: string; descricao?: string; lider_id?: number | null }) =>
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
  total_nomeados?: number;
}

export interface CultoPresenca {
  pessoa_id: number;
  nome_completo: string;
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

  // Presença nominal (M3)
  presencas: (id: number) => apiFetch<CultoPresenca[]>(`/cultos/${id}/presencas`),
  adicionarPresenca: (id: number, pessoa_id: number) =>
    apiFetch<CultoPresenca[]>(`/cultos/${id}/presencas`, {
      method: 'POST',
      body: JSON.stringify({ pessoa_id }),
    }),
  removerPresenca: (id: number, pessoa_id: number) =>
    apiFetch<CultoPresenca[]>(`/cultos/${id}/presencas/${pessoa_id}`, { method: 'DELETE' }),
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
  enviarBanner: (id: number, arquivo: File) =>
    apiUpload<Evento>(`/eventos/${id}/banner`, arquivo),
  removerBanner: (id: number) =>
    apiFetch<Evento>(`/eventos/${id}/banner`, { method: 'DELETE' }),

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

// ---------------------------------------------------------------------
// Ações (M5)

export type AcaoTipo = 'social' | 'evangelistica' | 'outra';
export type AcaoStatus = 'planejada' | 'em_andamento' | 'realizada' | 'cancelada';
export type AcaoPapel = 'coordenador' | 'voluntario';

export interface Acao {
  id: number;
  nome: string;
  tipo: AcaoTipo;
  data_inicio: string | null;
  data_fim: string | null;
  local: string | null;
  objetivo: string | null;
  resultado: string | null;
  departamento_id: number | null;
  departamento_nome?: string | null;
  status: AcaoStatus;
  total_voluntarios?: number;
  total_beneficiarios?: number;
  voluntarios?: AcaoVoluntario[];
  beneficiarios?: AcaoBeneficiario[];
}

export interface AcaoVoluntario {
  pessoa_id: number;
  nome_completo: string;
  papel: AcaoPapel;
}

export interface AcaoBeneficiario {
  id: number;
  nome: string;
  contato: string | null;
  observacao: string | null;
}

export interface AcaoPayload {
  nome: string;
  tipo: AcaoTipo;
  data_inicio?: string | null;
  data_fim?: string | null;
  local?: string | null;
  objetivo?: string | null;
  resultado?: string | null;
  departamento_id?: number | null;
  status?: AcaoStatus;
}

export const acoesApi = {
  listar(params: { de?: string; ate?: string; tipo?: string; status?: string; departamento_id?: number; pagina?: number; por_pagina?: number } = {}) {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') {
        qs.set(k, String(v));
      }
    }
    return apiFetch<RespostaPaginada<Acao>>(`/acoes?${qs}`);
  },
  ver: (id: number) => apiFetch<Acao>(`/acoes/${id}`),
  criar: (dados: AcaoPayload) =>
    apiFetch<Acao>('/acoes', { method: 'POST', body: JSON.stringify(dados) }),
  atualizar: (id: number, dados: Partial<AcaoPayload>) =>
    apiFetch<Acao>(`/acoes/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  remover: (id: number) =>
    apiFetch<{ ok: true }>(`/acoes/${id}`, { method: 'DELETE' }),

  vincularVoluntario: (id: number, pessoa_id: number, papel: AcaoPapel = 'voluntario') =>
    apiFetch<AcaoVoluntario[]>(`/acoes/${id}/voluntarios`, {
      method: 'POST',
      body: JSON.stringify({ pessoa_id, papel }),
    }),
  desvincularVoluntario: (id: number, pessoa_id: number) =>
    apiFetch<AcaoVoluntario[]>(`/acoes/${id}/voluntarios/${pessoa_id}`, { method: 'DELETE' }),

  criarBeneficiario: (id: number, dados: { nome: string; contato?: string; observacao?: string }) =>
    apiFetch<AcaoBeneficiario[]>(`/acoes/${id}/beneficiarios`, {
      method: 'POST',
      body: JSON.stringify(dados),
    }),
  removerBeneficiario: (id: number, bid: number) =>
    apiFetch<AcaoBeneficiario[]>(`/acoes/${id}/beneficiarios/${bid}`, { method: 'DELETE' }),
};

// ---------------------------------------------------------------------
// EBD (M6)

export interface EbdClasse {
  id: number;
  nome: string;
  faixa_etaria: string | null;
  sala: string | null;
  ativo: number;
  total_professores?: number;
  total_alunos?: number;
}

export interface EbdProfessor {
  id: number;
  pessoa_id: number;
  nome_completo: string;
  papel: 'titular' | 'auxiliar' | 'substituto';
  ativo: number;
}

export type EbdStatusAluno = 'matriculado' | 'transferido' | 'desistente';

export interface EbdAluno {
  id: number;
  classe_id: number;
  classe_nome: string;
  pessoa_id: number;
  nome_completo: string;
  data_matricula: string;
  data_desistencia: string | null;
  status: EbdStatusAluno;
}

export interface EbdPeriodo {
  id: number;
  ano: number;
  trimestre: number;
  tema_revista: string | null;
  data_inicio: string;
  data_fim: string;
  total_aulas?: number;
}

export interface EbdAula {
  id: number;
  periodo_id: number;
  numero_licao: number;
  titulo_licao: string | null;
  data: string;
  texto_aureo: string | null;
  leitura_biblica: string | null;
}

export interface EbdChamadaItem {
  aluno_id: number;
  nome_completo: string;
  presente: number | null;
  trouxe_biblia: number | null;
  trouxe_revista: number | null;
  oferta: string | null;
  visitante: number | null;
  registrado: number;
}

export interface EbdChamada {
  aula: EbdAula;
  chamada: EbdChamadaItem[];
}

export interface EbdFreqResumo {
  classe_id: number;
  classe_nome: string;
  periodo_id: number;
  aula_id: number;
  data: string;
  total_registros: number;
  presentes: number;
  biblias: number;
  revistas: number;
  ofertas: string;
  visitantes: number;
}

export interface EbdFaltoso {
  aluno_id: number;
  nome_completo: string;
  classe_nome: string;
  ultima_presenca: string | null;
}

export const ebdApi = {
  classes: (apenasAtivas?: boolean) =>
    apiFetch<EbdClasse[]>(`/ebd/classes${apenasAtivas !== undefined ? `?ativo=${apenasAtivas}` : ''}`),
  criarClasse: (dados: { nome: string; faixa_etaria?: string; sala?: string }) =>
    apiFetch<EbdClasse>('/ebd/classes', { method: 'POST', body: JSON.stringify(dados) }),
  atualizarClasse: (id: number, dados: { nome?: string; faixa_etaria?: string | null; sala?: string | null; ativo?: boolean }) =>
    apiFetch<EbdClasse>(`/ebd/classes/${id}`, { method: 'PUT', body: JSON.stringify(dados) }),
  professores: (classeId: number) =>
    apiFetch<EbdProfessor[]>(`/ebd/classes/${classeId}/professores`),
  vincularProfessor: (classeId: number, pessoa_id: number, papel: EbdProfessor['papel'] = 'auxiliar') =>
    apiFetch<EbdProfessor[]>(`/ebd/classes/${classeId}/professores`, {
      method: 'POST',
      body: JSON.stringify({ pessoa_id, papel }),
    }),
  desvincularProfessor: (classeId: number, pid: number) =>
    apiFetch<EbdProfessor[]>(`/ebd/classes/${classeId}/professores/${pid}`, { method: 'DELETE' }),

  alunos: (params: { classe_id?: number; status?: EbdStatusAluno; q?: string } = {}) => {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') {
        qs.set(k, String(v));
      }
    }
    return apiFetch<EbdAluno[]>(`/ebd/alunos?${qs}`);
  },
  matricular: (pessoa_id: number, classe_id: number, observacao?: string) =>
    apiFetch<EbdAluno>('/ebd/alunos', {
      method: 'POST',
      body: JSON.stringify({ pessoa_id, classe_id, observacao }),
    }),
  transferir: (alunoId: number, classe_id: number) =>
    apiFetch<EbdAluno>(`/ebd/alunos/${alunoId}/transferir`, {
      method: 'POST',
      body: JSON.stringify({ classe_id }),
    }),
  desistir: (alunoId: number) =>
    apiFetch<{ ok: true }>(`/ebd/alunos/${alunoId}/desistir`, { method: 'POST' }),

  periodos: () => apiFetch<EbdPeriodo[]>('/ebd/periodos'),
  criarPeriodo: (dados: { ano: number; trimestre: number; tema_revista?: string; data_inicio: string; data_fim: string }) =>
    apiFetch<EbdPeriodo>('/ebd/periodos', { method: 'POST', body: JSON.stringify(dados) }),
  aulas: (periodoId: number) => apiFetch<EbdAula[]>(`/ebd/periodos/${periodoId}/aulas`),
  criarAula: (periodoId: number, dados: { data: string; titulo_licao?: string; texto_aureo?: string; leitura_biblica?: string }) =>
    apiFetch<EbdAula>(`/ebd/periodos/${periodoId}/aulas`, { method: 'POST', body: JSON.stringify(dados) }),
  gerarAulas: (periodoId: number) =>
    apiFetch<{ criadas: number; aulas: EbdAula[] }>(`/ebd/periodos/${periodoId}/gerar-aulas`, { method: 'POST' }),

  chamada: (aulaId: number, classeId: number) =>
    apiFetch<EbdChamada>(`/ebd/aulas/${aulaId}/chamada?classe_id=${classeId}`),
  salvarChamada: (aulaId: number, classe_id: number, frequencias: Partial<EbdChamadaItem>[]) =>
    apiFetch<{ registrados: number; chamada: EbdChamadaItem[] }>(`/ebd/aulas/${aulaId}/chamada`, {
      method: 'PUT',
      body: JSON.stringify({ classe_id, frequencias }),
    }),

  relatorioFrequencia: (classe_id?: number, periodo_id?: number) => {
    const qs = new URLSearchParams();
    if (classe_id) qs.set('classe_id', String(classe_id));
    if (periodo_id) qs.set('periodo_id', String(periodo_id));
    return apiFetch<EbdFreqResumo[]>(`/ebd/relatorios/frequencia?${qs}`);
  },
  relatorioFaltosos: (semanas = 4) =>
    apiFetch<EbdFaltoso[]>(`/ebd/relatorios/faltosos?semanas=${semanas}`),
};

// ---------------------------------------------------------------------
// M7 — Sorteios beneficentes (doc 13)

export type RifaStatus = 'rascunho' | 'ativo' | 'encerrado' | 'sorteado' | 'cancelado';
export type RifaMetodo = 'sistema' | 'federal';
export type RifaStatusPag = 'pendente' | 'pago' | 'gratuito' | 'expirado' | 'falhou';

export interface Rifa {
  id: number;
  titulo: string;
  slug: string;
  descricao: string | null;
  regulamento: string | null;
  capa_url: string | null;
  valor_numero: number;
  total_numeros: number;
  max_por_pessoa: number;
  data_sorteio: string | null;
  metodo: RifaMetodo;
  status: RifaStatus;
  pix_instrucoes: string | null;
  seed: string | null;
  prova_hash: string | null;
  federal_numeros: string | null;
  cancelado_motivo: string | null;
  bilhetes_pagos?: number;
  bilhetes_pendentes?: number;
  total_premios?: number;
}

export interface RifaPremio {
  id: number;
  rifa_id: number;
  ordem: number;
  titulo: string;
  descricao: string | null;
  valor_estimado: number | null;
  imagem_url: string | null;
  departamento_id: number;
  departamento_nome?: string;
  bilhete_vencedor_id: number | null;
  bilhete_vencedor_numero?: number | null;
  vencedor_nome?: string | null;
}

export interface RifaReparticao {
  id: number;
  destino: string;
  tipo: 'livre' | 'departamentos';
  percentual: number;
}

export interface RifaDivulgador {
  id: number;
  pessoa_id: number;
  nome_completo?: string;
  token: string;
  ativo: number;
  vendas?: number;
  vendas_pagas?: number;
}

export interface RifaBilhete {
  id: number;
  numero: number | null;
  nome: string;
  telefone: string;
  email: string | null;
  status_pag: RifaStatusPag;
  divulgador_id: number | null;
  divulgador_nome?: string | null;
  departamento_nome?: string | null;
  expira_em: string | null;
  pago_em: string | null;
  created_at: string;
}

export interface RifaDetalhe extends Rifa {
  premios: RifaPremio[];
  reparticao: RifaReparticao[];
  divulgadores: RifaDivulgador[];
}

export interface RifaPayload {
  titulo: string;
  descricao?: string;
  regulamento?: string;
  pix_instrucoes?: string;
  valor_numero: number;
  total_numeros: number;
  max_por_pessoa?: number;
  data_sorteio?: string | null;
  metodo?: RifaMetodo;
}

export interface RifaRankingItem {
  divulgador_id: number;
  nome_completo: string;
  departamento_id: number;
  departamento_nome: string;
  reservas: number;
  pagos: number;
  pendentes: number;
  arrecadado: number;
}

export interface RifaExtrato {
  valor_numero: number;
  bilhetes: Record<string, number>;
  arrecadado: number;
  destinos: {
    destino: string;
    tipo: 'livre' | 'departamentos';
    percentual: number;
    valor: number;
    rateio?: { departamento_id: number; departamento_nome: string; bilhetes_pagos: number; valor: number }[];
  }[];
}

export const rifasApi = {
  listar: (params: { status?: string; q?: string; pagina?: number } = {}) => {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') qs.set(k, String(v));
    }
    return apiFetch<RespostaPaginada<Rifa>>(`/rifas?${qs}`);
  },
  ver: (id: number) => apiFetch<RifaDetalhe>(`/rifas/${id}`),
  criar: (p: RifaPayload) => apiFetch<Rifa>('/rifas', { method: 'POST', body: JSON.stringify(p) }),
  atualizar: (id: number, p: Partial<RifaPayload>) =>
    apiFetch<Rifa>(`/rifas/${id}`, { method: 'PUT', body: JSON.stringify(p) }),
  remover: (id: number) => apiFetch<{ ok: true }>(`/rifas/${id}`, { method: 'DELETE' }),
  ativar: (id: number) => apiFetch<Rifa>(`/rifas/${id}/ativar`, { method: 'POST' }),
  encerrar: (id: number) => apiFetch<Rifa>(`/rifas/${id}/encerrar`, { method: 'POST' }),
  cancelar: (id: number, motivo: string) =>
    apiFetch<Rifa>(`/rifas/${id}/cancelar`, { method: 'POST', body: JSON.stringify({ motivo }) }),
  sortear: (id: number, p: { seed?: string; federal_numeros?: string }) =>
    apiFetch<{ rifa: Rifa; resultado: { vencedores: number[]; seed: string; prova: string } }>(
      `/rifas/${id}/sortear`, { method: 'POST', body: JSON.stringify(p) }),
  criarPremio: (id: number, p: { ordem?: number; titulo: string; descricao?: string; valor_estimado?: number | null; departamento_id: number }) =>
    apiFetch<RifaPremio>(`/rifas/${id}/premios`, { method: 'POST', body: JSON.stringify(p) }),
  atualizarPremio: (id: number, premioId: number, p: Partial<RifaPremio>) =>
    apiFetch<RifaPremio>(`/rifas/${id}/premios/${premioId}`, { method: 'PUT', body: JSON.stringify(p) }),
  removerPremio: (id: number, premioId: number) =>
    apiFetch<{ ok: true }>(`/rifas/${id}/premios/${premioId}`, { method: 'DELETE' }),
  salvarReparticao: (id: number, linhas: { destino: string; tipo: 'livre' | 'departamentos'; percentual: number }[]) =>
    apiFetch<RifaReparticao[]>(`/rifas/${id}/reparticao`, { method: 'PUT', body: JSON.stringify({ linhas }) }),
  divulgadores: (id: number) => apiFetch<RifaDivulgador[]>(`/rifas/${id}/divulgadores`),
  criarDivulgador: (id: number, pessoa_id: number) =>
    apiFetch<RifaDivulgador>(`/rifas/${id}/divulgadores`, { method: 'POST', body: JSON.stringify({ pessoa_id }) }),
  desativarDivulgador: (id: number, divulgadorId: number) =>
    apiFetch<RifaDivulgador[]>(`/rifas/${id}/divulgadores/${divulgadorId}`, { method: 'DELETE' }),
  bilhetes: (id: number, params: { q?: string; status_pag?: string; divulgador_id?: number; departamento_id?: number; pagina?: number } = {}) => {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== '') qs.set(k, String(v));
    }
    return apiFetch<RespostaPaginada<RifaBilhete>>(`/rifas/${id}/bilhetes?${qs}`);
  },
  pagarBilhete: (id: number, bilheteId: number) =>
    apiFetch<RifaBilhete>(`/rifas/${id}/bilhetes/${bilheteId}/pagar`, { method: 'PATCH' }),
  estornarBilhete: (id: number, bilheteId: number) =>
    apiFetch<RifaBilhete>(`/rifas/${id}/bilhetes/${bilheteId}/estornar`, { method: 'PATCH' }),
  ranking: (id: number) => apiFetch<RifaRankingItem[]>(`/rifas/${id}/ranking`),
  extrato: (id: number) => apiFetch<RifaExtrato>(`/rifas/${id}/reparticao-extrato`),
};

// ---- público (sem auth) ----

export interface SorteioPublico {
  slug: string;
  titulo: string;
  descricao: string | null;
  capa_url: string | null;
  valor_numero: number;
  total_numeros: number;
  max_por_pessoa: number;
  data_sorteio: string | null;
  metodo: RifaMetodo;
  status: RifaStatus;
  pix_instrucoes: string | null;
  prova_hash: string | null;
  federal_numeros: string | null;
  numeros_reservados: number;
  total_premios: number;
  premios?: {
    ordem: number;
    titulo: string;
    descricao: string | null;
    valor_estimado: number | null;
    imagem_url: string | null;
    departamento_nome: string;
    numero_vencedor: number | null;
  }[];
}

export const sorteioPublicoApi = {
  listar: () => apiFetch<SorteioPublico[]>('/public/sorteios'),
  ver: (slug: string) => apiFetch<SorteioPublico>(`/public/sorteios/${slug}`),
  reservar: (slug: string, p: { nome: string; telefone: string; email?: string; quantidade: number; v?: string; website?: string }) =>
    apiFetch<{ numeros: number[]; total: number; expira_em: number }>(
      `/public/sorteios/${slug}/reservar`, { method: 'POST', body: JSON.stringify(p) }),
  meusBilhetes: (slug: string, telefone: string) =>
    apiFetch<{ numero: number; status_pag: RifaStatusPag; vencedor: number }[]>(
      `/public/sorteios/${slug}/bilhetes?telefone=${encodeURIComponent(telefone)}`),
};
