# 06 — API e Integrações

## Estilo

REST JSON sob `/api`, servido pelo Slim 4 (`api/index.php`).
Convenções:

- Respostas: `{ "data": ... }` ou `{ "error": { "code", "message" } }`
- Auth: cookie de sessão; mutações exigem header `X-CSRF-Token`
- HostGator: Mod_Security responde 406 HTML em POST **sem body** — o
  frontend sempre envia `{}` em ações sem payload (`apiFetch` padrão)
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
| POST | `/api/membros` `{pessoa: {...}, membro: {...}}` (envelope — payload plano → 400) | admin, secretaria | ✅ |
| PUT | `/api/membros/{id}` `{pessoa, membro}` | admin, secretaria | ✅ |
| PATCH | `/api/membros/{id}/status` `{status, data_status?}` — ao sair de `ativo` desvincula deptos/EBD/usuário (D1) | admin, secretaria | ✅ |
| DELETE | `/api/membros/{id}` soft delete — aplica D1 também (deptos, EBD, usuário) | só admin | ✅ |
| POST | `/api/membros/{id}/foto` — multipart campo `arquivo` (JPG/PNG/WebP ≤ 2 MB) | admin, secretaria | ✅ |
| DELETE | `/api/membros/{id}/foto` | admin, secretaria | ✅ |
| GET | `/api/membros/aniversariantes?mes=` | painel | pendente |
| POST | `/api/membros/importar` (CSV) | admin, secretaria | pendente (D9) |
| GET | `/api/membros/exportar.csv` | admin, secretaria | pendente |

### Departamentos e catálogos (M2)

Leitura: todo perfil autenticado; `lider_departamento` recebe só os
departamentos que lidera (D5). Vínculo só aceita membros (D6) — um vínculo
encerrado é reativado (`data_fim=NULL`) por causa de
`uq_md_pessoa_departamento`.

| Método | Rota | Perfil | Status |
|--------|------|--------|--------|
| GET | `/api/cargos` | autenticado | ✅ |
| GET | `/api/departamentos?ativo=` | autenticado (lider: só os seus) | ✅ |
| GET | `/api/departamentos/{id}` (inclui `membros[]`) | autenticado (lider: só se lidera) | ✅ |
| POST | `/api/departamentos` `{nome, descricao?, lider_id?}` | admin, secretaria | ✅ |
| PUT | `/api/departamentos/{id}` | admin, secretaria | ✅ |
| DELETE | `/api/departamentos/{id}` — desativa; 422 `VINCULOS_ATIVOS` se tem membros | admin, secretaria | ✅ |
| POST | `/api/departamentos/{id}/membros` `{pessoa_id, funcao?, data_inicio?}` | admin, secretaria, líder do depto | ✅ |
| DELETE | `/api/departamentos/{id}/membros/{pessoa_id}` — encerra vínculo (`data_fim`) | admin, secretaria, líder do depto | ✅ |
| GET | `/api/membros/aniversariantes?mes=` | autenticado | ✅ |

### Usuários (T2.2 — todo o grupo é só-admin)

Admin não pode rebaixar o próprio perfil nem desativar a própria conta
(422 `PROPRIO_USUARIO`). Uma conta por pessoa (`uq_usuarios_pessoa` →
422 `PESSOA_COM_USUARIO`), e-mail único (422 `EMAIL_DUPLICADO`).

| Método | Rota | Status |
|--------|------|--------|
| GET | `/api/usuarios?q=` | ✅ |
| POST | `/api/usuarios` `{pessoa_id, email, senha≥10, perfil}` | ✅ |
| PUT | `/api/usuarios/{id}` `{email?, perfil?, ativo?}` — `senha` no body → 400 (use PATCH) | ✅ |
| PATCH | `/api/usuarios/{id}/senha` `{senha≥10}` — invalida reset pendente | ✅ |
| DELETE | `/api/usuarios/{id}` — desativa (`ativo=0`) | ✅ |

### Cultos (M3)

`uq_cultos_tipo_data`: um culto por tipo/dia (D11) → 422 `CULTO_DUPLICADO`.
Escritas de culto: secretaria/dirigente/admin; tipos: secretaria/admin.
Dados iniciais (D12) aplicados pela migration `0008` (INSERT IGNORE).

| Método | Rota | Perfil | Status |
|--------|------|--------|--------|
| GET | `/api/tipos-culto?ativo=` | autenticado | ✅ |
| POST | `/api/tipos-culto` `{nome, dia_semana_sugerido?, horario_padrao?}` | admin, secretaria | ✅ |
| PUT | `/api/tipos-culto/{id}` (inclui `ativo`) | admin, secretaria | ✅ |
| DELETE | `/api/tipos-culto/{id}` → `ativo=0` | admin, secretaria | ✅ |
| GET | `/api/cultos?de=&ate=&tipo_culto_id=&pagina=` | autenticado | ✅ |
| GET | `/api/cultos/{id}` | autenticado | ✅ |
| POST | `/api/cultos` | admin, secretaria, dirigente | ✅ |
| PUT | `/api/cultos/{id}` | admin, secretaria, dirigente | ✅ |
| DELETE | `/api/cultos/{id}` (hard delete; presenças em cascata) | admin, secretaria, dirigente | ✅ |
| GET | `/api/cultos/{id}/presencas` — lista nominal (`pessoa_id`, `nome`) | autenticado | ✅ |
| POST | `/api/cultos/{id}/presencas` `{pessoa_id}` — só membros ativos; `uq_culto_pessoa` → add **idempotente** (201 mesmo já existindo, por design — diferente de `/departamentos` que retorna 422) | admin, secretaria, dirigente | ✅ |
| DELETE | `/api/cultos/{id}/presencas/{pessoa_id}` | admin, secretaria, dirigente | ✅ |

### Eventos (M4)

`publico=1` + não-cancelado + dentro do período alimenta `/public/eventos`
e `v_programacao_publica` (WeeklySchedule) sem ação extra. Soft delete.
Escritas: admin, secretaria, dirigente.
`tipo`: `congresso|festividade|culto_especial|data_comemorativa|outro`;
`status`: `planejado|confirmado|realizado|cancelado`.

| Método | Rota | Status |
|--------|------|--------|
| GET | `/api/eventos?de=&ate=&tipo=&status=&publico=&pagina=` | ✅ |
| GET | `/api/eventos/{id}` — inclui `atividades[]` | ✅ |
| POST/PUT | `/api/eventos`, `/{id}` `{nome, tipo, data_inicio, data_fim?, local?, descricao?, banner_url?, publico?, status?}` | ✅ |
| DELETE | `/api/eventos/{id}` — soft delete | ✅ |
| POST | `/api/eventos/{id}/atividades` `{titulo, data, horario_inicio?, horario_fim?, responsavel_id?, descricao?}` | ✅ |
| PUT/DELETE | `/api/eventos/{id}/atividades/{aid}` | ✅ |
| POST | `/api/eventos/{id}/banner` — multipart campo `arquivo` (JPG/PNG/WebP ≤ 2 MB) | ✅ |
| DELETE | `/api/eventos/{id}/banner` | ✅ |

### Ações (M5)

Soft delete. Voluntário = pessoa cadastrada (`uq_acao_pessoa` → re-vínculo
promove papel, não duplica). Beneficiário = texto livre (externo, sem
vínculo a pessoa). Escritas: admin, secretaria, dirigente.

| Método | Rota | Status |
|--------|------|--------|
| GET | `/api/acoes?de=&ate=&tipo=&status=&departamento_id=&pagina=` | ✅ |
| GET | `/api/acoes/{id}` — inclui `voluntarios[]`, `beneficiarios[]` | ✅ |
| POST/PUT | `/api/acoes`, `/{id}` `{nome, tipo, data_inicio?, data_fim?, local?, objetivo?, resultado?, departamento_id?, status?}` | ✅ |
| DELETE | `/api/acoes/{id}` — soft delete | ✅ |
| POST | `/api/acoes/{id}/voluntarios` `{pessoa_id, papel}` — upsert | ✅ |
| DELETE | `/api/acoes/{id}/voluntarios/{pessoa_id}` | ✅ |
| POST | `/api/acoes/{id}/beneficiarios` `{nome, contato?, observacao?}` | ✅ |
| DELETE | `/api/acoes/{id}/beneficiarios/{bid}` | ✅ |

### EBD (M6)

Decisões: **D2** uma matrícula `matriculado` por aluno (422
`JA_MATRICULADO`; re-matrícula na mesma classe reativa a linha terminal).
**D3** só presença registrada (falta = ausência de linha); chamada por
`ebd_admin` ou `ebd_professor` que leciona na classe. **D4** períodos
`ano+trimestre` (422 `PERIODO_DUPLICADO`). **D8** `gerar-aulas` cria uma
aula por domingo do período (idempotente); `POST /aulas` cria aula
pontual/transferida em qualquer data (nº sequencial seguinte).

| Método | Rota | Perfil | Status |
|--------|------|--------|--------|
| GET | `/api/ebd/classes?ativo=` | autenticado | ✅ |
| POST/PUT | `/api/ebd/classes`, `/{id}` | ebd_admin, admin | ✅ |
| GET | `/api/ebd/classes/{id}/professores` | autenticado | ✅ |
| POST | `/api/ebd/classes/{id}/professores` `{pessoa_id, papel}` | ebd_admin, admin | ✅ |
| DELETE | `/api/ebd/classes/{id}/professores/{pid}` → `ativo=0` | ebd_admin, admin | ✅ |
| GET | `/api/ebd/alunos?classe_id=&status=&q=` | ebd_admin, admin, ebd_professor | ✅ |
| POST | `/api/ebd/alunos` `{pessoa_id, classe_id, observacao?}` | ebd_admin, admin | ✅ |
| POST | `/api/ebd/alunos/{id}/transferir` `{classe_id}` | ebd_admin, admin | ✅ |
| POST | `/api/ebd/alunos/{id}/desistir` | ebd_admin, admin | ✅ |
| GET | `/api/ebd/periodos` | autenticado | ✅ |
| POST | `/api/ebd/periodos` `{ano, trimestre, data_inicio, data_fim, tema_revista?}` | ebd_admin, admin | ✅ |
| GET | `/api/ebd/periodos/{id}/aulas` | autenticado | ✅ |
| POST | `/api/ebd/periodos/{id}/aulas` — aula pontual/transferida | ebd_admin, admin | ✅ |
| POST | `/api/ebd/periodos/{id}/gerar-aulas` — domingos | ebd_admin, admin | ✅ |
| GET | `/api/ebd/aulas/{id}/chamada?classe_id=` | ebd_admin ou professor da classe | ✅ |
| PUT | `/api/ebd/aulas/{id}/chamada` `{classe_id, frequencias[]}` — upsert | ebd_admin ou professor da classe | ✅ |
| GET | `/api/ebd/relatorios/frequencia?classe_id=&periodo_id=` | ebd_admin, admin, dirigente | ✅ |
| GET | `/api/ebd/relatorios/faltosos?semanas=` (1–12, default 4) | ebd_admin, admin | ✅ |

### M7 — Sorteios beneficentes (doc 13)
| Método | Rota | Quem | Status |
|--------|------|------|--------|
| GET/POST/PUT | `/api/rifas[/{id}]` | leitura autenticado; escrita secretaria, admin | ✅ |
| DELETE | `/api/rifas/{id}` — soft, só rascunho | secretaria, admin | ✅ |
| POST | `/api/rifas/{id}/ativar` — exige prêmio + regulamento + repartição Σ=100 | secretaria, admin | ✅ |
| POST | `/api/rifas/{id}/encerrar` `/cancelar{motivo}` | secretaria, admin | ✅ |
| POST | `/api/rifas/{id}/sortear` `{seed? | federal_numeros}` | secretaria, admin | ✅ |
| POST/PUT/DELETE | `/api/rifas/{id}/premios[/{pid}]` — depto doador | secretaria, admin | ✅ |
| PUT | `/api/rifas/{id}/reparticao` `{linhas[]}` — Σ=100 | secretaria, admin | ✅ |
| GET/POST/DELETE | `/api/rifas/{id}/divulgadores[/{did}]` — token por membro | leitura autenticado; escrita secretaria, admin, lider_departamento | ✅ |
| GET | `/api/rifas/{id}/bilhetes?q=&status_pag=&depto=&divulgador=` | autenticado | ✅ |
| PATCH | `/api/rifas/{id}/bilhetes/{bid}/pagar` `/estornar` | secretaria, admin | ✅ |
| GET | `/api/rifas/{id}/ranking` — vendas por membro/depto | autenticado | ✅ |
| GET | `/api/rifas/{id}/reparticao-extrato` — arrecadado × % | autenticado | ✅ |

### Público (site) — sem auth
| Método | Rota | Conteúdo |
|--------|------|----------|
| GET | `/api/public/programacao` | cultos + eventos `publico` futuros |
| GET | `/api/public/eventos` | festividades públicas |
| GET | `/api/public/sorteios` | rifas ativas/encerradas/sorteadas (view) |
| GET | `/api/public/sorteios/{slug}` | detalhe + prêmios + resultado (sem dados de compradores) |
| POST | `/api/public/sorteios/{slug}/reservar` | `{nome,telefone,quantidade,v?,website?}` — números sequenciais, expira 24h |
| GET | `/api/public/sorteios/{slug}/bilhetes?telefone=` | consulta LGPD-safe (número+status) |

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
