---
name: review
description: "Revisão rápida das alterações atuais antes de commitar (bugs, segurança, estilo, pt-BR). Para revisão independente use /independent-code-review."
allowed-tools: Read, Grep, Glob, Bash(git diff:*), Bash(git status:*), Bash(git log:*)
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

Se a pasta não for um repositório git, peça a lista de arquivos alterados
e revise-os diretamente. Para uma revisão independente mais profunda (em
subagente isolado), use `/independent-code-review`.
