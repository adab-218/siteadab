# Equipe de agentes — Claude Code / PHP + MySQL

Configuração de agentes para o projeto (migrada do Devin CLI para o Claude Code). Contém instruções globais, 11 subagentes, 17 skills reutilizáveis, um servidor MCP e um fluxo de trabalho. Não inclui o sistema de negócio em si.

## Conteúdo

- `CLAUDE.md`: importa `AGENTS.md` (regras permanentes do repositório, lidas pelo Claude Code e por outras ferramentas).
- `.claude/agents/*.md`: subagentes do Claude Code (frontmatter `name`, `description`, `tools`, `model`). Sem `tools`, o agente herda todas as ferramentas da sessão. Agentes de análise/revisão são somente leitura (`Read, Grep, Glob` + `Bash` quando precisam rodar `git diff`/testes).
- `.claude/skills/*/SKILL.md`: skills invocáveis com `/nome-da-skill` ou automaticamente pelo modelo conforme a `description`. `/independent-code-review` e `/security-audit` usam `context: fork` + `agent:` para rodar isoladas no revisor correspondente.
- `.claude/settings.json`: permissões compartilhadas. Overrides pessoais em `.claude/settings.local.json` (gitignored).
- `.mcp.json`: servidores MCP do projeto (GitHub).
- `docs/TEAM_WORKFLOW.md`: fluxo de trabalho e contratos de equipe.
- `docs/TASK_TEMPLATE.md`: modelo para delegar tarefas.
- `docs/ACCEPTANCE_CHECKLIST.md`: checklist de qualidade e segurança.

## Mapeamento Devin → Claude Code

| Devin CLI | Claude Code |
|---|---|
| `AGENTS.md` | `CLAUDE.md` (com `@AGENTS.md`) |
| `.devin/agents/*.md` (`allowed-tools: read, grep, glob, exec, write, edit`) | `.claude/agents/*.md` (`tools: Read, Grep, Glob, Bash, Write, Edit`) |
| `.devin/skills/*/SKILL.md` (`triggers: [user, model]`) | `.claude/skills/*/SKILL.md` (padrão: usuário e modelo) |
| `.devin/config.json` → `permissions` (`Exec(...)`) | `.claude/settings.json` → `permissions` (`Bash(...:*)`) |
| `.devin/mcp_config.json` | `.mcp.json` |
| `.devin/*.local.json` | `.claude/settings.local.json`, `CLAUDE.local.md` |

## Como usar

1. Abra o repositório com `claude` (terminal) ou no app Claude (aba Code).
2. Liste os subagentes com `/agents` e as skills digitando `/`.
3. Peça ao agente principal para delegar pelo nome, por exemplo: “Use o subagente `security-reviewer` para revisar o diff”. Agentes com “Use proativamente” na descrição podem ser acionados automaticamente.
4. Para o MCP do GitHub, defina `GITHUB_TOKEN` no ambiente (PAT com escopo mínimo) antes de abrir a sessão e aprove o servidor `github` quando solicitado. Confira com `/mcp`.
5. Comece em modo de planejamento (Shift+Tab → Plan) para validar o plano sem editar arquivos. Use modo normal para implementação e aprovação humana para operações de alto impacto.

## Recomendações de execução

- Use inicialmente 2–3 subagentes por tarefa; o paralelismo aumenta custo e pode criar conflitos.
- Pesquisa e auditoria somente leitura podem rodar em background. Alterações em código e banco devem ser coordenadas e revisadas.
- Subagentes do Claude Code não criam outros subagentes; a coordenação fica com o agente principal (Tech Lead).
- O projeto deve definir comandos de teste reais. Não invente resultados nem marque critérios como atendidos sem evidência.
- O Claude Code executa localmente com as permissões do ambiente; mantenha credenciais e acesso de produção fora do alcance da sessão sempre que possível.

## Referências oficiais

- Subagentes: https://docs.claude.com/en/docs/claude-code/sub-agents
- Skills: https://docs.claude.com/en/docs/claude-code/skills
- MCP: https://docs.claude.com/en/docs/claude-code/mcp
- Settings/permissões: https://docs.claude.com/en/docs/claude-code/settings

## Fluxo recomendado para uma nova funcionalidade

1. `project-coordinator` → plano com tarefas, agentes e arquivos (ou `/plan-feature`).
2. `requirements-analyst` se houver regra de negócio indefinida (registrar nova decisão `Dn` no CHECKLIST).
3. `/new-migration` (via `database-engineer`) quando o schema mudar.
4. `/new-endpoint` (via `php-backend-developer`) → `frontend-developer` para a tela.
5. `qa-test-engineer` → testes de autorização e regressão.
6. `/verify` → `/independent-code-review` → `/security-audit` (rotas públicas, uploads, dados pessoais).
7. `process-documenter` para diagramas; marcar itens em `dev/docs/CHECKLIST.md` e `12-plano-de-acao.md`.
