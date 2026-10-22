# 06 — API e Integrações

## Estilo

REST JSON sob `/api`, servido pelo Slim 4 (`api/index.php`).
Convenções:

- Respostas: `{ "data": ... }` ou `{ "error": { "code", "message" } }`
- Auth: cookie de sessão; mutações exigem header `X-CSRF-Token`
- Conteúdo pt-BR; datas `yyyy-mm-dd` na API, `dd/mm/aaaa` na UI
- Paginação: `?pagina=1&por_pagina=25` (máx. 50); busca: `?q=` —
  resposta de listagem sempre no envelope
  `{ "data": { "itens": [...], "pagina": 1, "por_pagina": 25, "total": 137 } }`
  (helpers: `Json::paginada()` / `Json::parametrosPaginacao()` no PHP,
  `RespostaPaginada<T>` em `src/lib/api.ts`)
- Erros: 400 validação, 401 não autenticado, 403 sem permissão,
  404 não encontrado, 422 regra de negócio

## Rotas

### Auth
| Método | Rota | Perfil |
|--------|------|--------|
| POST | `/api/auth/login` | público |
| POST | `/api/auth/logout` | autenticado |
| GET | `/api/auth/me` | autenticado |
| POST | `/api/auth/recuperar` | público |
| POST | `/api/auth/redefinir` | público (token) |

### Membros

Implementadas (M1): leitura aberta a todo perfil autenticado —
`lider_departamento` recebe escopo automático p/ seus departamentos (D5).

| Método | Rota | Perfil | Status |
|--------|------|--------|--------|
| GET | `/api/membros?q=&status=&cargo_id=&departamento_id=&pagina=&por_pagina=` | autenticado (lider: só seu depto) | ✅ |
| GET | `/api/membros/{id}` | autenticado (lider: membro do seu depto) | ✅ |
| POST | `/api/membros` `{pessoa, membro}` | admin, secretaria | ✅ |
| PUT | `/api/membros/{id}` `{pessoa, membro}` | admin, secretaria | ✅ |
| PATCH | `/api/membros/{id}/status` `{status, data_status?}` — ao sair de `ativo` desvincula deptos/EBD/usuário (D1) | admin, secretaria | ✅ |
| DELETE | `/api/membros/{id}` soft delete | só admin | ✅ |
| POST | `/api/membros/{id}/foto` | admin, secretaria | pendente (uploads) |
| GET | `/api/membros/aniversariantes?mes=` | painel | pendente |
| POST | `/api/membros/importar` (CSV) | admin, secretaria | pendente (D9) |
| GET | `/api/membros/exportar.csv` | admin, secretaria | pendente |
| GET/POST/PUT | `/api/departamentos[/...]` | admin, secretaria | pendente |

### Cultos
| Método | Rota | Perfil |
|--------|------|--------|
| GET/POST | `/api/cultos`, `/api/tipos-culto` | leitura painel / escrita secretaria+ |
| PUT | `/api/cultos/{id}` | admin, secretaria, dirigente |
| POST | `/api/cultos/{id}/presencas` | admin, secretaria |

### Eventos / Ações
| Método | Rota | Perfil |
|--------|------|--------|
| GET/POST/PUT | `/api/eventos[/...]` (+ `/atividades`) | conforme matriz |
| POST | `/api/eventos/{id}/banner` | admin, secretaria, dirigente |
| GET/POST/PUT | `/api/acoes[/...]` | conforme matriz |
| POST | `/api/acoes/{id}/voluntarios`, `/beneficiarios` | conforme matriz |

### EBD
| Método | Rota | Perfil |
|--------|------|--------|
| GET/POST/PUT | `/api/ebd/classes[/...]` | ebd_admin, admin |
| POST | `/api/ebd/classes/{id}/professores` | ebd_admin, admin |
| GET/POST | `/api/ebd/alunos` (matrícula) | ebd_admin, admin, ebd_professor (leitura) |
| POST | `/api/ebd/alunos/{id}/transferir`, `/desistir` | ebd_admin, admin |
| GET/POST | `/api/ebd/periodos`, `/{id}/aulas` | ebd_admin, admin |
| GET | `/api/ebd/aulas/{id}/chamada?classe_id=` | professor da classe |
| PUT | `/api/ebd/aulas/{id}/chamada` | professor da classe (upsert frequências) |
| GET | `/api/ebd/relatorios/frequencia?classe_id=&periodo_id=` | ebd_admin, admin, dirigente |
| GET | `/api/ebd/relatorios/faltosos?semanas=` | ebd_admin, admin |

### Público (site) — sem auth
| Método | Rota | Conteúdo |
|--------|------|----------|
| GET | `/api/public/programacao` | cultos + eventos `publico` futuros |
| GET | `/api/public/eventos` | festividades públicas |

### Migrations e operação
| Método | Rota | Proteção |
|--------|------|----------|
| POST | `/api/admin/migrate` | admin logado + token de deploy |

## Camadas no PHP (`api/`)

```
index.php               # bootstrap Slim + grupos de rotas + middlewares
src/Controllers/        # finos: validam input, chamam service
src/Services/           # regras de negócio
src/Repositories/       # PDO — única camada que fala SQL
src/Middleware/         # AuthMiddleware, PerfilMiddleware, CsrfMiddleware
```

Regras:
- Todo SQL em Repositories com prepared statements (PDO).
- Validação de entrada no controller (mesmas regras do schema zod do
  front — manter espelhadas).
- Nunca confiar em filtro aplicado só no front: escopo de dados sempre
  no repositório.

## Integrações externas

| Serviço | Uso |
|---------|-----|
| Google Maps / SoundCloud embed | site público (já existente) |
| SMTP (cPanel) | recuperação de senha (PHPMailer) |
| phpMyAdmin | admin manual do banco |
