-- 0011_sorteios_rate_limit_e_compromisso: itens da auditoria do M7 (T2.8).
--
-- 1) rate_limit_eventos — limite por IP nas rotas públicas de sorteio
--    (reserva e consulta por telefone). Tabela própria, separada de
--    login_tentativas, para que reservas em rede compartilhada (wifi da
--    igreja) não bloqueiem login. `peso` permite somar números reservados.
-- 2) Compromisso da semente (commit-reveal): no método 'sistema' a seed é
--    gerada pelo servidor na ativação e prova_hash = SHA-256(seed) é
--    publicado ANTES das vendas, e a seed só é revelada após o sorteio.
--    Rifas já ativas/encerradas sem seed recebem compromisso agora
--    (sem RANDOM_BYTES, ausente no MariaDB < 10.10 — só backfill).
-- 3) v_rifas_publicas expõe a seed somente quando status = 'sorteado'.
--
-- Rollback manual: DROP TABLE rate_limit_eventos e recriar a view da 0009
-- (as seeds geradas aqui podem permanecer).

CREATE TABLE IF NOT EXISTS rate_limit_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acao VARCHAR(40) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    peso INT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_rle_acao_ip_data (acao, ip, criado_em),
    KEY ix_rle_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE rifas
   SET seed = SHA2(CONCAT(UUID(), RAND(), id, NOW(6), CONNECTION_ID()), 256)
 WHERE metodo = 'sistema' AND status IN ('ativo','encerrado') AND seed IS NULL;

UPDATE rifas
   SET prova_hash = SHA2(seed, 256)
 WHERE metodo = 'sistema' AND status IN ('ativo','encerrado')
   AND seed IS NOT NULL AND prova_hash IS NULL;

CREATE OR REPLACE
SQL SECURITY INVOKER
VIEW v_rifas_publicas AS
SELECT r.id, r.slug, r.titulo, r.descricao, r.capa_url, r.valor_numero,
       r.total_numeros, r.max_por_pessoa, r.data_sorteio, r.metodo,
       r.status, r.pix_instrucoes, r.prova_hash, r.federal_numeros,
       CASE WHEN r.status = 'sorteado' THEN r.seed END AS seed,
       (SELECT COUNT(*) FROM rifa_bilhetes b
         WHERE b.rifa_id = r.id AND b.status_pag IN ('pago','pendente','gratuito')) AS numeros_reservados,
       (SELECT COUNT(*) FROM rifa_premios p WHERE p.rifa_id = r.id) AS total_premios
  FROM rifas r
 WHERE r.deleted_at IS NULL
   AND r.status IN ('ativo','encerrado','sorteado');
