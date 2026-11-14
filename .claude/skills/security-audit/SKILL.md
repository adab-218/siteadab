---
name: security-audit
description: "Auditoria de segurança do diff ou de um módulo em subagente isolado (security-reviewer), sem ações destrutivas. Use em mudanças de auth, perfis, uploads, endpoints públicos ou dados pessoais."
context: fork
agent: security-reviewer
---

Audite as alterações atuais (`git diff`, `git diff --staged`) ou, se
indicado, o módulo/arquivos: $ARGUMENTS

Revise SQL injection, XSS, CSRF, autenticação, autorização por perfil e
por registro (D3/D5), sessões, senhas, segredos, uploads, traversal,
endpoints `/public/*`, logs, dados pessoais (LGPD) e configuração
(`.htaccess`). Não teste produção nem exfiltre dados. Relate severidade,
`arquivo:linha`, cenário, impacto, correção e nível de confiança,
separando achados confirmados de suspeitas.
