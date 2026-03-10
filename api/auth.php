<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

$pdo = get_pdo();
init_database($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'me') {
    try {
        $userId = get_authenticated_user_id();
        if ($userId === null) {
            json_response(['ok' => true, 'authenticated' => false]);
        }

        $stmt = $pdo->prepare('SELECT id, username, email, created_at, last_login FROM usuarios WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            session_destroy();
            json_response(['ok' => true, 'authenticated' => false]);
        }

        json_response(['ok' => true, 'authenticated' => true, 'user' => $user]);
    } catch (Throwable $e) {
        error_log('[NousGPT] auth.php me error: ' . $e->getMessage());
        json_response(['ok' => true, 'authenticated' => false]);
    }
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$input = parse_json_body();
$action = $input['action'] ?? '';

if ($action === 'register') {
    $username = clean_text((string) ($input['username'] ?? ''), 50);
    $email = clean_text((string) ($input['email'] ?? ''), 120);
    $password = (string) ($input['password'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        json_response(['ok' => false, 'error' => 'Todos los campos son obligatorios.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'error' => 'Email no válido.'], 400);
    }

    if (mb_strlen($password) < 6) {
        json_response(['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'], 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare('INSERT INTO usuarios (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $hash]);
        $userId = (int) $pdo->lastInsertId();

        $_SESSION['user_id'] = $userId;

        json_response([
            'ok' => true,
            'message' => 'Cuenta creada correctamente.',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
            ],
        ]);
    } catch (PDOException $exception) {
        $error = 'No se pudo registrar el usuario.';
        if ((int) $exception->getCode() === 23000) {
            $error = 'El usuario o el email ya existen.';
        }
        json_response(['ok' => false, 'error' => $error], 409);
    }
}

if ($action === 'login') {
    $email = clean_text((string) ($input['email'] ?? ''), 120);
    $password = (string) ($input['password'] ?? '');

    if ($email === '' || $password === '') {
        json_response(['ok' => false, 'error' => 'Email y contraseña son obligatorios.'], 400);
    }

    $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['ok' => false, 'error' => 'Credenciales no válidas.'], 401);
    }

    $_SESSION['user_id'] = (int) $user['id'];

    $upd = $pdo->prepare('UPDATE usuarios SET last_login = NOW() WHERE id = ?');
    $upd->execute([(int) $user['id']]);

    json_response([
        'ok' => true,
        'message' => 'Login correcto.',
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
        ],
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    json_response(['ok' => true, 'message' => 'Sesión cerrada.']);
}

json_response(['ok' => false, 'error' => 'Acción no válida.'], 400);
