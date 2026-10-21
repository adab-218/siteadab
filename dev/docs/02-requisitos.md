# 02 — Requisitos

Legenda de prioridade: **M** = Must (MVP), **S** = Should, **C** = Could.

## Requisitos Funcionais

### M1 — Membros

| ID | Requisito | Pri. |
|----|-----------|------|
| RF-M1.1 | Cadastrar pessoa com dados pessoais, contato e endereço | M |
| RF-M1.2 | Registrar dados eclesiásticos: conversão, batismo, cargo, congregação, status | M |
| RF-M1.3 | Upload de foto do membro | M |
| RF-M1.4 | Listar membros com busca por nome e filtros (status, cargo, departamento) | M |
| RF-M1.5 | Ficha individual do membro com histórico | M |
| RF-M1.6 | Vincular membro a um ou mais departamentos com função e período | M |
| RF-M1.7 | Inativar/transferir membro preservando histórico (soft delete) | M |
| RF-M1.8 | Listagem de aniversariantes do mês | S |
| RF-M1.9 | Importar membros via planilha (CSV/Excel) | S |
| RF-M1.10 | Exportar listagem (CSV/PDF) | S |
| RF-M1.11 | Agrupar membros por família | C |

### M2 — Cultos

| ID | Requisito | Pri. |
|----|-----------|------|
| RF-M2.1 | Cadastrar tipos de culto (EBD, Adoração, Família, Unidade, etc.) | M |
| RF-M2.2 | Registrar culto por data: dirigente, pregador, tema | M |
| RF-M2.3 | Registrar totais: presentes, visitantes, decisões/conversões | M |
| RF-M2.4 | Registrar presença nominal de membros no culto | S |
| RF-M2.5 | Relatório de frequência de cultos por período/tipo | S |

### M3 — Festividades e Eventos

| ID | Requisito | Pri. |
|----|-----------|------|
| RF-M3.1 | Cadastrar evento com nome, tipo, período, local, descrição e banner | M |
| RF-M3.2 | Cadastrar programação interna do evento (dia, horário, atividade, responsável) | M |
| RF-M3.3 | Eventos públicos aparecem automaticamente no site | M |
| RF-M3.4 | Calendário mensal de eventos | S |

### M4 — Ações

| ID | Requisito | Pri. |
|----|-----------|------|
| RF-M4.1 | Cadastrar ação social/evangelística (nome, tipo, data, local, objetivo) | M |
| RF-M4.2 | Vincular voluntários (membros) e departamento responsável | M |
| RF-M4.3 | Registrar beneficiários e resultados | M |

### M5 — EBD

| ID | Requisito | Pri. |
|----|-----------|------|
| RF-M5.1 | Cadastrar classes (nome, faixa etária, sala) | M |
| RF-M5.2 | Vincular professores a classes (titular/auxiliar/substituto, período) | M |
| RF-M5.3 | Matricular aluno em classe (vinculado ao cadastro de pessoas) | M |
| RF-M5.4 | Transferir/desmatricular aluno preservando histórico | M |
| RF-M5.5 | Cadastrar períodos letivos (trimestre, tema da revista) e lições com data | M |
| RF-M5.6 | Realizar chamada por aula: presença, Bíblia, revista, oferta, visitante | M |
| RF-M5.7 | Relatório de frequência por classe e por período | M |
| RF-M5.8 | Listar alunos faltosos (N ausências consecutivas) para acompanhamento | S |
| RF-M5.9 | Chamada otimizada para celular (uso durante a aula) | S |

### M6 — Painel e site público

| ID | Requisito | Pri. |
|----|-----------|------|
| RF-M6.1 | Login com e-mail/senha e recuperação de senha | M |
| RF-M6.2 | Controle de acesso por perfil (ver 05-seguranca-e-permissoes) | M |
| RF-M6.3 | Dashboard com KPIs: membros ativos, frequência EBD, próximos eventos | M |
| RF-M6.4 | Programação do site público alimentada pelos dados de cultos/eventos | M |
| RF-M6.5 | Aniversariantes e festividades no dashboard | S |

## Requisitos Não Funcionais

| ID | Requisito |
|----|-----------|
| RNF-1 | Interface 100% pt-BR, responsiva (mobile-first na chamada da EBD) |
| RNF-2 | Conformidade LGPD: dados religiosos são dados **sensíveis** — ver doc 05 |
| RNF-3 | Autenticação obrigatória para o painel; site público sem login |
| RNF-4 | Autorização por perfil na API (middleware); nenhuma rota admin expõe dados sem permissão; escopo de dados aplicado no repositório |
| RNF-5 | Auditoria: `created_by`, `created_at`, `updated_at` em tabelas de cadastro |
| RNF-6 | Soft delete em entidades de cadastro (histórico preservado) |
| RNF-7 | Build com typecheck; lint verde obrigatório em CI |
| RNF-8 | Backup diário do banco (ver doc 11) |
| RNF-9 | Primeira carga do painel < 3s em 4G; site público otimizado (lazy load) |
