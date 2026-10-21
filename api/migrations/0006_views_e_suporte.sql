-- 0006_views_e_suporte: views de consulta + tabelas de suporte
-- (rate limit de login, auditoria)

CREATE TABLE login_tentativas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_lt_email_data (email, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NULL,
    acao VARCHAR(60) NOT NULL,
    entidade VARCHAR(60) NOT NULL,
    entidade_id VARCHAR(60) NULL,
    detalhes JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_audit_entidade (entidade, entidade_id),
    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW v_membros_ativos AS
SELECT p.id AS pessoa_id, p.nome_completo, p.data_nascimento, p.telefone, p.email,
       p.foto_url, m.numero_carteirinha, m.data_batismo, m.congregacao,
       m.cargo_id, m.status
  FROM pessoas p
  JOIN membros m ON m.pessoa_id = p.id
 WHERE m.status = 'ativo' AND m.deleted_at IS NULL AND p.deleted_at IS NULL;

CREATE OR REPLACE VIEW v_programacao_publica AS
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

CREATE OR REPLACE VIEW v_aniversariantes_mes AS
SELECT p.id AS pessoa_id, p.nome_completo, p.data_nascimento,
       MONTH(p.data_nascimento) AS mes, DAY(p.data_nascimento) AS dia
  FROM pessoas p
  JOIN membros m ON m.pessoa_id = p.id
 WHERE m.status = 'ativo' AND m.deleted_at IS NULL AND p.deleted_at IS NULL
   AND p.data_nascimento IS NOT NULL;

CREATE OR REPLACE VIEW v_ebd_frequencia_resumo AS
SELECT a.classe_id,
       au.periodo_id,
       au.id AS aula_id,
       au.data,
       COUNT(f.id) AS total_registros,
       SUM(f.presente) AS presentes,
       SUM(f.trouxe_biblia) AS biblias,
       SUM(f.trouxe_revista) AS revistas,
       COALESCE(SUM(f.oferta), 0) AS ofertas,
       SUM(f.visitante) AS visitantes
  FROM ebd_aulas au
  JOIN ebd_alunos a ON a.status = 'matriculado'
  LEFT JOIN ebd_frequencias f ON f.aula_id = au.id AND f.aluno_id = a.id
 GROUP BY a.classe_id, au.periodo_id, au.id, au.data;
