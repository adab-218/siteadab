# 07 — UX e Fluxos

## Diretrizes

- pt-BR em todo texto; datas `dd/mm/aaaa`, moeda `R$`.
- Identidade visual: primária `red-600`/`red-700`, cinzas neutros,
  acentos por departamento (já usados em `getDepartmentColor`).
- Componentes: shadcn/ui sobre Tailwind — tabela, dialog, drawer
  (mobile), form, select, date picker, toast (sonner).
- Mobile-first nas telas operacionais (chamada EBD em especial).
- Acessibilidade: `aria-label` em botões de ícone, foco visível,
  modais com trap de foco e fechamento por `Esc`.

## Mapa de telas

### Público (`/`)
Site atual, agora com:
- Programação dinâmica (cultos + eventos do banco)
- Festividades destacadas (banner dos eventos `publico = true`)
- Link discreto "Área do Membro" → `/login`

### Painel (`/admin`)
```
/admin
├── dashboard          # KPIs, aniversariantes, próximos eventos
├── membros/
│   ├── lista          # busca + filtros + tabela paginada
│   ├── novo           # wizard: dados pessoais → eclesiásticos → foto
│   └── [id]           # ficha: dados, departamentos, EBD, cultos
├── cultos/            # calendário/lista + formulário de registro
├── eventos/           # lista + detalhe com programação interna
├── acoes/             # lista + detalhe (voluntários, beneficiários)
└── ebd/
    ├── classes/       # grade de classes + professores
    ├── alunos/        # matrícula (busca pessoa, transfere)
    ├── periodos/      # trimestres e lições
    ├── chamada/[aulaId]/[classeId]   # tela mobile de frequência
    └── relatorios/    # frequência, faltosos, ranking
```

## Fluxos críticos

### Chamada da EBD (mobile)
1. Professor abre `/admin/ebd/chamada` → vê aulas do dia das suas classes.
2. Seleciona a aula → lista de alunos da classe com toggles:
   presente / Bíblia / revista; oferta e visitante quando aplicável.
3. Um toque marca presença; "marcar todos" para agilizar.
4. Salvar → upsert; estado offline: guardar rascunho em localStorage e
   sincronizar quando voltar a rede (fase 2).

### Matrícula de aluno
1. Buscar pessoa por nome (ou cadastrar nova rapidamente — modal).
2. Escolher classe → confirma → status `matriculado`.
3. Transferência: encerra matrícula atual (`data_desistencia` +
   status `transferido`) e cria nova — histórico preservado.

### Registro de culto
1. Secretaria abre cultos → "+ Registrar culto" → tipo + data
   (sugere horário padrão do tipo).
2. Preenche dirigente/pregador (combobox de membros), tema, totais.
3. Opcional: presença nominal marcando membros presentes.

### Importação de membros
1. Upload CSV → preview com validação linha a linha.
2. Erros listados por linha; confirmar importa apenas válidas.

## Estados de UI

- Tabelas: skeleton durante fetch, empty state com CTA, erro com retry.
- Toda mutação: toast de sucesso/erro; confirmação para exclusões
  (que na prática são soft delete/desativação).
