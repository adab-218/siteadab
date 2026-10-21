-- 0001_nucleo: pessoas, cargos, departamentos, membros, usuarios

CREATE TABLE cargos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(60) NOT NULL UNIQUE,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pessoas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(200) NOT NULL,
    nome_social VARCHAR(200) NULL,
    data_nascimento DATE NULL,
    sexo CHAR(1) NULL,
    estado_civil VARCHAR(20) NULL,
    conjuge_id BIGINT UNSIGNED NULL,
    cpf VARCHAR(14) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(120) NULL,
    cep VARCHAR(9) NULL,
    logradouro VARCHAR(200) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    foto_url VARCHAR(255) NULL,
    consentimento_lgpd_em DATETIME NULL,
    observacoes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_pessoas_cpf (cpf),
    KEY ix_pessoas_nome (nome_completo),
    KEY ix_pessoas_deleted_nome (deleted_at, nome_completo),
    CONSTRAINT fk_pessoas_conjuge FOREIGN KEY (conjuge_id) REFERENCES pessoas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE departamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    descricao TEXT NULL,
    lider_id BIGINT UNSIGNED NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_departamentos_lider FOREIGN KEY (lider_id) REFERENCES pessoas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE membros (
    pessoa_id BIGINT UNSIGNED PRIMARY KEY,
    numero_carteirinha VARCHAR(30) NULL UNIQUE,
    data_conversao DATE NULL,
    data_batismo DATE NULL,
    batizado_espirito_santo TINYINT(1) NOT NULL DEFAULT 0,
    congregacao VARCHAR(120) NULL,
    procedencia VARCHAR(200) NULL,
    cargo_id BIGINT UNSIGNED NULL,
    status ENUM('ativo','afastado','transferido','falecido','disciplinado') NOT NULL DEFAULT 'ativo',
    data_status DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_membros_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id) ON DELETE CASCADE,
    CONSTRAINT fk_membros_cargo FOREIGN KEY (cargo_id) REFERENCES cargos (id) ON DELETE SET NULL,
    KEY ix_membros_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE membro_departamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    departamento_id BIGINT UNSIGNED NOT NULL,
    funcao VARCHAR(80) NULL,
    data_inicio DATE NULL,
    data_fim DATE NULL,
    CONSTRAINT fk_md_pessoa FOREIGN KEY (pessoa_id) REFERENCES membros (pessoa_id) ON DELETE CASCADE,
    CONSTRAINT fk_md_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id) ON DELETE CASCADE,
    KEY ix_md_pessoa (pessoa_id),
    KEY ix_md_departamento (departamento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin','secretaria','dirigente','ebd_admin','ebd_professor','lider_departamento') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    reset_token VARCHAR(128) NULL,
    reset_expira DATETIME NULL,
    ultimo_login_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
