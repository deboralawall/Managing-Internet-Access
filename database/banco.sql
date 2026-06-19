-- Script de criação das tabelas, chaves estrangeiras e índices
-- Banco de Dados: Gerência de Redes (Sistema de Bloqueio/Desbloqueio de Portas)

CREATE DATABASE IF NOT EXISTS gerencia_redes;
USE gerencia_redes;

-- Tabela de Salas
CREATE TABLE IF NOT EXISTS salas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Switches de Rede
CREATE TABLE IF NOT EXISTS switches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    ip VARCHAR(15) NOT NULL UNIQUE,
    comunidade_snmp VARCHAR(100) NOT NULL,
    sala_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE CASCADE,
    INDEX idx_sala (sala_id)
);

-- Tabela de Máquinas de Teste
CREATE TABLE IF NOT EXISTS maquinas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    ip VARCHAR(15) NOT NULL UNIQUE,
    mac_address VARCHAR(17) NOT NULL UNIQUE,
    porta_switch INT NOT NULL,
    switch_id INT NOT NULL,
    sala_id INT NOT NULL,
    status ENUM('ativa', 'inativa', 'bloqueada') DEFAULT 'ativa',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (switch_id) REFERENCES switches(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE CASCADE,
    INDEX idx_switch (switch_id),
    INDEX idx_sala (sala_id)
);

-- Tabela de Logs de Acesso
CREATE TABLE IF NOT EXISTS logs_acesso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_ip VARCHAR(15) NOT NULL,
    usuario_mac VARCHAR(17),
    acao VARCHAR(50) NOT NULL,
    descricao TEXT,
    maquina_id INT,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (maquina_id) REFERENCES maquinas(id) ON DELETE SET NULL,
    INDEX idx_data (data_hora),
    INDEX idx_usuario (usuario_ip)
);

-- Tabela de Bloqueios Agendados
CREATE TABLE IF NOT EXISTS bloqueios_agendados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    maquina_id INT NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME,
    motivo VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (maquina_id) REFERENCES maquinas(id) ON DELETE CASCADE,
    INDEX idx_maquina (maquina_id),
    INDEX idx_data_inicio (data_inicio)
);

-- Tabela de Usuários Autorizados
CREATE TABLE IF NOT EXISTS usuarios_autorizados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    ip_autorizado VARCHAR(15) NOT NULL,
    mac_autorizado VARCHAR(17) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ip_mac (ip_autorizado, mac_autorizado),
    INDEX idx_ativo (ativo)
);
