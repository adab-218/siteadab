-- 0005_ebd: Escola Bíblica Dominical — classes, professores,
-- alunos (matrícula), períodos, aulas e frequência (chamada)

CREATE TABLE ebd_classes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    faixa_etaria VARCHAR(60) NULL,
    sala VARCHAR(60) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ebd_professores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    papel ENUM('titular','auxiliar','substituto') NOT NULL DEFAULT 'auxiliar',
    data_inicio DATE NULL,
    data_fim DATE NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_ep_classe FOREIGN KEY (classe_id) REFERENCES ebd_classes (id) ON DELETE CASCADE,
    CONSTRAINT fk_ep_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id) ON DELETE CASCADE,
    KEY ix_ep_classe (classe_id),
    KEY ix_ep_pessoa (pessoa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ebd_alunos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    classe_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    data_matricula DATE NOT NULL,
    data_desistencia DATE NULL,
    status ENUM('matriculado','transferido','desistente') NOT NULL DEFAULT 'matriculado',
    observacao TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ea_classe FOREIGN KEY (classe_id) REFERENCES ebd_classes (id),
    CONSTRAINT fk_ea_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id),
    UNIQUE KEY uq_ea_classe_pessoa_ativo (classe_id, pessoa_id, status),
    KEY ix_ea_pessoa (pessoa_id),
    KEY ix_ea_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ebd_periodos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ano SMALLINT UNSIGNED NOT NULL,
    trimestre TINYINT UNSIGNED NOT NULL,
    tema_revista VARCHAR(200) NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    UNIQUE KEY uq_periodo (ano, trimestre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ebd_aulas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    periodo_id BIGINT UNSIGNED NOT NULL,
    numero_licao TINYINT UNSIGNED NOT NULL,
    titulo_licao VARCHAR(200) NULL,
    data DATE NOT NULL,
    texto_aureo TEXT NULL,
    leitura_biblica VARCHAR(200) NULL,
    UNIQUE KEY uq_aula_periodo_num (periodo_id, numero_licao),
    KEY ix_aulas_data (data),
    CONSTRAINT fk_aulas_periodo FOREIGN KEY (periodo_id) REFERENCES ebd_periodos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ebd_frequencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aula_id BIGINT UNSIGNED NOT NULL,
    aluno_id BIGINT UNSIGNED NOT NULL,
    presente TINYINT(1) NOT NULL DEFAULT 0,
    trouxe_biblia TINYINT(1) NOT NULL DEFAULT 0,
    trouxe_revista TINYINT(1) NOT NULL DEFAULT 0,
    oferta DECIMAL(10,2) NULL,
    visitante TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_freq_aula_aluno (aula_id, aluno_id),
    KEY ix_freq_aluno (aluno_id),
    CONSTRAINT fk_freq_aula FOREIGN KEY (aula_id) REFERENCES ebd_aulas (id) ON DELETE CASCADE,
    CONSTRAINT fk_freq_aluno FOREIGN KEY (aluno_id) REFERENCES ebd_alunos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
