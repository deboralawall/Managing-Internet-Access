-- Script com dados iniciais (salas, switches, máquinas de teste)

USE gerencia_redes;

-- Inserir Salas
INSERT INTO salas (nome, descricao) VALUES
('Laboratório de Redes - Sala 101', 'Laboratório de pesquisa em redes de computadores'),
('Sala de Estudos - 102', 'Sala para estudos práticos de TI'),
('Centro de Testes - 103', 'Ambiente de teste para aplicações de rede'),
('Sala de Desenvolvimento - 104', 'Espaço para desenvolvimento de sistemas');

-- Inserir Switches
INSERT INTO switches (nome, ip, comunidade_snmp, sala_id) VALUES
('Switch-Cisco-1', '192.168.1.10', 'public', 1),
('Switch-Cisco-2', '192.168.1.11', 'public', 2),
('Switch-HP-1', '192.168.1.12', 'public', 3),
('Switch-Netgear-1', '192.168.1.13', 'public', 4);

-- Inserir Máquinas de Teste
INSERT INTO maquinas (nome, ip, mac_address, porta_switch, switch_id, sala_id, status) VALUES
('Máquina-Teste-01', '192.168.1.100', '00:11:22:33:44:55', 1, 1, 1, 'ativa'),
('Máquina-Teste-02', '192.168.1.101', '00:11:22:33:44:56', 2, 1, 1, 'ativa'),
('Máquina-Teste-03', '192.168.1.102', '00:11:22:33:44:57', 1, 2, 2, 'ativa'),
('Máquina-Teste-04', '192.168.1.103', '00:11:22:33:44:58', 2, 2, 2, 'ativa'),
('Máquina-Teste-05', '192.168.1.104', '00:11:22:33:44:59', 1, 3, 3, 'ativa'),
('Máquina-Teste-06', '192.168.1.105', '00:11:22:33:44:5a', 2, 3, 3, 'ativa'),
('Máquina-Teste-07', '192.168.1.106', '00:11:22:33:44:5b', 1, 4, 4, 'ativa'),
('Máquina-Teste-08', '192.168.1.107', '00:11:22:33:44:5c', 2, 4, 4, 'ativa');

-- Inserir Usuários Autorizados
INSERT INTO usuarios_autorizados (nome, ip_autorizado, mac_autorizado, senha_hash, ativo) VALUES
('Professor Principal', '192.168.1.50', 'aa:bb:cc:dd:ee:ff', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOP', TRUE),
('Professor Auxiliar', '192.168.1.51', 'aa:bb:cc:dd:ee:00', '$2y$10$XYZabcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMN', TRUE);

-- Inserir Log de Exemplo
INSERT INTO logs_acesso (usuario_ip, usuario_mac, acao, descricao, maquina_id) VALUES
('192.168.1.50', 'aa:bb:cc:dd:ee:ff', 'autenticacao', 'Login bem-sucedido', NULL);
