<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  Ollama API helpers                                             ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function fetch_ollama_tags(): ?array
{
    if (!OLLAMA_ENABLED) {
        return null;
    }

    $baseUrl  = rtrim(OLLAMA_BASE_URL, '/');
    $endpoint = $baseUrl . '/api/tags';

    $ch = curl_init($endpoint);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($result) || $result === '' || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : null;
}

function get_ollama_model_names(): array
{
    $tags = fetch_ollama_tags();
    if (!is_array($tags)) {
        return [];
    }

    $models = $tags['models'] ?? [];
    if (!is_array($models)) {
        return [];
    }

    $names = [];
    foreach ($models as $model) {
        $name = trim((string) ($model['name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function resolve_ollama_model(?string $requestedModel): string
{
    $requested = trim((string) $requestedModel);
    if ($requested === '') {
        return OLLAMA_MODEL;
    }

    $models = get_ollama_model_names();
    if (!$models) {
        return OLLAMA_MODEL;
    }

    return in_array($requested, $models, true) ? $requested : OLLAMA_MODEL;
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  Message building                                               ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function build_chat_messages(string $systemPrompt, string $message, array $history): array
{
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];

    $recentHistory = array_slice($history, - (MAX_HISTORY_MESSAGES));
    foreach ($recentHistory as $item) {
        $role    = $item['role'] ?? 'user';
        $content = trim((string) ($item['contenido'] ?? ''));
        if ($content !== '' && in_array($role, ['user', 'assistant', 'system'], true)) {
            $messages[] = ['role' => $role, 'content' => $content];
        }
    }

    $messages[] = ['role' => 'user', 'content' => $message];
    return $messages;
}

function estimate_tokens(string $text): int
{
    return (int) ceil(mb_strlen($text) / 3.5);
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  Ollama provider                                                ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function generate_ollama_reply(string $systemPrompt, string $message, array $history, ?string $model = null): ?string
{
    if (!OLLAMA_ENABLED) {
        return null;
    }

    $baseUrl  = rtrim(OLLAMA_BASE_URL, '/');
    $endpoint = $baseUrl . '/api/chat';
    $messages = build_chat_messages($systemPrompt, $message, $history);
    $resolved = resolve_ollama_model($model);

    $payload = [
        'model'    => $resolved,
        'messages' => $messages,
        'stream'   => false,
        'options'  => [
            'temperature' => 0.6,
            'num_predict' => 2048,
        ],
    ];

    $ch = curl_init($endpoint);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => OLLAMA_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($result) || $result === '' || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $decoded = json_decode($result, true);
    $content = $decoded['message']['content'] ?? null;

    if (!is_string($content)) {
        return null;
    }

    $content = trim($content);
    return $content !== '' ? $content : null;
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  OpenAI provider                                                ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function generate_openai_reply(string $systemPrompt, string $message, array $history): ?string
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        return null;
    }

    $payloadMessages = build_chat_messages($systemPrompt, $message, $history);

    $payload = [
        'model'       => OPENAI_MODEL,
        'messages'    => $payloadMessages,
        'temperature' => 0.6,
        'max_tokens'  => 2048,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => OPENAI_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $decoded = json_decode($result, true);
    return $decoded['choices'][0]['message']['content'] ?? null;
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  Local fallback (sin IA)                                        ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function generate_local_reply(string $systemPrompt, string $message, array $history): string
{
    $normalized = mb_strtolower(trim($message));

    if ($normalized === '') {
        return 'No he recibido texto. Escríbeme una pregunta concreta y te ayudo.';
    }

    $patterns = [
        'goku|kakarotto|kakarot'                                  => "**Son Goku (Kakarotto)** 🥋\n\nProtagonista de Dragon Ball. Saiyan de clase baja enviado a la Tierra de bebé. Maestros: Maestro Roshi, Karin, Mr. Popo, Kami, Rey Kai, Whis.\n\n**Transformaciones principales:**\n- Super Saiyan 1, 2, 3\n- Super Saiyan God\n- Super Saiyan Blue\n- Ultra Instinto (Migatte no Gokui)\n\n**Técnicas:** Kamehameha, Kaioken, Genkidama (Esfera Genki), Teletransportación.\n\n**Curiosidad:** Su cola de mono fue cortada permanentemente por Kami.",
        'vegeta|principe'                                         => "**Vegeta - Príncipe de los Saiyan** 👑\n\nPríncipe de la raza Saiyan, inicialmente enemigo y luego rival/aliado de Goku. Orgullo inquebrantable.\n\n**Transformaciones:**\n- Super Saiyan 1, 2\n- Super Saiyan Blue\n- Super Saiyan Blue Evolved\n- Ultra Ego (manga)\n\n**Técnicas:** Galick Gun, Final Flash, Big Bang Attack.\n\n**Dato curioso:** Primera persona en dominar el Super Saiyan por entrenamiento puro (Goku lo logró por ira).",
        'transformaciones|super saiyan|ssj'                       => "**Transformaciones Saiyan** ⚡\n\n1. **Super Saiyan (SSJ):** Multiplicador x50. Pelo dorado, ojos verdes\n2. **SSJ2:** x100. Electricidad alrededor, cabello más erizado\n3. **SSJ3:** x400. Sin cejas, pelo largo hasta la espalda\n4. **SSJ God:** Poder divino, pelo rojo\n5. **SSJ Blue:** SSJ + God Ki, pelo azul\n6. **Ultra Instinto:** Movimiento automático sin pensar\n\n**Requisito:** S-Cells + desencadenante emocional intenso.",
        'esferas|dragon balls|shenron|porunga'                    => "**Esferas del Dragón** 🐉\n\n**Tierra (7 esferas):**\n- Dragón: **Shenron**\n- Deseos: 1 (luego 2-3 según época)\n- Recarga: 1 año\n\n**Namek (7 esferas):**\n- Dragón: **Porunga**\n- Deseos: 3\n- Idioma: Namekiano\n- Recarga: 130 días terrestres\n\n**Super (Super Dragon Balls):**\n- 7 esferas del tamaño de planetas\n- Deseo: Sin límites conocidos",
        'freezer|frieza|freeza'                                   => "**Freezer (Frieza)** ❄️\n\nEmperador del universo, tirano espacial. Destruyó el planeta Vegeta por temor al Super Saiyan legendario.\n\n**Formas:**\n1. Primera forma (supresión)\n2. Segunda forma\n3. Tercera forma\n4. Forma final (100%)\n5. **Golden Frieza** (DBS)\n6. **Black Frieza** (manga actual)\n\n**Poder:** En DBS renace como uno de los mortales más poderosos tras entrenar seriamente.\n\n**Muerte:** Eliminado por Trunks del futuro (cortado por la mitad).",
        'cell|célula'                                             => "**Cell (Célula)** 🧬\n\nAndroide bio-orgánico creado por el Dr. Gero. Contiene células de Goku, Vegeta, Piccolo, Freezer, Rey Cold.\n\n**Formas:**\n- Imperfecto\n- Semi-perfecto\n- **Perfecto** (tras absorber a los Androides 17 y 18)\n- Super Perfecto (regeneración zenkai)\n\n**Técnica especial:** Puede usar ataques de todos los guerreros con sus células (Kamehameha, regeneración Namek, Kienzan, etc.)\n\n**Derrota:** Gohan SSJ2 con Kamehameha padre-hijo.",
        'majin buu|buu|majin bu'                                  => "**Majin Buu** 🍬\n\nEntidad mágica antigua creada por Bibidi, despertada por Babidi.\n\n**Formas:**\n1. **Gordo (Mr. Buu):** Versión amable\n2. **Malvado (Evil Buu):** Mitad oscura separada\n3. **Super Buu:** Fusión de ambos\n4. **Buuhan:** Absorbe a Gohan\n5. **Kid Buu:** Forma original, pura maldad\n\n**Habilidades:** Absorción, conversión en chocolate, regeneración total, resistencia absoluta.\n\n**Final:** Kid Buu eliminado por Genkidama. Mr. Buu vive en paz con los humanos.",
        'bills|beerus|whis'                                       => "**Beerus y Whis** 😸👼\n\n**Beerus (Bills):** Dios de la Destrucción del Universo 7. Poder abrumador, ama la comida terrestre.\n\n**Whis:** Ángel y maestro de Beerus. Puede retroceder el tiempo 3 minutos. Entrena a Goku y Vegeta.\n\n**Jerarquía:** Ángeles > Dioses de la Destrucción > Kaio-shin\n\n**Técnica especial:** Hakai (destrucción absoluta que elimina alma y cuerpo).",
        'torneos|torneo del poder|tournament of power'            => "**Torneos importantes** 🏆\n\n1. **Torneo de las Artes Marciales (Tenkaichi Budokai):** Torneo terrestre clásico\n2. **Torneo del Universo 6 vs 7:** 5vs5, premio: esferas del deseo\n3. **Torneo del Poder:** 8 universos, 10 guerreros c/u, Battle Royale\n   - Premio: Supervivencia del universo\n   - Ganador: Universo 7\n   - MVP: Android 17\n\n**Regla zen-oh:** Universos perdedores = eliminados por los Reyes Omni.",
        'fusion|fusión|gogeta|vegito|gotenks'                     => "**Fusiones** 🤝\n\n**Danza de la Fusión (Metamoru):**\n- Gogeta = Goku + Vegeta\n- Gotenks = Goten + Trunks\n- Duración: 30 minutos\n\n**Pendientes Pothala:**\n- Vegito = Goku + Vegeta (más poderoso que Gogeta)\n- Duración: Permanente para Kaio-shin, 1 hora para mortales\n- Restricción: No reusables (excepto con los de Kaio-shin)\n\n**Curiosidad:** Vegito SSJ Blue vs Zamasu Fusionado se desactivó antes de tiempo por gasto energético.",
        'hola|buenas|hey|saludos'                                 => "¡Hola! 🐉 Soy tu **experto en Dragon Ball**.\n\n¿Qué quieres saber?\n- 🥋 **Personajes** y su historia\n- ⚡ **Transformaciones** y niveles de poder\n- 💥 **Técnicas** de combate\n- 🌍 **Sagas** y cronología\n- 🏆 **Torneos** y batallas épicas\n- 🌟 **Curiosidades** del universo DB\n\n¡Pregúntame lo que quieras sobre el mundo de Akira Toriyama!",
    ];

    foreach ($patterns as $pattern => $response) {
        if (preg_match('/(' . $pattern . ')/iu', $normalized)) {
            return $response;
        }
    }

    $lastTurns   = array_slice($history, -4);
    $contextHint = '';
    foreach ($lastTurns as $turn) {
        if (($turn['role'] ?? '') === 'user') {
            $contextHint = (string) ($turn['contenido'] ?? '');
        }
    }

    return "Como experto en **Dragon Ball**, te recomiendo:\n\n"
        . "1. ⚡ **Especificar tu pregunta:** ¿Personajes? ¿Transformaciones? ¿Sagas?\n"
        . "2. 🥋 **Preguntar sobre técnicas y combates** legendarios\n"
        . "3. 📊 **Comparar niveles de poder** entre personajes\n"
        . "4. 📖 **Explorar la cronología** de DB, DBZ, DBGT, DBS\n\n"
        . ($contextHint !== '' ? "_Conectándolo con tu consulta anterior: \"{$contextHint}\"_\n\n" : '')
        . '¿Sobre qué aspecto del universo Dragon Ball quieres saber más?';
}

/* ╔══════════════════════════════════════════════════════════════════╗
   ║  Orchestrator — Ollama → OpenAI → Local fallback                ║
   ╚══════════════════════════════════════════════════════════════════╝ */

function generate_assistant_reply(string $systemPrompt, string $message, array $history, ?string $model = null): string
{
    $ollama = generate_ollama_reply($systemPrompt, $message, $history, $model);
    if ($ollama !== null && trim($ollama) !== '') {
        return trim($ollama);
    }

    $openAI = generate_openai_reply($systemPrompt, $message, $history);
    if ($openAI !== null && trim($openAI) !== '') {
        return trim($openAI);
    }

    return generate_local_reply($systemPrompt, $message, $history);
}
