# Sistema de Gestão do Templo — ADAB

Documentação técnica e funcional do sistema de gestão da **Assembleia de Deus
Templo Arthur Bernardes** (Belém-PA). O sistema evolui do site institucional
atual para uma plataforma completa de gestão eclesiástica.

## Índice

| Doc | Conteúdo |
|-----|----------|
| [01 — Visão e Escopo](01-visao-e-escopo.md) | Objetivos, módulos, personas, fora de escopo |
| [02 — Requisitos](02-requisitos.md) | Requisitos funcionais e não funcionais |
| [03 — Arquitetura](03-arquitetura.md) | Visão de componentes, fluxos, decisões |
| [ADR 0001 — Stack](adr/0001-stack-tecnologica.md) | Decisão de stack tecnológica e alternativas |
| [04 — Modelo de Dados](04-modelo-de-dados.md) | DER, tabelas, dicionário de dados |
| [05 — Segurança e Permissões](05-seguranca-e-permissoes.md) | Papéis, autorização na API, LGPD |
| [06 — API e Integrações](06-api-e-integracoes.md) | Contratos de acesso a dados |
| [07 — UX e Fluxos](07-ux-e-fluxos.md) | Telas, fluxos e diretrizes de UI |
| [08 — Setup e Ambiente](08-setup-e-ambiente.md) | Como rodar o projeto localmente |
| [09 — Padrões e Convenções](09-padroes-e-convencoes.md) | Código, git, nomenclatura |
| [10 — Estratégia de Testes](10-testes.md) | Pirâmide de testes e cobertura |
| [11 — Deploy e Operação](11-deploy-e-operacao.md) | CI/CD, backup, observabilidade |
| [12 — Plano de Ação](12-plano-de-acao.md) | Checklist de implementação por fases |

## Estado atual x visão

| Hoje | Visão |
|------|-------|
| Site estático de uma página | Site público + painel administrativo |
| Programação hardcoded no código | Programação gerenciada no banco de dados |
| Edição de eventos só em memória | CRUD persistente com permissões |
| Sem autenticação | Perfis de acesso por função (secretaria, EBD, etc.) |
