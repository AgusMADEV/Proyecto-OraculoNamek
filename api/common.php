<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

/* ── Global exception / error handler ─────────────────────────────── */
set_exception_handler(function (Throwable $e): void {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('[NousGPT] Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'ok'    => false,
        'error' => 'Error interno del servidor. Revisa los logs para más detalles.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function get_authenticated_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_auth_user_id(): int
{
    $userId = get_authenticated_user_id();
    if ($userId === null) {
        json_response([
            'ok' => false,
            'error' => 'No autenticado.',
        ], 401);
    }

    return $userId;
}

function clean_text(string $value, int $maxLength): string
{
    $value = trim($value);
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}
