-- 0004_acoes: ações sociais e evangelísticas

CREATE TABLE acoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    tipo ENUM('social','evangelistica','outra') NOT NULL DEFAULT 'outra',
    data_inicio DATE NULL,
    data_fim DATE NULL,
    local VARCHAR(200) NULL,
    objetivo TEXT NULL,
    resultado TEXT NULL,
    departamento_id BIGINT UNSIGNED NULL,
    status ENUM('planejada','em_andamento','realizada','cancelada') NOT NULL DEFAULT 'planejada',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_acoes_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id) ON DELETE SET NULL,
    KEY ix_acoes_status (status),
    KEY ix_acoes_inicio (data_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE acao_voluntarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acao_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    papel ENUM('coordenador','voluntario') NOT NULL DEFAULT 'voluntario',
    UNIQUE KEY uq_acao_pessoa (acao_id, pessoa_id),
    CONSTRAINT fk_av_acao FOREIGN KEY (acao_id) REFERENCES acoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_av_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE acao_beneficiarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acao_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(200) NOT NULL,
    contato VARCHAR(120) NULL,
    observacao TEXT NULL,
    CONSTRAINT fk_ab_acao FOREIGN KEY (acao_id) REFERENCES acoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
