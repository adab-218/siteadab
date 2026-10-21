# 01 — Visão e Escopo

## Objetivo

Transformar o site institucional da AD Templo Arthur Bernardes em um **sistema
de gestão do templo**, unificando:

- **Site público**: informações institucionais, programação, localização
  (conteúdo hoje estático passa a vir do banco de dados).
- **Painel administrativo** (área restrita): gestão de membros, cultos,
  festividades, ações e Escola Bíblica Dominical (EBD).

## Personas

| Persona | Necessidade |
|---------|-------------|
| Pastor / dirigente | Visão geral, relatórios, acompanhamento de crescimento |
| Secretaria | Cadastro de membros, registro de cultos e eventos |
| Superintendente / Secretário da EBD | Classes, professores, matrículas, relatórios de frequência |
| Professor da EBD | Chamada/frequência da sua classe no culto de domingo |
| Líder de departamento | Ver membros do seu departamento, registrar ações |
| Membro / visitante | Site público: programação, contato, localização |

## Módulos

### M1 — Membros
Cadastro completo de pessoas e membros: dados pessoais, contato, endereço,
família, dados eclesiásticos (conversão, batismo, cargo, congregação),
vínculo com departamentos, foto, status (ativo/afastado/transferido/falecido).

### M2 — Cultos
Tipos de culto, agenda, registro por data: dirigente, pregador, tema,
totais de presentes/visitantes/decisões. Presença nominal opcional.

### M3 — Festividades e Eventos
Calendário de eventos (congressos, aniversário do templo, datas comemorativas),
com programação interna por dia/horário, responsáveis e banner público.

### M4 — Ações
Ações sociais e evangelísticas: campanhas, voluntários, beneficiários,
resultados. Ligadas opcionalmente a departamentos.

### M5 — EBD (Escola Bíblica Dominical)
- Classes por faixa etária/tema
- Professores (titular, auxiliar, substituto) vinculados a classes
- Matrícula de alunos (vinculada ao cadastro de pessoas)
- Períodos letivos (trimestres/revistas) e lições
- **Chamada**: presença, Bíblia, revista, oferta, visitantes — por aluno,
  por aula
- Relatórios: frequência por classe/período, alunos faltosos, ranking

### M6 — Site público
Consome agenda real (cultos + eventos) do banco, em vez de conteúdo
hardcoded. Mantém seções institucionais atuais.

## Fora de escopo (fases futuras)

- Financeiro (dízimos, ofertas, tesouraria)
- Comunicação (envio de e-mails/WhatsApp em massa)
- App mobile nativo (o painel será responsivo; PWA avaliável)
- Multi-congregação com hierarquia (o campo `congregacao` em membros
  cobre o caso simples)

## Premissas

- Equipe de desenvolvimento pequena (possivelmente voluntários)
- Uso majoritariamente via navegador, incluindo celular (chamada da EBD)
- Conteúdo 100% pt-BR
