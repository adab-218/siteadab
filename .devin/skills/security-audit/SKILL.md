---
name: security-audit
description: Auditar diff e aplicação web para falhas de segurança sem ações destrutivas.
triggers:
  - user
  - model
---
Revise SQL injection, XSS, CSRF, autenticação, autorização por registro, sessões, senhas, segredos, upload, traversal, logs e configuração. Não teste produção nem exfiltre dados. Relate severidade, caminho/linha, cenário, impacto, correção e nível de confiança. Separe achados confirmados de suspeitas.
