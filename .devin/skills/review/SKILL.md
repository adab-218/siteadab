---
name: review
description: Revisar alterações de código antes de commitar
allowed-tools:
  - read
  - grep
  - glob
  - exec
---

Revise as alterações atuais e dê feedback:

1. Rode `git diff --staged` (ou `git diff` se nada estiver staged)
2. Verifique:
   - Erros de lógica ou bugs
   - Tratamento de erros ausente
   - Problemas de segurança
   - Inconsistências de estilo com o restante do código
   - Textos visíveis ao usuário em pt-BR
   - Uso correto de classes Tailwind e ícones lucide-react
3. Resuma os achados e sugira melhorias, citando arquivos e linhas
