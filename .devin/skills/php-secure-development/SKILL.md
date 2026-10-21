---
name: php-secure-development
description: Implementar PHP seguro com validação, autorização, sessões e tratamento de erros.
triggers:
  - user
  - model
---
Antes de editar, inspecione a versão PHP e padrões do projeto. Use PDO e prepared statements; valide dados no servidor; centralize autorização; escape saída conforme contexto; proteja sessões; use `password_hash()`/`password_verify()`; implemente CSRF para ações com sessão; não exponha detalhes internos. Acrescente testes para entradas inválidas e acessos não autorizados. Não adicione framework sem aprovação.
