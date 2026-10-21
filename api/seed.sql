-- seed.sql — dados de desenvolvimento (NÃO usar em produção).
-- Aplicar após as migrations:  php migrate.php --seed
-- Ou manualmente no phpMyAdmin.

-- Cargos
INSERT INTO cargos (nome, ordem) VALUES
('membro', 10), ('congregado', 5), ('auxiliar', 20), ('diácono', 30),
('presbítero', 40), ('evangelista', 50), ('missionária', 55), ('pastor', 60);

-- Departamentos
INSERT INTO departamentos (nome, descricao) VALUES
('Círculo de Oração', 'Intercessão e oração'),
('Mocidade', 'Ministério de jovens'),
('EBD', 'Escola Bíblica Dominical'),
('Ação Social', 'Obras sociais na comunidade'),
('Mulheres', 'Ministério feminino'),
('Homens', 'Ministério masculino');

-- Tipos de culto
INSERT INTO tipos_culto (nome, dia_semana_sugerido, horario_padrao) VALUES
('Escola Bíblica Dominical', 0, '08:00'),
('Culto de Adoração e Louvor', 0, '18:30'),
('Culto da Família', 3, '19:30'),
('Culto da Unidade', 2, '19:00'),
('Círculo de Oração', 2, '08:00');

-- Pessoas (exemplos)
INSERT INTO pessoas (nome_completo, data_nascimento, sexo, telefone, cidade, uf) VALUES
('Admin Sistema', '1990-01-01', 'M', '91999990001', 'Belém', 'PA'),
('Maria da Silva', '1985-03-12', 'F', '91999990002', 'Belém', 'PA'),
('João Pereira', '1978-07-25', 'M', '91999990003', 'Belém', 'PA'),
('Ana Costa', '1992-11-30', 'F', '91999990004', 'Belém', 'PA'),
('Pedro Santos', '2001-05-08', 'M', '91999990005', 'Belém', 'PA');

-- Membros
INSERT INTO membros (pessoa_id, numero_carteirinha, data_batismo, cargo_id, status) VALUES
(1, 'ADAB-0001', '2010-02-14', 8, 'ativo'),
(2, 'ADAB-0002', '2011-06-20', 1, 'ativo'),
(3, 'ADAB-0003', '2009-01-10', 4, 'ativo'),
(4, 'ADAB-0004', '2015-09-05', 1, 'ativo'),
(5, 'ADAB-0005', '2020-12-19', 2, 'ativo');

-- Usuários de desenvolvimento — senha de todos: "password"
-- (hash bcrypt de teste; troque em produção via create-user.php)
INSERT INTO usuarios (pessoa_id, email, senha_hash, perfil) VALUES
(1, 'admin@adab.dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'secretaria@adab.dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'secretaria'),
(3, 'ebd.admin@adab.dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ebd_admin'),
(4, 'professor@adab.dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ebd_professor'),
(5, 'dirigente@adab.dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dirigente');

-- Classes EBD
INSERT INTO ebd_classes (nome, faixa_etaria, sala) VALUES
('Juniores', '9 a 11 anos', 'Sala 1'),
('Adolescentes', '12 a 17 anos', 'Sala 2'),
('Jovens', '18 a 30 anos', 'Sala 3'),
('Adultos', '31+ anos', 'Templo');

-- Professores
INSERT INTO ebd_professores (classe_id, pessoa_id, papel) VALUES
(2, 4, 'titular'),
(4, 3, 'titular');

-- Alunos de exemplo
INSERT INTO ebd_alunos (classe_id, pessoa_id, data_matricula) VALUES
(4, 2, '2026-01-11'),
(4, 5, '2026-01-11');

-- Período letivo + aulas (13 domingos do 1º trimestre de 2027)
INSERT INTO ebd_periodos (ano, trimestre, tema_revista, data_inicio, data_fim) VALUES
(2027, 1, 'A Igreja e sua missão', '2027-01-03', '2027-03-28');
