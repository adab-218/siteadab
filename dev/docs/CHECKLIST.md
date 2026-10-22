# Checklist de Correções — Revisão da Equipe (out/2026)

Consolidação dos pareceres dos 10 agentes. Ordem: T0 (hotfixes com
exposição real hoje) → T1 (fundação) → T2 (módulos) → OPS (manual/cPanel)
→ DECISÕES (pendentes do cliente).

Legenda: `[x]` concluído · `[ ]` pendente · `[~]` parcial/decidido-adiar

## T0 — Correções quentes

### Backend PHP

- [x] T0.1 `AuthController`: não logar reset token no `error_log` (logar só o fato)
- [x] T0.2 `usuarios.reset_token` armazenado como hash SHA-256 (gerar/redefinir)
- [x] T0.3 Rate-limit em `POST /auth/recuperar` (reusar `login_tentativas`)
- [x] T0.4 Rate-limit adicional por IP no login (mitigar par email+IP)
- [x] T0.5 `login_tentativas`: e-mail como hash (LGPD) + purge de linhas > 1 dia
- [x] T0.6 Login: `password_verify` em hash dummy p/ paridade de timing
- [x] T0.7 `PublicController::eventos`: filtrar `status<>'cancelado' AND deleted_at IS NULL`
- [x] T0.8 `AuthMiddleware`: revalidar `usuarios.ativo` e refrescar perfil por request
- [x] T0.9 `index.php`: try/catch no bootstrap (config/db fatal → JSON 500)
- [x] T0.10 `index.php`: mapear códigos HTTP no error handler (405→METODO_NAO_PERMITIDO etc.)
- [x] T0.11 `index.php`: `session_save_path` configurável + fallback `X-Forwarded-Proto` p/ Secure
- [x] T0.12 `Json.php`: `Cache-Control: no-store` + fallback de `json_encode`
- [x] T0.13 `Migrator`: `GET_LOCK` anti-concorrência + erro por statement (contexto)
- [x] T0.14 `migrate.php --seed`: recusar quando `config['env']==='prod'`
- [x] T0.15 `create-user.php`: checar config.php, validar e-mail/pessoa_id, try/catch
- [x] T0.16 `composer.json`: `"php": ">=8.2"` (phpunit 11 exige 8.2)
- [x] T0.17 `logout`: cookie de expiração com `samesite` na forma de array
- [x] T0.18 Convenção: novas migrations usam `IF NOT EXISTS`/`CREATE OR REPLACE` (AGENTS.md + Migrator)

### Deploy / .htaccess

- [x] T0.19 `deploy.yml`: gerar `config.php` via PHP `var_export` (sem `sed`) + `php -l`
- [x] T0.20 `deploy.yml`: `timeout-minutes` no job
- [x] T0.21 `deploy.yml`: smoke test pós-deploy (`/`→200 com retry + `/api/health`)
- [x] T0.22 `.htaccess`: HSTS + negar dotfiles/`*.sql`/`*.log`/`composer.*`/`phpunit.xml`

### Frontend (rápidos)

- [x] T0.23 `index.html`: favicon real (criado `public/favicon.svg`)
- [x] T0.24 `App.tsx`: `function App()` → `const App: React.FC` + `aria-label` nos ícones
- [x] T0.25 `api.ts`: tipar `publicApi.eventos` (`unknown[]` → `EventoPublico[]`)
- [x] T0.26 `.env.example`: corrigir sugestão cross-origin (quebra `same-origin`)

### Docs (alinhamento)

- [x] T0.27 Doc 06: entry point real é `api/index.php` (não `api/public/index.php`)
- [x] T0.28 Doc 12: marcar secrets/SSL como concluídos + apontar este checklist
- [x] T0.29 Doc 08: comando correto p/ API dev (`php -S localhost:8080 api/index.php`)
- [x] T0.30 Doc 11: `cron/backup_db.php` ainda não existe — marcado como pendente

## T1 — Fundação

### Backend

- [x] T1.1 `src/Repositories/`: padrão CRUD — `UsuarioRepository`,
      `TentativaLoginRepository`, `ProgramacaoRepository` (SQL fora dos controllers)
- [x] T1.2 Migration `0007`: `CREATE OR REPLACE VIEW` c/ `SQL SECURITY INVOKER`;
      `v_ebd_frequencia_resumo` corrigida (eixo aula×classe + vigência da matrícula);
      `UNIQUE usuarios.pessoa_id`, `membro_departamentos`, `ebd_professores`;
      `ebd_alunos` coluna gerada p/ re-matrícula; `email`→`email_hash` +
      índice por IP em `login_tentativas`; índice `audit_log(criado_em)`
- [x] T1.3 Shape de paginação `{data:{itens,pagina,por_pagina,total}}` — doc 06 +
      `Json::paginada()`/`parametrosPaginacao()` + `RespostaPaginada<T>`

### Testes

- [x] T1.4 PHPUnit: testes unitários dos middlewares (Auth/Perfil/Csrf) + `Json`
- [x] T1.5 Vitest: `jsdom` + `@testing-library/*` instalados; `environment` no vite.config
- [x] T1.6 `api.test.ts` (envelope, erro, CSRF) — `--passWithNoTests` removido
- [x] T1.7 CI: MySQL 8 service + `MigrationsIntegrationTest` (aplica 0001–0007
      de verdade no banco de teste; pulado local sem `ADAB_TEST_DSN`)

### Frontend estrutural

- [x] T1.8 `react-router-dom` + rotas `/`, `/login`, `/redefinir-senha`, `/admin/*`
- [x] T1.9 `auth-context.tsx` (bootstrap `me()`, login/logout, perfil) + `RequireAuth`
- [x] T1.10 `LoginPage` + `RedefinirSenhaPage` (fecha fluxo de reset quebrado)
- [x] T1.11 `AdminLayout` + `DashboardPage` stub + `EmBrevePage` por módulo
- [x] T1.12 `WeeklySchedule`: somente leitura, consumindo `publicApi.programacao()`
      (CRUD anônimo removido; dialog c/ Escape + scroll lock)
- [x] T1.13 `ui/Modal.tsx` (dialog, Escape, scroll lock, foco) + `ui/Field.tsx`
      (label htmlFor + erro via aria-describedby)
- [x] T1.14 Imagens Imgur → `public/img/` (logo, hero, pastor) — CSP adicionada
      no `.htaccess` (self + google maps + soundcloud)

### Robustez backend (débitos)

- [x] T1.15 Sessão lazy (não cria sessão em `/health`, `/public/*`)
- [x] T1.16 `AuthMiddleware`: timeout de inatividade 30min + rotação de
      `session_id` a cada 15min
- [~] T1.17 `admin/migrate`: mantido "sessão admin OU X-Migrate-Token" —
      decisão registrada (SameSite=Lax + perfil admin mitigam CSRF)

## OPS — manual/cPanel (não-código)

- [ ] OPS.1 Criar usuário admin real (phpMyAdmin + seed OU script SQL dedicado)
- [ ] OPS.2 Configurar SMTP no cPanel + secrets (`SMTP_*`) p/ recuperação de senha
- [ ] OPS.3 Backup do banco: commitar `cron/backup_db.php` + agendar cron no cPanel
- [ ] OPS.4 Uptime monitor externo (UptimeRobot/Healthchecks) pingando `/api/health`
- [~] OPS.5 `session_save_path`: deploy já gera `~/adab_sessions` (criado em runtime);
      confirmar criação após próximo deploy
- [x] OPS.6 Verificado em prod (22/10): `config.php`→403, `migrations/*.sql`→403,
      `/.git/config`→403, `.ftp-deploy-sync-state.json`→403, `/login`→200
- [ ] OPS.7 Rotacionar senha FTP/DB (apareceu em chat) + atualizar secrets
- [x] OPS.8 Varredura do histórico git — nenhum segredo/hash/credencial
      commitado (só referências de código)

## T2 — Módulos (ordem do plano de ação)

- [ ] T2.1 M1 Membros (contrato → Repository → rotas → UI) — espinha dorsal
- [ ] T2.2 Gestão de usuários (admin)
- [ ] T2.3 Site público dinâmico (programação/eventos via API) —
      WeeklySchedule já consome `/public/programacao`; falta seção Eventos
- [ ] T2.4 M2 Cultos → M3 Eventos → M4 Ações → M5 EBD
- [ ] T2.5 Testes de autorização negativa por perfil (CI c/ MySQL service)
- [ ] T2.6 E2E Playwright (após 1º fluxo completo)

## DECISÕES — pendentes do cliente (bloqueiam partes do T2)

- [x] D1 **Fluxo livre + desvincula**: status muda livremente; sair de 'ativo'
      desativa vínculos de departamento, matrícula EBD e acesso de usuário
- [ ] D2 EBD: oferta/visitante por aluno ou totais da aula
- [ ] D3 Re-matrícula após desistência (novo registro ou reativação)
- [ ] D4 Escopo `ebd_professor` (busca ampla p/ matricular vs. só sua classe)
- [x] D5 **`lider_departamento` vê/edita apenas o próprio departamento**
      (membros + ações do depto; escopo por registro)
- [x] D6 **Departamentos só aceitam membros** (FK atual para `membros` mantida)
- [ ] D7 LGPD: política de retenção/anonimização + termo de consentimento
- [ ] D8 Calendário EBD (domingos? feriados? 13 lições fixas?)
- [ ] D9 Formato/chave de dedup do CSV de importação
- [ ] D10 SMTP disponível? Fluxo alternativo se e-mail falhar
- [ ] D11 Unique `(tipo_culto_id, data)` — uma entrada por tipo/dia é a regra?
- [ ] D12 Listas iniciais de cargos/tipos de culto
- [x] D13 **Gerador fake removido do MVP** (seção 'Oração & Reflexão' cortada)
