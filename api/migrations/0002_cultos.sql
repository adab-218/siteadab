-- 0002_cultos: tipos de culto, registro de cultos, presença nominal

CREATE TABLE tipos_culto (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    dia_semana_sugerido TINYINT NULL COMMENT '0=domingo ... 6=sábado',
    horario_padrao TIME NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cultos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_culto_id BIGINT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    horario_inicio TIME NULL,
    dirigente_id BIGINT UNSIGNED NULL,
    pregador_id BIGINT UNSIGNED NULL,
    tema VARCHAR(200) NULL,
    total_presentes INT UNSIGNED NULL,
    total_visitantes INT UNSIGNED NULL,
    total_decisoes INT UNSIGNED NULL,
    observacoes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cultos_tipo_data (tipo_culto_id, data),
    KEY ix_cultos_data (data),
    CONSTRAINT fk_cultos_tipo FOREIGN KEY (tipo_culto_id) REFERENCES tipos_culto (id),
    CONSTRAINT fk_cultos_dirigente FOREIGN KEY (dirigente_id) REFERENCES pessoas (id) ON DELETE SET NULL,
    CONSTRAINT fk_cultos_pregador FOREIGN KEY (pregador_id) REFERENCES pessoas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE culto_presencas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    culto_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_culto_pessoa (culto_id, pessoa_id),
    CONSTRAINT fk_cp_culto FOREIGN KEY (culto_id) REFERENCES cultos (id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
