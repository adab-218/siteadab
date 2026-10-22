-- 0008_dados_iniciais: dados de referência (D12) — idempotente via
-- INSERT IGNORE. tipos_culto derivados da programação fixa do site;
-- cargos padrão AD. Adicionais/editais via UI.

-- Tipos de culto (dia_semana_sugerido: 0=domingo, 3=quarta)
INSERT IGNORE INTO tipos_culto (nome, dia_semana_sugerido, horario_padrao) VALUES
    ('Escola Bíblica Dominical', 0, '08:00:00'),
    ('Culto de Adoração e Louvor', 0, '18:30:00'),
    ('Culto da Família', 3, '19:30:00'),
    ('Culto de Doutrina', NULL, NULL),
    ('Culto Evangelístico', NULL, NULL);

-- Cargos iniciais (ordem de precedência eclesiástica usual)
INSERT IGNORE INTO cargos (nome, ordem) VALUES
    ('Membro', 10),
    ('Diácono', 20),
    ('Presbítero', 30),
    ('Evangelista', 40),
    ('Pastor', 50);
