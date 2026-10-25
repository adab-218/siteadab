-- 0009_rifas: M7 Sorteios Beneficentes — sorteio multiprêmios, prêmios
-- por departamento, repartição percentual, links de divulgação por
-- membro e bilhetes (reserva pública, PIX manual, expiração 24h — doc 13)

CREATE TABLE IF NOT EXISTS rifas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    descricao TEXT NULL,
    regulamento TEXT NULL,
    capa_url VARCHAR(255) NULL,
    valor_numero DECIMAL(10,2) NOT NULL,
    total_numeros INT UNSIGNED NOT NULL,
    max_por_pessoa INT UNSIGNED NOT NULL DEFAULT 50,
    data_sorteio DATETIME NULL,
    metodo ENUM('sistema','federal') NOT NULL DEFAULT 'sistema',
    status ENUM('rascunho','ativo','encerrado','sorteado','cancelado') NOT NULL DEFAULT 'rascunho',
    pix_instrucoes TEXT NULL,
    seed VARCHAR(128) NULL,
    prova_hash VARCHAR(128) NULL,
    federal_numeros VARCHAR(200) NULL,
    cancelado_motivo VARCHAR(300) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY ix_rifas_status (status),
    KEY ix_rifas_sorteio (data_sorteio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rifa_reparticao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rifa_id BIGINT UNSIGNED NOT NULL,
    destino VARCHAR(120) NOT NULL,
    tipo ENUM('livre','departamentos') NOT NULL DEFAULT 'livre',
    percentual DECIMAL(5,2) NOT NULL,
    CONSTRAINT fk_rr_rifa FOREIGN KEY (rifa_id) REFERENCES rifas (id) ON DELETE CASCADE,
    KEY ix_rr_rifa (rifa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rifa_divulgadores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rifa_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    token CHAR(32) NOT NULL UNIQUE,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rd_rifa FOREIGN KEY (rifa_id) REFERENCES rifas (id) ON DELETE CASCADE,
    CONSTRAINT fk_rd_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id) ON DELETE CASCADE,
    UNIQUE KEY uq_rd_rifa_pessoa (rifa_id, pessoa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rifa_bilhetes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rifa_id BIGINT UNSIGNED NOT NULL,
    numero INT UNSIGNED NULL,
    nome VARCHAR(200) NOT NULL,
    telefone VARCHAR(30) NOT NULL,
    email VARCHAR(120) NULL,
    status_pag ENUM('pendente','pago','gratuito','expirado','falhou') NOT NULL DEFAULT 'pendente',
    divulgador_id BIGINT UNSIGNED NULL,
    expira_em DATETIME NULL,
    pago_em DATETIME NULL,
    pago_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rb_rifa FOREIGN KEY (rifa_id) REFERENCES rifas (id) ON DELETE CASCADE,
    CONSTRAINT fk_rb_divulgador FOREIGN KEY (divulgador_id) REFERENCES rifa_divulgadores (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_pagopor FOREIGN KEY (pago_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    UNIQUE KEY uq_rb_rifa_numero (rifa_id, numero),
    KEY ix_rb_telefone (rifa_id, telefone),
    KEY ix_rb_divulgador (rifa_id, divulgador_id),
    KEY ix_rb_expira (rifa_id, status_pag, expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rifa_premios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rifa_id BIGINT UNSIGNED NOT NULL,
    ordem TINYINT UNSIGNED NOT NULL DEFAULT 1,
    titulo VARCHAR(160) NOT NULL,
    descricao TEXT NULL,
    valor_estimado DECIMAL(10,2) NULL,
    imagem_url VARCHAR(255) NULL,
    departamento_id BIGINT UNSIGNED NOT NULL,
    bilhete_vencedor_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rp_rifa FOREIGN KEY (rifa_id) REFERENCES rifas (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_depto FOREIGN KEY (departamento_id) REFERENCES departamentos (id),
    CONSTRAINT fk_rp_vencedor FOREIGN KEY (bilhete_vencedor_id) REFERENCES rifa_bilhetes (id) ON DELETE SET NULL,
    KEY ix_rp_rifa (rifa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exposição pública: só rifas em fase visível, sem dados de compradores
CREATE OR REPLACE VIEW v_rifas_publicas AS
SELECT r.id, r.slug, r.titulo, r.descricao, r.capa_url, r.valor_numero,
       r.total_numeros, r.max_por_pessoa, r.data_sorteio, r.metodo,
       r.status, r.pix_instrucoes, r.prova_hash, r.federal_numeros,
       (SELECT COUNT(*) FROM rifa_bilhetes b
         WHERE b.rifa_id = r.id AND b.status_pag IN ('pago','pendente','gratuito')) AS numeros_reservados,
       (SELECT COUNT(*) FROM rifa_premios p WHERE p.rifa_id = r.id) AS total_premios
  FROM rifas r
 WHERE r.deleted_at IS NULL
   AND r.status IN ('ativo','encerrado','sorteado');
