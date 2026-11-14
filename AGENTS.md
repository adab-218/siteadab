# Regras do Projeto — Gestão do Templo ADAB

Sistema de gestão da Assembleia de Deus Templo Arthur Bernardes (ADAB):
site público + painel administrativo (membros, cultos, eventos, ações e
Escola Bíblica Dominical).

## Stack

- **Frontend**: Vite 7 + React 18 + TypeScript (strict), Tailwind 3,
  `lucide-react` — SPA servida pelo Apache
- **Backend**: `api/` PHP 8.3 + Slim 4 + PDO sobre MySQL (Hostgator
  compartilhada, mesmo domínio `adab.teo.br`)
- **Auth**: sessão PHP (cookie httpOnly SameSite) + CSRF token; perfis em
  `usuarios.perfil`
- **Deploy**: GitHub Actions → build → FTPS → `public_html` (ver
  `dev/docs/11-deploy-e-operacao.md`)
- Todo o conteúdo visível ao usuário é em **pt-BR**

## Documentação

`dev/docs/` é a fonte da verdade: requisitos, arquitetura, modelo de
dados, segurança (matriz de perfis), API, convenções e plano de ação em
`12-plano-de-acao.md`. **Estado real de implementação e decisões de
negócio D1–D13** ficam em `dev/docs/CHECKLIST.md` — leia antes de
planejar. Consulte antes de implementar módulos.

## Mapa do código

- API: rotas/middlewares em `api/src/App.php` (`criarApp`); `api/index.php`
  só faz bootstrap. Camadas: Controllers (`api/src/Controllers/`) →
  Repositories (`api/src/Repositories/`); sem camada Services por ora.
  Utilitários: `Json` (`resposta`/`erro` → `{ "error": { "code", "message" } }`),
  `Validacao`, `Uploads`, `Migrator`
- Perfis: admin, secretaria, dirigente, ebd_admin, ebd_professor,
  lider_departamento. Escrita com `PerfilMiddleware::para([...])` (admin
  sempre passa); escopo por registro D3 (professor × classe) e D5 (líder × depto)
- Front: rotas em `src/main.tsx`, guard `src/routes/RequireAuth.tsx`,
  painel em `src/pages/admin/`, sessão em `src/lib/auth-context.tsx`,
  cliente HTTP único `src/lib/api.ts` (`apiFetch`, `ApiError`, CSRF), UI
  base em `src/components/ui/`
- Testes: Vitest (`*.test.ts` em `src/`), PHPUnit em `api/tests/`
  (integração exige `ADAB_TEST_DSN`, senão é pulada); CI em `.github/workflows/ci.yml`
- Módulos prontos (back + front + testes): Auth, Usuários, Membros,
  Departamentos, Cultos (+ presença), Eventos, Ações, EBD, Sorteios (M7),
  site público dinâmico. Pendências principais: CSV de membros (D9),
  LGPD (D7), SMTP (D10/OPS.2), backup (OPS.3), escopo do líder em
  sorteios, E2E Playwright, CSP — ver CHECKLIST
- Dependências front: não adicionar libs (react-query, zod, shadcn...)
  sem aprovação, mesmo que citadas no doc 12

## Convenções

- Componentes funcionais `React.FC` com interfaces de props tipadas;
  componentes reutilizáveis em `src/components/`; acesso à API só via
  `src/lib/api.ts` (e futuros `api.ts` por módulo)
- PHP: PSR-12 + `declare(strict_types=1)`; SQL somente em Repositories
  com prepared statements; autorização via `AuthMiddleware`/`PerfilMiddleware`
  — nunca confiar em filtro do front
- Identidade visual: vermelho (`red-600`/`red-700`) como cor primária
- Schema novo/alterado exige migration em `api/migrations/` (NNNN_*.sql);
  preferir formas idempotentes (`CREATE TABLE IF NOT EXISTS`, `CREATE OR
  REPLACE VIEW`, `INSERT ... ON DUPLICATE KEY`) — statements separados
  por `;` ao fim de linha, sem `DELIMITER`/procedures/triggers
- Nunca commitar `api/config.php`, `.env*`, conteúdo de `uploads/`

## Verificação antes de concluir

- `/verify`: `npm run lint`, `npx tsc -b`, `npm run test` e
  `npm run build` sem erros (mesma ordem da CI)
- `composer test` (em `api/`) quando houver PHP/PHPUnit disponível; sem
  PHP local, dizer que a API será validada na CI — nunca alegar sucesso
- `git diff --check` limpo; sem segredos ou dados reais no diff
- Ao concluir item do plano, marcar em `dev/docs/CHECKLIST.md` e
  `dev/docs/12-plano-de-acao.md`

## Equipe de agentes

Subagentes especializados em `.claude/agents/` (ver `docs/TEAM_WORKFLOW.md`
para fluxo completo, DoR/DoD e política de paralelismo):

- `project-coordinator` — decomposição de tarefas e integração
- `requirements-analyst` — escopo, regras de negócio e critérios de aceite
- `software-architect` — contratos, camadas e decisões técnicas
- `database-engineer` — schema, migrations e integridade
- `php-backend-developer` — implementação da API
- `frontend-developer` — UI e integração com contratos
- `qa-test-engineer` — testes e evidências
- `code-reviewer` — revisão independente de correção e convenções
- `security-reviewer` — auditoria independente (somente leitura)
- `devops-release-engineer` — release, observabilidade e rollback
- `process-documenter` — diagramas Mermaid (`dev/docs/diagramas/*.mmd`)

Regras de delegação: quem implementa não aprova sozinho o próprio
trabalho (revisão independente); dois agentes não editam o mesmo arquivo
simultaneamente; auditorias read-only podem rodar em background;
nenhum deploy/migração/operação em produção sem autorização humana
explícita. Checklist de aceite: `docs/ACCEPTANCE_CHECKLIST.md`.

Diagramas de processo: mudanças que alterem fluxos (rotas, middlewares,
passos de workflow, transições de estado, guards de navegação) devem
atualizar `dev/docs/diagramas/*.mmd` no mesmo commit — usar o agente
`process-documenter` ou a skill `/process-diagrams` para convenções.

## Skills disponíveis

Em `.claude/skills/<nome>/SKILL.md` (invocáveis com `/nome` ou
automaticamente pelo modelo quando a descrição se aplica):

- `/review` — revisão rápida das alterações antes de commitar
- `/verify` — rodar lint, tsc, Vitest, build (e PHPUnit se houver PHP)
- `/new-endpoint <MÉTODO /rota>` — endpoint ponta a ponta (rota, perfil,
  controller, repository, teste, `api.ts`, doc 06, diagrama)
- `/new-migration <descricao>` — próxima migration no formato do Migrator
- `/new-component <Nome>` — criar componente seguindo as convenções do projeto
- `/plan-feature`, `/api-contracts`, `/mysql-schema-migrations` — planejamento,
  contratos e schema
- `/php-secure-development`, `/frontend-accessibility`,
  `/test-driven-feature`, `/debug-root-cause` — implementação e correção
- `/independent-code-review`, `/security-audit`, `/definition-of-done`,
  `/release-readiness`, `/process-diagrams` — revisão, aceite e release
  (`/independent-code-review` e `/security-audit` rodam isoladas nos
  agentes `code-reviewer` e `security-reviewer`)

## MCP e permissões

- `.mcp.json`: servidor MCP `github` (remoto, oficial). Exige a variável de
  ambiente `GITHUB_TOKEN` (PAT) — nunca colocar o token no arquivo; o
  Claude Code pede aprovação do servidor no primeiro uso
- `.claude/settings.json`: permissões compartilhadas (git leitura, npm/npx
  liberados; `git push --force`, `git reset --hard`, leitura/edição de
  `api/config.php` e `.env`, e edição de `uploads/` negados). Overrides
  pessoais em `.claude/settings.local.json` (gitignored)
