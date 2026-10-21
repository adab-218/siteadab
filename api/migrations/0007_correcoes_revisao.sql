-- 0007_correcoes_revisao: unicidades, views com SQL SECURITY INVOKER,
-- correção da v_ebd_frequencia_resumo (produto cartesiano + ex-alunos),
-- e-mail de login_tentativas vira hash (LGPD) e índices de suporte.
-- Ver dev/docs/CHECKLIST.md (T1.2).

-- login_tentativas: e-mail passa a ser armazenado como SHA-256 (privacidade)
-- e índice por IP para o novo limite de tentativas por IP.
ALTER TABLE login_tentativas
    CHANGE COLUMN email email_hash CHAR(64) NOT NULL,
    ADD KEY ix_lt_ip_data (ip, criado_em);

-- Uma conta de usuário por pessoa (modelo do doc 04).
ALTER TABLE usuarios
    ADD UNIQUE KEY uq_usuarios_pessoa (pessoa_id);

-- Sem duplicar vínculo membro↔departamento.
ALTER TABLE membro_departamentos
    ADD UNIQUE KEY uq_md_pessoa_departamento (pessoa_id, departamento_id);

-- Sem duplicar professor↔classe.
ALTER TABLE ebd_professores
    ADD UNIQUE KEY uq_ep_classe_pessoa (classe_id, pessoa_id);

-- Consultas por período na auditoria.
ALTER TABLE audit_log
    ADD KEY ix_audit_criado_em (criado_em);

-- ebd_alunos: a unicidade anterior (classe_id, pessoa_id, status) impedia
-- re-matrícula legítima (2º registro 'desistente' colidia). Coluna gerada
-- garante no máximo UMA matrícula ativa por (classe, pessoa) e permite
-- histórico ilimitado de encerradas (NULLs não colidem em UNIQUE).
ALTER TABLE ebd_alunos
    ADD COLUMN ativo_flag TINYINT GENERATED ALWAYS AS (IF(status = 'matriculado', 1, NULL)) VIRTUAL,
    DROP INDEX uq_ea_classe_pessoa_ativo,
    ADD UNIQUE KEY uq_ea_classe_pessoa_ativo (classe_id, pessoa_id, ativo_flag);

-- Views recriadas com SQL SECURITY INVOKER (não dependem do usuário
-- que migrou; em shared hosting evita quebra ao trocar credencial).
CREATE OR REPLACE
SQL SECURITY INVOKER
VIEW v_membros_ativos AS
SELECT p.id AS pessoa_id, p.nome_completo, p.data_nascimento, p.telefone, p.email,
       p.foto_url, m.numero_carteirinha, m.data_batismo, m.congregacao,
       m.cargo_id, m.status
  FROM pessoas p
  JOIN membros m ON m.pessoa_id = p.id
 WHERE m.status = 'ativo' AND m.deleted_at IS NULL AND p.deleted_at IS NULL;

CREATE OR REPLACE
SQL SECURITY INVOKER
VIEW v_programacao_publica AS
SELECT 'culto' AS tipo,
       t.nome AS titulo,
       c.data,
       c.horario_inicio,
       NULL AS data_fim,
       t.nome AS categoria
  FROM cultos c
  JOIN tipos_culto t ON t.id = c.tipo_culto_id
 WHERE c.data >= CURDATE() AND t.ativo = 1
UNION ALL
SELECT 'evento' AS tipo,
       e.nome AS titulo,
       e.data_inicio AS data,
       NULL AS horario_inicio,
       e.data_fim,
       e.tipo AS categoria
  FROM eventos e
 WHERE e.publico = 1
   AND e.status <> 'cancelado'
   AND COALESCE(e.data_fim, e.data_inicio) >= CURDATE()
   AND e.deleted_at IS NULL;

CREATE OR REPLACE
SQL SECURITY INVOKER
VIEW v_aniversariantes_mes AS
SELECT p.id AS pessoa_id, p.nome_completo, p.data_nascimento,
       MONTH(p.data_nascimento) AS mes, DAY(p.data_nascimento) AS dia
  FROM pessoas p
  JOIN membros m ON m.pessoa_id = p.id
 WHERE m.status = 'ativo' AND m.deleted_at IS NULL AND p.deleted_at IS NULL
   AND p.data_nascimento IS NOT NULL;

-- Corrigida: eixo aula × classe ativa (sem produto cartesiano por aluno);
-- alunos entram na agregação apenas dentro da vigência da matrícula, o que
-- preserva o histórico de quem virou desistente/transferido.
CREATE OR REPLACE
SQL SECURITY INVOKER
VIEW v_ebd_frequencia_resumo AS
SELECT c.id AS classe_id,
       au.periodo_id,
       au.id AS aula_id,
       au.data,
       COUNT(f.id) AS total_registros,
       COALESCE(SUM(f.presente), 0) AS presentes,
       COALESCE(SUM(f.trouxe_biblia), 0) AS biblias,
       COALESCE(SUM(f.trouxe_revista), 0) AS revistas,
       COALESCE(SUM(f.oferta), 0) AS ofertas,
       COALESCE(SUM(f.visitante), 0) AS visitantes
  FROM ebd_aulas au
  JOIN ebd_classes c ON c.ativo = 1
  LEFT JOIN ebd_alunos a ON a.classe_id = c.id
       AND a.data_matricula <= au.data
       AND (a.data_desistencia IS NULL OR a.data_desistencia >= au.data)
  LEFT JOIN ebd_frequencias f ON f.aula_id = au.id AND f.aluno_id = a.id
 GROUP BY c.id, au.periodo_id, au.id, au.data;
