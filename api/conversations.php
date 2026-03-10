<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

$pdo = get_pdo();
init_database($pdo);

$userId = require_auth_user_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT c.id, c.titulo, c.system_prompt, c.created_at, c.updated_at,
                (SELECT contenido FROM mensajes m WHERE m.conversacion_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultimo_mensaje,
                (SELECT COUNT(*) FROM mensajes m2 WHERE m2.conversacion_id = c.id) AS total_mensajes
         FROM conversaciones c
         WHERE c.usuario_id = ? AND c.archivada = 0
         ORDER BY c.updated_at DESC'
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    json_response(['ok' => true, 'conversations' => $rows]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$input = parse_json_body();
$action = (string) ($input['action'] ?? '');

if ($action === 'create') {
    $title = clean_text((string) ($input['title'] ?? ''), 120);
    $systemPrompt = clean_text((string) ($input['system_prompt'] ?? DEFAULT_SYSTEM_PROMPT), 1000);

    if ($title === '') {
        $title = 'Conversación sin título';
    }
    if ($systemPrompt === '') {
        $systemPrompt = DEFAULT_SYSTEM_PROMPT;
    }

    $stmt = $pdo->prepare('INSERT INTO conversaciones (usuario_id, titulo, system_prompt) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $title, $systemPrompt]);
    $conversationId = (int) $pdo->lastInsertId();

    $insertWelcome = $pdo->prepare('INSERT INTO mensajes (conversacion_id, role, contenido) VALUES (?, ?, ?)');
    $insertWelcome->execute([$conversationId, 'assistant', '¡Hola! Soy ' . ASSISTANT_NAME . '. 🐉 Estoy listo para responder todas tus preguntas sobre el universo Dragon Ball. ¿Qué quieres saber?']);

    json_response([
        'ok' => true,
        'conversation' => [
            'id' => $conversationId,
            'titulo' => $title,
            'system_prompt' => $systemPrompt,
        ],
    ]);
}

if ($action === 'rename') {
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    $title = clean_text((string) ($input['title'] ?? ''), 120);

    if ($conversationId <= 0 || $title === '') {
        json_response(['ok' => false, 'error' => 'Datos inválidos para renombrar.'], 400);
    }

    $stmt = $pdo->prepare('UPDATE conversaciones SET titulo = ?, updated_at = NOW() WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$title, $conversationId, $userId]);

    if ($stmt->rowCount() === 0) {
        json_response(['ok' => false, 'error' => 'Conversación no encontrada.'], 404);
    }

    json_response(['ok' => true, 'message' => 'Conversación renombrada.']);
}

if ($action === 'delete') {
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        json_response(['ok' => false, 'error' => 'ID de conversación no válido.'], 400);
    }

    $stmt = $pdo->prepare('DELETE FROM conversaciones WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$conversationId, $userId]);

    if ($stmt->rowCount() === 0) {
        json_response(['ok' => false, 'error' => 'Conversación no encontrada.'], 404);
    }

    json_response(['ok' => true, 'message' => 'Conversación eliminada.']);
}

json_response(['ok' => false, 'error' => 'Acción no válida.'], 400);
