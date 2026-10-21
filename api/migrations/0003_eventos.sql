-- 0003_eventos: festividades e programação interna

CREATE TABLE eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    tipo ENUM('congresso','festividade','culto_especial','data_comemorativa','outro') NOT NULL DEFAULT 'outro',
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    local VARCHAR(200) NULL,
    descricao TEXT NULL,
    banner_url VARCHAR(255) NULL,
    publico TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('planejado','confirmado','realizado','cancelado') NOT NULL DEFAULT 'planejado',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY ix_eventos_inicio (data_inicio),
    KEY ix_eventos_publico (publico, data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evento_atividades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evento_id BIGINT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    horario_inicio TIME NULL,
    horario_fim TIME NULL,
    titulo VARCHAR(200) NOT NULL,
    responsavel_id BIGINT UNSIGNED NULL,
    descricao TEXT NULL,
    CONSTRAINT fk_ea_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
    CONSTRAINT fk_ea_responsavel FOREIGN KEY (responsavel_id) REFERENCES pessoas (id) ON DELETE SET NULL,
    KEY ix_ea_evento_data (evento_id, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
