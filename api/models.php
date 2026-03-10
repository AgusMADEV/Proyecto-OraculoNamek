<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../lib/assistant.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
require_auth_user_id();

if ($method === 'GET') {
    $models = get_ollama_model_names();
    $selectedModel = resolve_ollama_model((string) ($_SESSION['selected_model'] ?? OLLAMA_MODEL));
    $_SESSION['selected_model'] = $selectedModel;

    json_response([
        'ok' => true,
        'provider' => OLLAMA_ENABLED ? 'ollama' : 'none',
        'default_model' => OLLAMA_MODEL,
        'selected_model' => $selectedModel,
        'models' => $models,
    ]);
}

if ($method === 'POST') {
    $input = parse_json_body();
    $requestedModel = clean_text((string) ($input['model'] ?? ''), 100);
    $selectedModel = resolve_ollama_model($requestedModel !== '' ? $requestedModel : OLLAMA_MODEL);
    $_SESSION['selected_model'] = $selectedModel;

    json_response([
        'ok' => true,
        'selected_model' => $selectedModel,
    ]);
}

json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
