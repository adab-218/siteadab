---
name: process-diagrams
description: Criar/atualizar diagramas Mermaid (.mmd) de fluxos de processo do sistema.
---

# Diagramas de processo (Mermaid)

Os diagramas vivem em `dev/docs/diagramas/` como arquivos `.mmd`
(Mermaid puro, sem render necessário — GitHub renderiza blocos
```mermaid em previews e a extensão Mermaid do editor abre .mmd).

## Quando atualizar (gatilhos)

Atualize o `.mmd` correspondente NO MESMO commit quando mudar:
- rotas/grupos em `api/index.php` ou assinatura de middleware;
- lógica de fluxo em Controllers (branching, novos endpoints);
- `.github/workflows/*` (passos, retries, gates);
- transições de status em migrations ou regras de negócio;
- rotas frontend em `src/main.tsx`, guards, contexto de sessão;
- contratos em `src/lib/api.ts` que afetem sequência de chamadas.

## Convenções

- Um processo por arquivo, nome kebab-case (ex.: `autenticacao.mmd`);
- primeira linha: `%% <titulo> — revisado em AAAA-MM-DD`;
- textos em pt-BR;
- tipos: `sequenceDiagram` (requisições/atores), `flowchart`
  (pipelines/navegação), `stateDiagram-v2` (ciclo de vida);
- só diagramar fluxo implementado — futuro planejado vai ao README;
- índice de diagramas e política: `dev/docs/diagramas/README.md`.

## Validação

- Releia o código-fonte citado antes de marcar como fiel;
- confira balanceamento de `end` em `subgraph`/`loop`/`alt` e sintaxe
  de participantes (`participant`, `->>`, `-->>`);
- reporte ao final: arquivos alterados e fluxos ainda sem cobertura.
