<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=utf8mb4;unix_socket=%s',
        DB_HOST,
        DB_PORT,
        DB_SOCKET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('[Oráculo Namek] Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'No se pudo conectar a la base de datos. Verifica que MySQL (MAMP) esté en ejecución.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $pdo;
}

function init_database(PDO $pdo): void
{
    try {
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `dragonball_chat` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `dragonball_chat`');
    } catch (PDOException $e) {
        error_log('[Oráculo Namek] init_database failed: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => 'Error al inicializar la base de datos: ' . $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(120) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME NULL
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS conversaciones (
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
        ) ENGINE=InnoDB'
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS mensajes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversacion_id INT NOT NULL,
            role ENUM('user', 'assistant', 'system') NOT NULL,
            contenido MEDIUMTEXT NOT NULL,
            tokens_estimados INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_mensajes_conversaciones
                FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB"
    );

    ensure_index_exists($pdo, 'conversaciones', 'idx_conversaciones_usuario', 'CREATE INDEX idx_conversaciones_usuario ON conversaciones(usuario_id, updated_at)');
    ensure_index_exists($pdo, 'mensajes', 'idx_mensajes_conversacion', 'CREATE INDEX idx_mensajes_conversacion ON mensajes(conversacion_id, created_at)');
}

function ensure_index_exists(PDO $pdo, string $tableName, string $indexName, string $createIndexSql): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$tableName, $indexName]);
    $exists = (int) ($stmt->fetch()['total'] ?? 0) > 0;

    if (!$exists) {
        $pdo->exec($createIndexSql);
    }
}
