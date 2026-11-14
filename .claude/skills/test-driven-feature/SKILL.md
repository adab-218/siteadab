---
name: test-driven-feature
description: "Implementar uma funcionalidade com testes e critérios de aceite verificáveis."
---
Leia os critérios de aceite e testes existentes. Crie ou atualize testes para caminho feliz, entradas inválidas, autorização, falhas e regressões. Implemente a mudança mínima necessária. Execute os comandos reais do projeto e registre saída. Não enfraqueça testes nem alegue que passaram sem executá-los. Use banco de teste isolado.

Comandos reais do projeto:
- Front: `npm run test` (Vitest; arquivos `*.test.ts(x)` ao lado do código), `npx tsc -b`, `npm run lint`.
- API: `composer test` em `api/` com `ADAB_TEST_DSN`/`ADAB_TEST_USER`/`ADAB_TEST_PASS` apontando para um MySQL de teste descartável. Sem PHP/MySQL local, a evidência vem do job `api` da CI — diga isso explicitamente; testes pulados não contam como aprovados.
- Autorização: acrescente casos negativos em `api/tests/AutorizacaoIntegrationTest.php` para cada rota nova.
