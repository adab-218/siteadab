---
name: verify
description: Rodar lint e build para validar o projeto
allowed-tools:
  - exec
  - read
---

Valide o estado do projeto:

1. Rode `npm run lint` e reporte erros/warnings
2. Rode `npm run build` e confirme que compila sem erros
3. Se algo falhar, identifique a causa raiz nos arquivos indicados
   e proponha (ou aplique, se pedido) a correção
4. Reporte um resumo final: lint OK/falha, build OK/falha
