-- ╔══════════════════════════════════════════════════════════════════╗
-- ║  Oráculo Namek v2.0 — Database Schema                           ║
-- ║  Chat especializado en Dragon Ball con IA                       ║
-- ╚══════════════════════════════════════════════════════════════════╝

CREATE DATABASE IF NOT EXISTS dragonball_chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dragonball_chat;

-- ── Usuarios ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
) ENGINE=InnoDB;

-- ── Conversaciones ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    system_prompt TEXT NOT NULL,
    archivada TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_conversaciones_usuarios
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Mensajes ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT NOT NULL,
    role ENUM('user', 'assistant', 'system') NOT NULL,
    contenido MEDIUMTEXT NOT NULL,
    tokens_estimados INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensajes_conversaciones
        FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Índices para rendimiento ──────────────────────────────────────────
CREATE INDEX idx_conversaciones_usuario ON conversaciones(usuario_id, updated_at);
CREATE INDEX idx_mensajes_conversacion ON mensajes(conversacion_id, created_at);
CREATE INDEX idx_mensajes_rate_limit ON mensajes(created_at, role);