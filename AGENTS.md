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
`12-plano-de-acao.md`. Consulte antes de implementar módulos.

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

- `npm run lint` e `npm run build` devem passar sem erros
- `composer test` (em `api/`) quando houver PHP/PHPUnit disponível
- `git diff --check` limpo; sem segredos ou dados reais no diff

## Equipe de agentes

Subagentes especializados em `.devin/agents/` (ver `docs/TEAM_WORKFLOW.md`
para fluxo completo, DoR/DoD e política de paralelismo):

- `project-coordinator` — decomposição de tarefas e integração
- `requirements-analyst` — escopo, regras de negócio e critérios de aceite
- `software-architect` — contratos, camadas e decisões técnicas
- `database-engineer` — schema, migrations e integridade
- `php-backend-developer` — implementação da API
- `frontend-developer` — UI e integração com contratos
- `qa-test-engineer` — testes e evidências
- `code-reviewer` / `reviewer` — revisão de correção e convenções
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

- `/review` — revisar alterações antes de commitar
- `/verify` — rodar lint + build e reportar problemas
- `/new-component` — criar componente seguindo as convenções do projeto
