# Diagramas de processo (Mermaid)

Cada `.mmd` descreve um fluxo REAL do sistema. São mantidos pelo agente
`process-documenter` (`.devin/agents/process-documenter.md`) e devem ser
atualizados **no mesmo commit** de qualquer mudança que altere o fluxo
(ver skill `/process-diagrams` e regra em `AGENTS.md`).

| Arquivo | Processo |
|---|---|
| `autenticacao.mmd` | Login, me, logout, recuperar/redefinir senha, rate-limit |
| `fluxo-requisicao-api.mmd` | Pipeline HTTP: Apache → index.php → sessão lazy → Slim → Json |
| `deploy-ci-cd.mmd` | Workflows CI + Deploy (verify → config.php → FTPS → migrate → smoke) |
| `migracoes.mmd` | Execução de migrations (token/CLI → lock → apply → registro) |
| `navegacao-app.mmd` | Rotas React: site, login, redefinir, /admin + RequireAuth |
| `site-publico.mmd` | Programação semanal e eventos públicos via API |
| `membros.mmd` | M1: CRUD /membros, escopo D5 do líder, desvinculação D1 |

## Fluxos ainda sem diagrama (a criar quando implementados)

- Ciclo de vida de membro (D1) — aguardando decisão de negócio
- Chamada EBD e frequência (M5)
- Cultos/eventos/ações — CRUDs (T2)
- Importação CSV de membros (D9)
- LGPD retenção/anonimização (D7)
