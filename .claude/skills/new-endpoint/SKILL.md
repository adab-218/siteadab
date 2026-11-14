---
name: new-endpoint
description: "Criar ou estender um endpoint da API (Slim) ponta a ponta: rota + perfil, controller, repository, teste de integração, cliente em src/lib/api.ts, doc 06 e diagrama. Use ao adicionar rotas à API."
argument-hint: "<MÉTODO /rota> <descrição curta>"
---

Endpoint pedido: $ARGUMENTS

Siga o padrão de um módulo existente (ex.: Cultos). Passos:

1. **Contrato** — defina método, rota (kebab-case, plural), perfis que
   leem/escrevem, escopo por registro (D3/D5), payload, validações,
   resposta `{ "data": ... }` e erros `{ "error": { "code", "message" } }`
   (400/403/404/422). Se houver regra de negócio indefinida, pare e pergunte.
2. **Schema** — precisa de tabela/coluna? Use `/new-migration` primeiro.
3. **Repository** (`api/src/Repositories/<Nome>Repository.php`) — todo SQL
   aqui, prepared statements, `final class`, `strict_types`.
4. **Controller** (`api/src/Controllers/<Nome>Controller.php`) — validação
   com `Validacao`, `Json::resposta`/`Json::erro` com mensagens pt-BR,
   checagem de escopo por registro, transação se tocar várias tabelas.
5. **Rota** em `api/src/App.php` dentro do grupo correto (autenticado ou
   `/public`), escrita com `->add(PerfilMiddleware::para([...]))`.
6. **Testes** em `api/tests/<Nome>IntegrationTest.php` (padrão
   `ADAB_TEST_DSN` + `markTestSkipped`) e casos negativos em
   `AutorizacaoIntegrationTest` (401, 403 CSRF, 403 perfil/escopo).
7. **Cliente** em `src/lib/api.ts`: tipos TS + função no objeto do módulo.
8. **Docs**: `dev/docs/06-api-e-integracoes.md`; diagrama
   `dev/docs/diagramas/<modulo>.mmd` (skill `/process-diagrams`).
9. Rodar `/verify` e pedir `/independent-code-review` (e `/security-audit`
   se a rota for pública, tiver upload ou dados pessoais).

Reporte arquivos criados/alterados e o que ficou pendente.
