<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../lib/assistant.php';

$pdo    = get_pdo();
init_database($pdo);

$userId = require_auth_user_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function load_user_conversation(PDO $pdo, int $conversationId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT id, titulo, system_prompt FROM conversaciones WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$conversationId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function check_rate_limit(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM mensajes m
         JOIN conversaciones c ON m.conversacion_id = c.id
         WHERE c.usuario_id = ? AND m.role = ? AND m.created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
    );
    $stmt->execute([$userId, 'user', RATE_LIMIT_WINDOW_SECONDS]);
    $count = (int) ($stmt->fetch()['total'] ?? 0);

    return $count < RATE_LIMIT_MESSAGES_PER_MINUTE;
}

/* ── GET: load messages ────────────────────────────────────────────── */
if ($method === 'GET') {
    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    if ($conversationId <= 0) {
        json_response(['ok' => false, 'error' => 'conversation_id no válido.'], 400);
    }

    $conversation = load_user_conversation($pdo, $conversationId, $userId);
    if (!$conversation) {
        json_response(['ok' => false, 'error' => 'Conversación no encontrada.'], 404);
    }

    $stmt = $pdo->prepare('SELECT id, role, contenido, tokens_estimados, created_at FROM mensajes WHERE conversacion_id = ? ORDER BY id ASC');
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll();

    json_response([
        'ok'           => true,
        'conversation' => $conversation,
        'messages'     => $messages,
    ]);
}

/* ── POST: send message ────────────────────────────────────────────── */
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$input          = parse_json_body();
$conversationId = (int) ($input['conversation_id'] ?? 0);
$message        = clean_text((string) ($input['message'] ?? ''), MAX_MESSAGE_LENGTH);
$selectedModel  = clean_text((string) ($input['model'] ?? ''), 100);

if ($conversationId <= 0 || $message === '') {
    json_response(['ok' => false, 'error' => 'Datos de mensaje no válidos.'], 400);
}

$conversation = load_user_conversation($pdo, $conversationId, $userId);
if (!$conversation) {
    json_response(['ok' => false, 'error' => 'Conversación no encontrada.'], 404);
}

// Rate limiting
if (!check_rate_limit($pdo, $userId)) {
    json_response(['ok' => false, 'error' => 'Has enviado demasiados mensajes. Espera un momento.'], 429);
}

$stmtHistory = $pdo->prepare('SELECT role, contenido FROM mensajes WHERE conversacion_id = ? ORDER BY id ASC');
$stmtHistory->execute([$conversationId]);
$history = $stmtHistory->fetchAll();

$sessionModel   = clean_text((string) ($_SESSION['selected_model'] ?? ''), 100);
$effectiveModel = resolve_ollama_model($selectedModel !== '' ? $selectedModel : ($sessionModel !== '' ? $sessionModel : OLLAMA_MODEL));
$_SESSION['selected_model'] = $effectiveModel;

// Generate assistant reply with error handling
try {
    $startTime      = microtime(true);
    $assistantReply = generate_assistant_reply((string) $conversation['system_prompt'], $message, $history, $effectiveModel);
    $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);
} catch (Throwable $aiError) {
    error_log('[NousGPT] AI generation error: ' . $aiError->getMessage());
    $assistantReply = 'Lo siento, hubo un error al generar la respuesta. Por favor, inténtalo de nuevo.';
    $responseTimeMs = 0;
}

$userTokens      = estimate_tokens($message);
$assistantTokens = estimate_tokens($assistantReply);

$pdo->beginTransaction();
try {
    $insertUser = $pdo->prepare('INSERT INTO mensajes (conversacion_id, role, contenido, tokens_estimados) VALUES (?, ?, ?, ?)');
    $insertUser->execute([$conversationId, 'user', $message, $userTokens]);
    $userMessageId = (int) $pdo->lastInsertId();

    $insertAssistant = $pdo->prepare('INSERT INTO mensajes (conversacion_id, role, contenido, tokens_estimados) VALUES (?, ?, ?, ?)');
    $insertAssistant->execute([$conversationId, 'assistant', $assistantReply, $assistantTokens]);
    $assistantMessageId = (int) $pdo->lastInsertId();

    $updateConversation = $pdo->prepare('UPDATE conversaciones SET updated_at = NOW() WHERE id = ?');
    $updateConversation->execute([$conversationId]);

    $pdo->commit();
} catch (Throwable $error) {
    error_log('[NousGPT] DB save error: ' . $error->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'error' => 'No se pudo guardar el mensaje: ' . $error->getMessage()], 500);
}

json_response([
    'ok'              => true,
    'inserted'        => [
        'user_message_id'      => $userMessageId,
        'assistant_message_id' => $assistantMessageId,
    ],
    'assistant_reply' => $assistantReply,
    'model'           => $effectiveModel,
    'response_time_ms' => $responseTimeMs,
    'tokens'          => [
        'user'      => $userTokens,
        'assistant' => $assistantTokens,
    ],
]);
