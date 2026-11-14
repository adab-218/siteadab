-- 0010_rifa_divulgador_departamento: congela o departamento do link de
-- divulgação no momento em que ele é gerado.
--
-- Motivo (auditoria de segurança, escopo D5/R1 do M7): o escopo do
-- lider_departamento era calculado pelo vínculo ATUAL do divulgador em
-- membro_departamentos. Como o líder pode incluir qualquer membro no
-- próprio depto, ele ampliava o escopo e passava a ver bilhetes
-- (telefones) e confirmar pagamentos de divulgadores de outros deptos.
-- Agora o escopo usa rifa_divulgadores.departamento_id, gravado na criação.
--
-- Dados existentes: backfill com o menor departamento ativo do membro
-- (mesmo critério já usado no ranking/extrato). Links sem depto ficam
-- NULL — visíveis só para secretaria/admin.
-- Rollback manual: ALTER TABLE rifa_divulgadores DROP FOREIGN KEY
-- fk_rd_departamento, DROP COLUMN departamento_id (sem ponto e vírgula aqui: o Migrator divide por ";" no fim da linha)
-- Não idempotente por natureza (ADD COLUMN), protegido pelo controle do
-- Migrator (roda uma única vez).

ALTER TABLE rifa_divulgadores
    ADD COLUMN departamento_id BIGINT UNSIGNED NULL AFTER pessoa_id,
    ADD CONSTRAINT fk_rd_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id) ON DELETE SET NULL,
    ADD KEY ix_rd_rifa_depto (rifa_id, departamento_id);

UPDATE rifa_divulgadores rd
  JOIN (SELECT pessoa_id, MIN(departamento_id) AS departamento_id
          FROM membro_departamentos
         WHERE data_fim IS NULL
         GROUP BY pessoa_id) md ON md.pessoa_id = rd.pessoa_id
   SET rd.departamento_id = md.departamento_id
 WHERE rd.departamento_id IS NULL;
